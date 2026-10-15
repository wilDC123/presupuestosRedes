<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\PresupuestoPdfGenerator;
use backend\models\OrdenCambio;
use backend\models\Plantilla;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SolicitudCotizacionItem;
use backend\models\SubcatalogoItem;
use Throwable;
use Yii;

/**
 * Capa de Servicio para la LISTA DE ITEMS de un proyecto (redes.ProyectoItem):
 * solo items y cantidades, sin precios.
 *
 * Reglas de negocio que viven aqui:
 *  - la lista se inicia copiando una plantilla o en blanco,
 *  - solo se edita mientras esta "en edicion" y el proyecto no esta cerrado,
 *  - toda la lista se guarda junta (una transaccion), conservando el id de
 *    cada item (las fases siguientes apuntan a ese id),
 *  - un item que ya tiene linea en el presupuesto no se quita,
 *  - cada item puede tener COMO MAXIMO una especificacion de la libreria
 *    (opcional): solo se enlazan especificaciones activas; una que se
 *    desactivo despues se conserva donde ya estaba,
 *  - estados: edicion -> en_cotizacion -> cerrada (al aprobar el presupuesto).
 */
class ListaItemsService
{
    public function contar(Proyecto $proyecto): int
    {
        return (int) ProyectoItem::find()->andWhere(['idProyecto' => $proyecto->id])->count();
    }

    /**
     * REGLA DE NEGOCIO: la lista solo se modifica "en edicion" y mientras el
     * presupuesto del proyecto no este aprobado ni cancelado. Una lista ya
     * enviada a cotizacion se reabre con una orden de cambio.
     */
    public function puedeEditarse(Proyecto $proyecto): bool
    {
        $presupuesto = $proyecto->presupuesto;

        return $proyecto->estadoLista === Proyecto::LISTA_EDICION
            && ($presupuesto === null || $presupuesto->puedeEditarse());
    }

    /**
     * Items de la lista agrupados por clasificacion, en el orden de las
     * categorias: [idCategoria => ['nombre' => string, 'items' => ProyectoItem[]]].
     *
     * Si se pasan $items (los enviados en un formulario que no se pudo
     * guardar) agrupa esos en vez de los guardados.
     *
     * @param ProyectoItem[]|null $items
     * @return array<int, array{nombre: string, items: ProyectoItem[]}>
     */
    public function agrupar(Proyecto $proyecto, ?array $items = null): array
    {
        $items ??= $proyecto->getItems()->with(['subcatalogoItem', 'categoria', 'especificacion'])->all();

        $grupos = [];
        $orden = [];

        foreach ($items as $item) {
            $idCategoria = (int) $item->idCategoria;

            if (!isset($grupos[$idCategoria])) {
                $grupos[$idCategoria] = ['nombre' => $item->categoria->nombre ?? 'Sin clasificación', 'items' => []];
                $orden[$idCategoria] = $item->categoria->orden ?? 999;
            }

            $grupos[$idCategoria]['items'][] = $item;
        }

        uksort($grupos, static fn ($a, $b) => $orden[$a] <=> $orden[$b]);

        return $grupos;
    }

    /**
     * Guarda la lista completa tal como llega del formulario:
     *   items[n][id]             id del item ya guardado ('' si es nuevo)
     *   items[n][idSubcatalogo]  item del subcatalogo (solo cuenta en los nuevos)
     *   items[n][cantidad]
     *   items[n][observacion]
     *   items[n][idEspecificacion] especificacion de la libreria ('' = ninguna)
     *
     * Los items guardados que ya no vienen en el formulario se quitan. Los
     * que siguen se ACTUALIZAN (no se borran y recrean) para conservar su id.
     *
     * Devuelve false si la lista no es editable o si algo no es valido. Los
     * mensajes quedan en $errores y $enviados trae los items tal como
     * llegaron, para volver a mostrarlos.
     *
     * @param mixed $filas
     * @param string[] $errores (por referencia)
     * @param ProyectoItem[] $enviados (por referencia)
     */
    public function guardar(Proyecto $proyecto, $filas, array &$errores = [], array &$enviados = []): bool
    {
        $errores = [];
        $enviados = [];

        if (!$this->puedeEditarse($proyecto)) {
            $errores[] = 'La lista ya no está en edición: no se puede modificar.';

            return false;
        }

        /** @var ProyectoItem[] $guardados indexados por id */
        $guardados = ProyectoItem::find()->andWhere(['idProyecto' => $proyecto->id])->indexBy('id')->all();
        $conservados = [];
        $numero = 0;
        $especificacionesActivas = (new EspecificacionService())->idsActivas();

        foreach ((array) $filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }

            $numero++;
            $id = $this->texto($fila['id'] ?? '');

            if ($id !== '') {
                $item = $guardados[$id] ?? null;

                // Un id que no es de esta lista, o repetido en el formulario.
                if ($item === null || isset($conservados[$id])) {
                    $errores[] = "Ítem $numero: no pertenece a la lista de este proyecto.";
                    continue;
                }

                $conservados[$id] = true;
            } else {
                $item = $this->nuevoItem($proyecto, $this->texto($fila['idSubcatalogo'] ?? ''));

                if ($item === null) {
                    $errores[] = "Ítem $numero: no existe en el subcatálogo o está inactivo.";
                    continue;
                }
            }

            $item->cantidad = $this->texto($fila['cantidad'] ?? '');
            $item->observacion = $this->texto($fila['observacion'] ?? '');
            $enviados[] = $item;
            $nombre = $item->subcatalogoItem->descripcion ?? "Ítem $numero";

            $errorEspecificacion = $this->asignarEspecificacion($item, $this->texto($fila['idEspecificacion'] ?? ''), $especificacionesActivas);
            if ($errorEspecificacion !== null) {
                $errores[] = "$nombre: $errorEspecificacion";
            }

            if (!$item->validate(['cantidad', 'observacion'])) {
                $errores[] = "$nombre: " . implode(' ', $item->getFirstErrors());
            }
        }

        $quitados = array_diff_key($guardados, $conservados);

        foreach ($this->conLineaDePresupuesto($quitados) as $item) {
            $errores[] = 'No se puede quitar "' . ($item->subcatalogoItem->descripcion ?? '?') . '": ya tiene una línea en el presupuesto.';
            // Vuelve a la pantalla, para que no parezca que se quito.
            $enviados[] = $item;
        }

        if ($errores !== []) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            // Un item que se quita de la lista sale tambien de su solicitud
            // de cotizacion (esa llave foranea no borra en cascada).
            if ($quitados !== []) {
                SolicitudCotizacionItem::deleteAll(['idProyectoItem' => array_keys($quitados)]);
            }

            foreach ($quitados as $item) {
                $item->delete();
            }

            foreach ($enviados as $posicion => $item) {
                $item->orden = $posicion + 1;
                $item->save(false);
            }

            // Editar la lista a mano tambien resuelve el paso "Plantilla".
            $proyecto->updateAttributes(['listaIniciada' => true]);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Cuantos items de la lista tienen cantidad 0 (se avisa al enviar a
     * cotizacion; no bloquea: "mano de obra" suele ir en 0).
     */
    public function contarSinCantidad(Proyecto $proyecto): int
    {
        return (int) ProyectoItem::find()->andWhere(['idProyecto' => $proyecto->id, 'cantidad' => 0])->count();
    }

    /**
     * edicion -> en_cotizacion. Desde aqui la lista queda de solo lectura.
     * Cierra la orden de cambio que la habia reabierto, si habia una.
     *
     * Devuelve false si la lista no esta en edicion o no tiene items.
     */
    public function enviarACotizacion(Proyecto $proyecto): bool
    {
        if (!$this->puedeEditarse($proyecto) || $this->contar($proyecto) === 0) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $proyecto->updateAttributes(['estadoLista' => Proyecto::LISTA_EN_COTIZACION, 'listaIniciada' => true]);
            (new OrdenCambioService())->cerrarAbiertas($proyecto, OrdenCambio::ALCANCE_LISTA);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * La lista queda cerrada definitivamente. Lo llama PresupuestoService al
     * aprobar el presupuesto: un proyecto aprobado ya no se modifica.
     */
    public function cerrar(Proyecto $proyecto): void
    {
        $proyecto->updateAttributes(['estadoLista' => Proyecto::LISTA_CERRADA]);
        (new OrdenCambioService())->cerrarAbiertas($proyecto, OrdenCambio::ALCANCE_LISTA);
    }

    /**
     * Items de la lista que todavia no tienen linea en el presupuesto.
     *
     * @return ProyectoItem[]
     */
    public function pendientesDePresupuestar(Proyecto $proyecto): array
    {
        $presupuestados = PresupuestoDetalle::find()->select('idProyectoItem')->andWhere(['not', ['idProyectoItem' => null]]);

        return $proyecto->getItems()
            ->andWhere(['not in', 'id', $presupuestados])
            ->with(['subcatalogoItem', 'categoria', 'especificacion'])
            ->all();
    }

    /**
     * Ids de las especificaciones enlazadas en la lista (para que el
     * selector las siga ofreciendo aunque se hayan desactivado).
     *
     * @return int[]
     */
    public function especificacionesEnlazadas(Proyecto $proyecto): array
    {
        return array_map('intval', ProyectoItem::find()
            ->select('idEspecificacion')
            ->distinct()
            ->andWhere(['idProyecto' => $proyecto->id])
            ->andWhere(['not', ['idEspecificacion' => null]])
            ->column());
    }

    public function obtenerItem(int $id): ?ProyectoItem
    {
        return ProyectoItem::findOne($id);
    }

    /**
     * Convierte el HTML de la vista "pdf" (la arma el controlador) en el PDF
     * de la lista, con el mismo generador que usa el presupuesto.
     *
     * @return array{contenido: string, nombreArchivo: string}
     */
    public function generarPdf(Proyecto $proyecto, string $html): array
    {
        $referencia = $proyecto->codigo ?: (string) $proyecto->id;

        return [
            'contenido' => (new PresupuestoPdfGenerator())->generarDesdeHtml($html),
            'nombreArchivo' => 'lista_items_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $referencia) . '.pdf',
        ];
    }

    /**
     * COPIA los items de la plantilla a la lista del proyecto, con su
     * clasificacion, cantidad sugerida y observacion. No queda ningun
     * enlace entre la lista y la plantilla: si la plantilla cambia despues,
     * este proyecto no se entera.
     *
     * Si la lista ya tenia items, solo se agregan los items del subcatalogo
     * que la lista todavia no tiene; nunca se borra ni se duplica nada.
     *
     * @return int cuantos items se agregaron (0 tambien si la lista no es editable)
     */
    public function usarPlantilla(Proyecto $proyecto, Plantilla $plantilla): int
    {
        if (!$this->puedeEditarse($proyecto)) {
            return 0;
        }

        // Lo que la lista ya tenia ANTES de cargar. Dentro de la plantilla
        // un item si puede repetirse (patch cord de 1 m y de 3 m).
        $yaEnLista = ProyectoItem::find()
            ->select('idSubcatalogo')
            ->andWhere(['idProyecto' => $proyecto->id])
            ->column();

        $orden = (int) ProyectoItem::find()->andWhere(['idProyecto' => $proyecto->id])->max('orden');
        $agregados = 0;

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach ($plantilla->getItems()->with('subcatalogoItem')->all() as $itemPlantilla) {
                if (in_array($itemPlantilla->idSubcatalogo, $yaEnLista, false)) {
                    continue;
                }

                $item = new ProyectoItem();
                $item->idProyecto = $proyecto->id;
                $item->idSubcatalogo = $itemPlantilla->idSubcatalogo;
                $item->idCategoria = $itemPlantilla->idCategoria;
                $item->cantidad = $itemPlantilla->cantidadSugerida ?? 0;
                $item->unidadMedida = $itemPlantilla->subcatalogoItem->unidadDefecto ?? null;
                $item->observacion = $itemPlantilla->observacion;
                $item->orden = ++$orden;
                $item->save(false);
                $agregados++;
            }

            // updateAttributes() escribe solo estas columnas, sin pasar por
            // la validacion ni tocar el resto del proyecto.
            $proyecto->updateAttributes([
                'idPlantillaOrigen' => $plantilla->id,
                'listaIniciada' => true,
            ]);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return $agregados;
    }

    /**
     * "Omitir, empezar en blanco": el proyecto no usa plantilla y arma su
     * lista con items sueltos. Solo marca el paso como resuelto.
     */
    public function empezarEnBlanco(Proyecto $proyecto): void
    {
        $proyecto->updateAttributes(['listaIniciada' => true]);
    }

    /**
     * Item nuevo para la lista a partir de un item ACTIVO del subcatalogo
     * (null si no existe o esta inactivo). Toma del subcatalogo su
     * clasificacion y su unidad.
     */
    private function nuevoItem(Proyecto $proyecto, string $idSubcatalogo): ?ProyectoItem
    {
        if (!ctype_digit($idSubcatalogo)) {
            return null;
        }

        $origen = SubcatalogoItem::findOne(['id' => (int) $idSubcatalogo, 'activo' => true]);

        if ($origen === null) {
            return null;
        }

        $item = new ProyectoItem();
        $item->idProyecto = $proyecto->id;
        $item->idSubcatalogo = $origen->id;
        $item->idCategoria = $origen->idCategoria;
        $item->unidadMedida = $origen->unidadDefecto;
        // Deja el item del subcatalogo ya cargado en la relacion, para no
        // volver a consultarlo al mostrar su nombre.
        $item->populateRelation('subcatalogoItem', $origen);

        return $item;
    }

    /**
     * Asigna la especificacion elegida en el formulario ('' = ninguna).
     * Devuelve el mensaje de error, o null si es valida.
     *
     * REGLA DE NEGOCIO: solo se ENLAZAN especificaciones activas. Si la fila
     * ya tenia una que despues se desactivo, se conserva (el selector la
     * sigue mostrando) para no perderla al volver a guardar la lista.
     *
     * @param array<int, true> $activas Ver EspecificacionService::idsActivas()
     */
    private function asignarEspecificacion(ProyectoItem $item, string $valor, array $activas): ?string
    {
        if ($valor === '') {
            $item->idEspecificacion = null;

            return null;
        }

        if (!ctype_digit($valor)) {
            return 'la especificación elegida no es válida.';
        }

        $id = (int) $valor;
        $yaEnlazada = $id === (int) $item->getOldAttribute('idEspecificacion');

        if (!$yaEnlazada && !isset($activas[$id])) {
            return 'la especificación elegida no existe o está inactiva.';
        }

        $item->idEspecificacion = $id;

        return null;
    }

    /**
     * De los items dados, los que ya tienen linea en el presupuesto.
     *
     * @param ProyectoItem[] $items indexados por id
     * @return ProyectoItem[]
     */
    private function conLineaDePresupuesto(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $ids = PresupuestoDetalle::find()
            ->select('idProyectoItem')
            ->andWhere(['idProyectoItem' => array_keys($items)])
            ->column();

        return array_intersect_key($items, array_flip($ids));
    }

    /**
     * Garantiza un texto aunque en el formulario llegue un arreglo.
     *
     * @param mixed $valor
     */
    private function texto($valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }
}
