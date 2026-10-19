<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\PresupuestoPdfGenerator;
use backend\models\ConfiguracionSolicitudCotizacion;
use backend\models\Cotizacion;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SolicitudCotizacion;
use backend\models\SolicitudCotizacionItem;
use Throwable;
use Yii;

/**
 * Capa de Servicio para las solicitudes de cotizacion de un proyecto.
 *
 * No todos los proveedores venden lo mismo (el que vende tornillos no vende
 * racks): la lista del proyecto se reparte en varias solicitudes, una por
 * rubro, y cada una genera su propio documento.
 *
 * Reglas de negocio que viven aqui:
 *  - las solicitudes se arman con la lista "en cotizacion" (items y
 *    cantidades ya fijos) y mientras el presupuesto no este cerrado,
 *  - cada item de la lista va en UNA sola solicitud,
 *  - solo entran items de la lista de ESE proyecto,
 *  - las cantidades no se copian: se leen de la lista,
 *  - una solicitud con cotizaciones registradas no se borra, y sus items
 *    no se mueven ni se quitan (ni se le agregan otros),
 *  - la marca y modelo de referencia de cada item se edita mientras la
 *    solicitud no tenga cotizaciones (ya se le pidio eso al proveedor).
 */
class SolicitudCotizacionService
{
    public function puedeEditarse(Proyecto $proyecto): bool
    {
        $presupuesto = $proyecto->presupuesto;

        return $proyecto->estadoLista === Proyecto::LISTA_EN_COTIZACION
            && ($presupuesto === null || $presupuesto->puedeEditarse());
    }

    /**
     * Solicitudes del proyecto, con sus items ya cargados.
     *
     * @return SolicitudCotizacion[]
     */
    public function listar(Proyecto $proyecto): array
    {
        return SolicitudCotizacion::find()
            ->andWhere(['idProyecto' => $proyecto->id])
            ->with(['items.proyectoItem.subcatalogoItem', 'cotizaciones.proveedor', 'cotizaciones.cotizacionDetalles'])
            ->orderBy(['numero' => SORT_ASC])
            ->all();
    }

    public function obtener(int $id): ?SolicitudCotizacion
    {
        return SolicitudCotizacion::findOne($id);
    }

    /**
     * Items de la lista que todavia no estan en ninguna solicitud.
     *
     * @return ProyectoItem[]
     */
    public function sinAsignar(Proyecto $proyecto): array
    {
        return $proyecto->getItems()
            ->andWhere(['not in', 'id', SolicitudCotizacionItem::find()->select('idProyectoItem')])
            ->with(['subcatalogoItem', 'categoria'])
            ->all();
    }

    /**
     * Pone los items marcados en una solicitud: una existente ($idSolicitud)
     * o una nueva con el nombre dado. Un item que estaba en otra solicitud
     * se cambia de solicitud (nunca queda en dos).
     *
     * Devuelve cuantos items se movieron, o null si no se pudo (el motivo
     * queda en $error).
     *
     * @param mixed $idsProyectoItem ids marcados en el formulario
     */
    public function mover(Proyecto $proyecto, $idsProyectoItem, ?int $idSolicitud, string $nombreNueva, ?string &$error = null): ?int
    {
        $error = null;

        if (!$this->puedeEditarse($proyecto)) {
            $error = 'Las solicitudes se arman con la lista enviada a cotización y el presupuesto sin cerrar.';

            return null;
        }

        $ids = $this->idsDeLaLista($proyecto, $idsProyectoItem);

        if ($ids === []) {
            $error = 'Marque al menos un ítem de la lista.';

            return null;
        }

        if ($this->contarEnSolicitudesCotizadas($ids) > 0) {
            $error = 'Algunos de los ítems marcados están en una solicitud que ya tiene cotizaciones registradas: no se pueden mover.';

            return null;
        }

        if ($idSolicitud === null) {
            $solicitud = new SolicitudCotizacion();
            $solicitud->nombre = $nombreNueva;

            if (!$solicitud->validate(['nombre'])) {
                $error = $solicitud->getFirstError('nombre');

                return null;
            }
        } else {
            $solicitud = SolicitudCotizacion::findOne(['id' => $idSolicitud, 'idProyecto' => $proyecto->id]);

            if ($solicitud === null) {
                $error = 'La solicitud elegida no es de este proyecto.';

                return null;
            }

            if ($this->tieneCotizaciones($solicitud)) {
                $error = $solicitud->codigoCorto() . ' ya tiene cotizaciones registradas: no se le agregan ítems. Póngalos en una solicitud nueva.';

                return null;
            }
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            if ($solicitud->isNewRecord) {
                $this->insertar($proyecto, $solicitud);
            }

            $this->asignar($solicitud, $ids);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return count($ids);
    }

    /**
     * Atajo: crea una solicitud por cada clasificacion que tenga items sin
     * asignar, con el nombre de la clasificacion, y les pone esos items.
     * Despues el usuario mueve lo que no corresponda.
     *
     * @return int cuantas solicitudes se crearon
     */
    public function crearPorClasificacion(Proyecto $proyecto): int
    {
        if (!$this->puedeEditarse($proyecto)) {
            return 0;
        }

        $grupos = [];

        foreach ($this->sinAsignar($proyecto) as $item) {
            $nombre = $item->categoria->nombre ?? 'Sin clasificación';
            $grupos[$nombre]['orden'] = $item->categoria->orden ?? 999;
            $grupos[$nombre]['ids'][] = (int) $item->id;
        }

        uasort($grupos, static fn ($a, $b) => $a['orden'] <=> $b['orden']);

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach ($grupos as $nombre => $grupo) {
                $solicitud = new SolicitudCotizacion();
                $solicitud->nombre = (string) $nombre;
                $this->insertar($proyecto, $solicitud);
                $this->asignar($solicitud, $grupo['ids']);
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return count($grupos);
    }

    /**
     * Devuelve el item a "sin asignar". Devuelve null si se hizo, o el motivo
     * por el que no.
     */
    public function quitarItem(Proyecto $proyecto, int $idProyectoItem): ?string
    {
        if (!$this->puedeEditarse($proyecto) || $this->idsDeLaLista($proyecto, [$idProyectoItem]) === []) {
            return 'No se pudo quitar el ítem: las solicitudes de este proyecto ya no se modifican.';
        }

        if ($this->contarEnSolicitudesCotizadas([$idProyectoItem]) > 0) {
            return 'No se puede quitar: su solicitud ya tiene cotizaciones registradas.';
        }

        SolicitudCotizacionItem::deleteAll(['idProyectoItem' => $idProyectoItem]);

        return null;
    }

    /**
     * Cambia el nombre (rubro) y la nota. false si no es editable o el
     * nombre no es valido (el error queda en $solicitud->errors).
     */
    public function actualizar(SolicitudCotizacion $solicitud, array $datosFormulario): bool
    {
        if (!$this->puedeEditarse($solicitud->proyecto)) {
            $solicitud->addError('nombre', 'Las solicitudes de este proyecto ya no se modifican.');

            return false;
        }

        return $solicitud->load($datosFormulario) && $solicitud->save();
    }

    /**
     * Borra la solicitud; sus items vuelven a "sin asignar" (la llave
     * foranea borra en cascada las filas de redes.SolicitudCotizacionItem).
     * Devuelve null si se borro, o el motivo por el que no.
     *
     * REGLA DE NEGOCIO: una solicitud con cotizaciones registradas no se
     * borra (son la respuesta de los proveedores a ese documento).
     */
    public function eliminar(SolicitudCotizacion $solicitud): ?string
    {
        if (!$this->puedeEditarse($solicitud->proyecto)) {
            return 'No se pudo eliminar: las solicitudes de este proyecto ya no se modifican.';
        }

        $cotizaciones = (int) $solicitud->getCotizaciones()->count();

        if ($cotizaciones > 0) {
            return "No se puede eliminar {$solicitud->codigoCorto()}: tiene $cotizaciones cotización(es) registrada(s). Elimine primero las cotizaciones.";
        }

        $solicitud->delete();

        return null;
    }

    /**
     * OJO: no usar $solicitud->getCotizaciones()->exists(). Esa relacion trae
     * ORDER BY, Yii la mete dentro de un EXISTS(...) y SQL Server no admite
     * ORDER BY en una subconsulta (error 42000).
     */
    public function tieneCotizaciones(SolicitudCotizacion $solicitud): bool
    {
        return Cotizacion::find()->andWhere(['idSolicitudCotizacion' => $solicitud->id])->exists();
    }

    /**
     * Cuantos de esos items estan en una solicitud que ya tiene cotizaciones.
     *
     * @param int[] $idsProyectoItem
     */
    private function contarEnSolicitudesCotizadas(array $idsProyectoItem): int
    {
        return (int) SolicitudCotizacionItem::find()
            ->andWhere(['idProyectoItem' => $idsProyectoItem])
            ->andWhere(['idSolicitud' => Cotizacion::find()->select('idSolicitudCotizacion')->andWhere(['not', ['idSolicitudCotizacion' => null]])])
            ->count();
    }

    /**
     * Guarda la marca y modelo de referencia de los items de la solicitud:
     *   referencias[idProyectoItem] => texto libre ('' = sin referencia)
     * Solo los items de ESTA solicitud; los ids que no son de ella se
     * ignoran. Devuelve null si se guardo, o el motivo por el que no.
     *
     * @param mixed $referencias lo que llega en POST "referencias"
     */
    public function guardarReferencias(SolicitudCotizacion $solicitud, $referencias): ?string
    {
        if (!$this->puedeEditarse($solicitud->proyecto)) {
            return 'Las solicitudes se modifican con la lista enviada a cotización y el presupuesto sin cerrar.';
        }

        if ($this->tieneCotizaciones($solicitud)) {
            return $solicitud->codigoCorto() . ' ya tiene cotizaciones registradas: la marca y modelo pedidos ya no se cambian.';
        }

        $referencias = is_array($referencias) ? $referencias : [];
        $filas = $solicitud->getItems()->with('proyectoItem.subcatalogoItem')->all();

        foreach ($filas as $fila) {
            $texto = $referencias[$fila->idProyectoItem] ?? null;

            if (!is_string($texto)) {
                continue;
            }

            $fila->marcaModeloReferencia = $texto;

            if (!$fila->validate(['marcaModeloReferencia'])) {
                return ($fila->proyectoItem->subcatalogoItem->descripcion ?? 'Ítem') . ': ' . $fila->getFirstError('marcaModeloReferencia');
            }
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach ($filas as $fila) {
                $fila->save(false, ['marcaModeloReferencia']);
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return null;
    }

    /**
     * "Modelo sugerido" de la especificacion enlazada a cada item del
     * proyecto que lo tenga: [idProyectoItem => texto]. La pantalla lo
     * muestra como sugerencia en el campo de referencia vacio; no se copia.
     *
     * @return array<int, string>
     */
    public function modelosSugeridos(Proyecto $proyecto): array
    {
        $sugeridos = [];
        $items = $proyecto->getItems()
            ->andWhere(['not', ['idEspecificacion' => null]])
            ->with('especificacion.versionActual')
            ->all();

        foreach ($items as $item) {
            $modelo = $item->especificacion->versionActual->modeloSugerido ?? null;
            if (!empty($modelo)) {
                $sugeridos[$item->id] = $modelo;
            }
        }

        return $sugeridos;
    }

    /**
     * Especificacion tecnica ENLAZADA a cada item de la solicitud (la que se
     * eligio en la lista del proyecto), para citarla en el documento:
     * [idProyectoItem => 'NET:012/26'], con el codigo de su formulario NET
     * vigente (o el titulo de la ficha si todavia no se emitio). Los items sin
     * especificacion no aparecen: es un caso normal y el documento sale igual.
     *
     * @return array<int, string>
     */
    public function especificacionesPorItem(SolicitudCotizacion $solicitud): array
    {
        $idsItems = array_map(static fn ($fila) => (int) $fila->idProyectoItem, $solicitud->items);

        if ($idsItems === []) {
            return [];
        }

        $items = ProyectoItem::find()
            ->andWhere(['id' => $idsItems])
            ->andWhere(['not', ['idEspecificacion' => null]])
            ->with(['especificacion', 'emisionVigente'])
            ->all();

        $referencias = [];

        foreach ($items as $item) {
            $referencia = $item->referenciaEspecificacion();
            if ($referencia !== null) {
                $referencias[$item->id] = $referencia;
            }
        }

        return $referencias;
    }

    /**
     * Guarda los textos fijos del documento de la solicitud (cabecera,
     * instrucciones y nota final).
     */
    public function guardarConfiguracion(ConfiguracionSolicitudCotizacion $configuracion, array $datosFormulario): bool
    {
        return $configuracion->load($datosFormulario) && $configuracion->save();
    }

    /**
     * Convierte el HTML de la vista "pdf" (la arma el controlador) en el
     * documento de la solicitud.
     *
     * @return array{contenido: string, nombreArchivo: string}
     */
    public function generarPdf(SolicitudCotizacion $solicitud, string $html): array
    {
        $referencia = ($solicitud->proyecto->codigo ?: (string) $solicitud->idProyecto) . '_' . $solicitud->codigoCorto();

        return [
            'contenido' => (new PresupuestoPdfGenerator())->generarDesdeHtml($html),
            'nombreArchivo' => 'solicitud_cotizacion_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $referencia) . '.pdf',
        ];
    }

    /**
     * Guarda la solicitud nueva con el siguiente numero del proyecto.
     */
    private function insertar(Proyecto $proyecto, SolicitudCotizacion $solicitud): void
    {
        $solicitud->idProyecto = $proyecto->id;
        $solicitud->numero = 1 + (int) SolicitudCotizacion::find()->andWhere(['idProyecto' => $proyecto->id])->max('numero');
        $solicitud->save(false);
    }

    /**
     * Deja esos items en la solicitud, sacandolos antes de donde estuvieran.
     *
     * @param int[] $idsProyectoItem ya verificados (son de la lista del proyecto)
     */
    private function asignar(SolicitudCotizacion $solicitud, array $idsProyectoItem): void
    {
        // La fila se borra y se vuelve a crear en la solicitud de destino: la
        // marca y modelo de referencia que ya tenia viaja con el item.
        $referencias = SolicitudCotizacionItem::find()
            ->select(['marcaModeloReferencia', 'idProyectoItem'])
            ->andWhere(['idProyectoItem' => $idsProyectoItem])
            ->indexBy('idProyectoItem')
            ->column();

        SolicitudCotizacionItem::deleteAll(['idProyectoItem' => $idsProyectoItem]);

        foreach ($idsProyectoItem as $idProyectoItem) {
            $fila = new SolicitudCotizacionItem();
            $fila->idSolicitud = $solicitud->id;
            $fila->idProyectoItem = $idProyectoItem;
            $fila->marcaModeloReferencia = $referencias[$idProyectoItem] ?? null;
            $fila->save(false);
        }
    }

    /**
     * De los ids enviados, solo los que son items de la lista de este
     * proyecto, sin repetidos.
     *
     * @param mixed $enviados
     * @return int[]
     */
    private function idsDeLaLista(Proyecto $proyecto, $enviados): array
    {
        $ids = array_filter((array) $enviados, static fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)));

        if ($ids === []) {
            return [];
        }

        return array_map('intval', ProyectoItem::find()
            ->select('id')
            ->andWhere(['idProyecto' => $proyecto->id, 'id' => array_values(array_unique($ids))])
            ->orderBy(['orden' => SORT_ASC])
            ->column());
    }
}
