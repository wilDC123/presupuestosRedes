<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\PresupuestoPdfGenerator;
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
 *  - las cantidades no se copian: se leen de la lista.
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
            ->with(['items.proyectoItem.subcatalogoItem'])
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
     * Devuelve el item a "sin asignar". false si no se puede editar.
     */
    public function quitarItem(Proyecto $proyecto, int $idProyectoItem): bool
    {
        if (!$this->puedeEditarse($proyecto) || $this->idsDeLaLista($proyecto, [$idProyectoItem]) === []) {
            return false;
        }

        SolicitudCotizacionItem::deleteAll(['idProyectoItem' => $idProyectoItem]);

        return true;
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
     */
    public function eliminar(SolicitudCotizacion $solicitud): bool
    {
        if (!$this->puedeEditarse($solicitud->proyecto)) {
            return false;
        }

        return $solicitud->delete() !== false;
    }

    /**
     * Especificacion tecnica ENLAZADA a cada item de la solicitud (la que se
     * eligio en la lista del proyecto), para citarla en el documento:
     * [idProyectoItem => 'NET:012/26']. Los items sin especificacion no
     * aparecen: es un caso normal y el documento sale igual.
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
            ->with('especificacion')
            ->all();

        $referencias = [];

        foreach ($items as $item) {
            if ($item->especificacion !== null) {
                $referencias[$item->id] = $item->especificacion->referencia();
            }
        }

        return $referencias;
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
        SolicitudCotizacionItem::deleteAll(['idProyectoItem' => $idsProyectoItem]);

        foreach ($idsProyectoItem as $idProyectoItem) {
            $fila = new SolicitudCotizacionItem();
            $fila->idSolicitud = $solicitud->id;
            $fila->idProyectoItem = $idProyectoItem;
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
