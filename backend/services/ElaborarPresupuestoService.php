<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CotizacionDetalle;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use Throwable;
use Yii;

/**
 * Capa de Servicio de la pantalla "Presupuesto" del proyecto: el
 * presupuesto como DOCUMENTO VIVO.
 *
 * Cada item de la lista es una linea del presupuesto. No se "agregan"
 * lineas: se ELIGE el precio de cada item entre lo que cotizaron los
 * proveedores (o un precio manual). Las reglas:
 *  - al entrar, cada item sin linea recibe el MENOR precio vigente,
 *  - elegir otro precio REEMPLAZA la linea por una nueva, con el precio y la
 *    partida congelados en ese momento (PresupuestoService::congelarPrecio()
 *    y congelarPartida()); el precio de una linea guardada nunca se
 *    recalcula,
 *  - elegir un precio que no es el menor exige una justificacion,
 *  - solo se modifica mientras el presupuesto es un borrador ("en proceso").
 */
class ElaborarPresupuestoService
{
    private PresupuestoService $presupuestos;

    public function __construct()
    {
        $this->presupuestos = new PresupuestoService();
    }

    /**
     * El presupuesto del proyecto (cada proyecto tiene uno solo). Si todavia
     * no tiene, lo crea en "en proceso": los datos de cabecera se piden
     * recien al enviarlo a revision.
     */
    public function delProyecto(Proyecto $proyecto): Presupuesto
    {
        if ($proyecto->presupuesto !== null) {
            return $proyecto->presupuesto;
        }

        $presupuesto = new Presupuesto();
        $presupuesto->idProyecto = $proyecto->id;
        $presupuesto->estado = Presupuesto::ESTADO_EN_PROCESO;
        $presupuesto->save(false);
        $proyecto->populateRelation('presupuesto', $presupuesto);

        return $presupuesto;
    }

    /**
     * Da el menor precio vigente a los items que todavia no tienen linea
     * (recien entra el presupuesto, o items nuevos de una orden de cambio).
     * Los que no tienen ninguna cotizacion quedan "sin precio". Devuelve
     * cuantas lineas creo.
     */
    public function completarSinLinea(Presupuesto $presupuesto): int
    {
        if (!$presupuesto->esBorrador()) {
            return 0;
        }

        $sinLinea = (new ListaItemsService())->pendientesDePresupuestar($presupuesto->proyecto);
        $opciones = $this->opcionesPorItem($sinLinea);
        $creadas = 0;

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach ($sinLinea as $item) {
                $menor = $this->menor($opciones[$item->id]);

                if ($menor !== null && $this->reemplazarLinea($presupuesto, $item, (int) $menor->id, null, null) === null) {
                    $creadas++;
                }
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return $creadas;
    }

    /**
     * Todo lo que dibuja la pantalla: una columna por proveedor (los que
     * cotizaron algun item de la lista) y, por clasificacion, una fila por
     * item con el precio de cada proveedor, cual es el menor y la linea
     * elegida.
     *
     * @return array{
     *   proveedores: array<int, string>,
     *   grupos: array<int, array{nombre: string, subtotal: float, filas: array<int, array{
     *     item: ProyectoItem, celdas: array<int, CotizacionDetalle>, menor: int|null, linea: PresupuestoDetalle|null
     *   }>}>,
     *   total: float, conPrecio: int, totalFilas: int
     * }
     */
    public function tabla(Presupuesto $presupuesto): array
    {
        $listaItems = new ListaItemsService();
        $items = $presupuesto->proyecto->getItems()->with(['subcatalogoItem', 'categoria', 'especificacion'])->all();
        $opciones = $this->opcionesPorItem($items);
        $lineas = $presupuesto->getPresupuestoDetalles()
            ->andWhere(['not', ['idProyectoItem' => null]])
            ->with('cotizacionDetalle.cotizacion.proveedor')
            ->indexBy('idProyectoItem')
            ->all();

        $proveedores = [];
        $grupos = [];
        $total = 0.0;
        $conPrecio = 0;

        foreach ($listaItems->agrupar($presupuesto->proyecto, $items) as $idCategoria => $grupo) {
            $filas = [];
            $subtotal = 0.0;

            foreach ($grupo['items'] as $item) {
                // Por proveedor, su precio mas bajo para esta fila (las
                // opciones ya vienen ordenadas por precio).
                $celdas = [];
                foreach ($opciones[$item->id] as $detalle) {
                    $idProveedor = (int) ($detalle->cotizacion->idProveedor ?? 0);
                    $celdas[$idProveedor] ??= $detalle;
                    $proveedores[$idProveedor] ??= $detalle->cotizacion->proveedor->razonSocial ?? '?';
                }

                $menor = $this->menor($opciones[$item->id]);
                $linea = $lineas[$item->id] ?? null;

                if ($linea !== null) {
                    $subtotal += (float) $linea->precioTotal;
                    $conPrecio++;
                }

                $filas[] = [
                    'item' => $item,
                    'celdas' => $celdas,
                    'menor' => $menor === null ? null : (int) $menor->id,
                    'linea' => $linea,
                ];
            }

            $grupos[$idCategoria] = ['nombre' => $grupo['nombre'], 'subtotal' => $subtotal, 'filas' => $filas];
            $total += $subtotal;
        }

        asort($proveedores, SORT_STRING | SORT_FLAG_CASE);

        return [
            'proveedores' => $proveedores,
            'grupos' => $grupos,
            'total' => $total,
            'conPrecio' => $conPrecio,
            'totalFilas' => count($items),
        ];
    }

    /**
     * Elige el precio de un item:
     *   $opcion  idCotizacionDetalle de una cotizacion de ESTA fila, o 'manual'
     *   $precio  solo con 'manual'
     *   $justificacion  obligatoria si lo elegido no es el menor precio
     *
     * Devuelve null si se guardo, o el mensaje de por que no.
     */
    public function elegir(Presupuesto $presupuesto, int $idProyectoItem, string $opcion, string $precio = '', string $justificacion = ''): ?string
    {
        if (!$presupuesto->esBorrador()) {
            return 'El presupuesto está "' . $presupuesto->estadoLabel() . '" y ya no se modifica.';
        }

        $item = ProyectoItem::find()
            ->andWhere(['id' => $idProyectoItem, 'idProyecto' => $presupuesto->idProyecto])
            ->with('subcatalogoItem')
            ->one();

        if ($item === null) {
            return 'El ítem no pertenece a la lista de este proyecto.';
        }

        $opciones = $this->opcionesPorItem([$item])[$item->id];
        $menor = $this->menor($opciones);
        $precio = trim($precio);
        $justificacion = trim($justificacion);

        if ($opcion === 'manual') {
            if ($precio === '' || !is_numeric($precio) || (float) $precio < 0) {
                return 'Escriba un precio manual mayor o igual a 0.';
            }

            $idCotizacionDetalle = null;
        } else {
            // REGLA DE NEGOCIO: solo un precio cotizado para ESTA fila (el
            // navegador podria enviar cualquier id).
            $permitidos = array_map(static fn (CotizacionDetalle $d) => (int) $d->id, $opciones);

            if (!ctype_digit($opcion) || !in_array((int) $opcion, $permitidos, true)) {
                return 'La cotización elegida no corresponde a este ítem.';
            }

            $idCotizacionDetalle = (int) $opcion;
        }

        $esMenor = $menor !== null && $idCotizacionDetalle === (int) $menor->id;

        if ($menor !== null && !$esMenor && $justificacion === '') {
            return 'Justifique por qué no se eligió el menor precio ('
                . ($menor->cotizacion->proveedor->razonSocial ?? '?') . ' — '
                . Yii::$app->formatter->asDecimal($menor->precioUnitario, 2) . ').';
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $error = $this->reemplazarLinea(
                $presupuesto,
                $item,
                $idCotizacionDetalle,
                $idCotizacionDetalle === null ? $precio : null,
                $esMenor || $menor === null ? null : $justificacion
            );

            if ($error === null) {
                $transaccion->commit();
            } else {
                $transaccion->rollBack();
            }
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return $error;
    }

    /**
     * Atajos de adjudicacion, para todos los items a la vez:
     *  - 'menor': el menor precio vigente de cada item,
     *  - 'proveedor': todo a $idProveedor, en los items que ese proveedor
     *    cotizo (los demas se quedan como estaban). Donde su precio no es el
     *    menor, la linea lleva $justificacion, que entonces es obligatoria.
     *
     * Devuelve null si se aplico, o el mensaje de por que no. $cambiadas y
     * $sinCotizar (por referencia) son para el mensaje de la pantalla.
     */
    public function adjudicar(Presupuesto $presupuesto, string $modo, ?int $idProveedor, string $justificacion, int &$cambiadas = 0, int &$sinCotizar = 0): ?string
    {
        if (!$presupuesto->esBorrador()) {
            return 'El presupuesto está "' . $presupuesto->estadoLabel() . '" y ya no se modifica.';
        }

        if (!in_array($modo, ['menor', 'proveedor'], true) || ($modo === 'proveedor' && $idProveedor === null)) {
            return 'Elija cómo adjudicar.';
        }

        $items = $presupuesto->proyecto->getItems()->with('subcatalogoItem')->all();
        $opciones = $this->opcionesPorItem($items);
        $justificacion = trim($justificacion);
        $elecciones = [];
        $cambiadas = 0;
        $sinCotizar = 0;

        foreach ($items as $item) {
            $menor = $this->menor($opciones[$item->id]);
            $elegida = $menor;

            if ($modo === 'proveedor') {
                $elegida = null;
                foreach ($opciones[$item->id] as $detalle) {
                    if ((int) ($detalle->cotizacion->idProveedor ?? 0) === $idProveedor) {
                        $elegida = $detalle;
                        break;
                    }
                }

                if ($elegida === null) {
                    $sinCotizar++;
                    continue;
                }
            }

            if ($elegida === null) {
                continue;
            }

            $esMenor = (int) $elegida->id === (int) $menor->id;

            if (!$esMenor && $justificacion === '') {
                return 'Escriba la justificación: el proveedor elegido no tiene el menor precio en todos los ítems.';
            }

            $elecciones[] = [$item, (int) $elegida->id, $esMenor ? null : $justificacion];
        }

        if ($elecciones === []) {
            return $modo === 'proveedor' ? 'Ese proveedor no cotizó ningún ítem de la lista.' : 'Ningún ítem de la lista tiene cotizaciones.';
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach ($elecciones as [$item, $idCotizacionDetalle, $justificacionLinea]) {
                $antes = PresupuestoDetalle::find()
                    ->select('idCotizacionDetalle')
                    ->andWhere(['idPresupuesto' => $presupuesto->id, 'idProyectoItem' => $item->id])
                    ->scalar();

                $error = $this->reemplazarLinea($presupuesto, $item, $idCotizacionDetalle, null, $justificacionLinea);

                if ($error !== null) {
                    $transaccion->rollBack();

                    return ($item->subcatalogoItem->descripcion ?? 'Ítem') . ': ' . $error;
                }

                if ((int) $antes !== $idCotizacionDetalle) {
                    $cambiadas++;
                }
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return null;
    }

    /**
     * Lo que se revisa antes de enviar a revision:
     *  - sinPrecio: items de la lista sin linea (BLOQUEAN el envio),
     *  - vencidas: lineas con precio de una cotizacion vencida (solo aviso).
     *
     * @return array{sinPrecio: string[], vencidas: int}
     */
    public function control(Presupuesto $presupuesto): array
    {
        $sinPrecio = array_map(
            static fn (ProyectoItem $item) => $item->subcatalogoItem->descripcion ?? 'Ítem no disponible',
            (new ListaItemsService())->pendientesDePresupuestar($presupuesto->proyecto)
        );

        return [
            'sinPrecio' => $sinPrecio,
            'vencidas' => count($this->presupuestos->lineasConCotizacionVencida($presupuesto)),
        ];
    }

    /**
     * Precios que puede usar cada item, del menor al mayor: los cotizados
     * para esa fila de la lista o, si no tiene ninguno, las cotizaciones
     * sueltas de su item del subcatalogo. Dos consultas en total, no una por
     * item.
     *
     * @param ProyectoItem[] $items
     * @return array<int, CotizacionDetalle[]> [idProyectoItem => opciones] (con todos los items, aunque sea [])
     */
    private function opcionesPorItem(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $consulta = static fn () => CotizacionDetalle::find()
            ->with('cotizacion.proveedor')
            ->orderBy(['precioUnitario' => SORT_ASC, 'id' => SORT_ASC]);

        $porItem = [];
        foreach ($consulta()->andWhere(['idProyectoItem' => array_map(static fn (ProyectoItem $i) => $i->id, $items)])->all() as $detalle) {
            $porItem[(int) $detalle->idProyectoItem][] = $detalle;
        }

        $porSubcatalogo = [];
        $sueltas = $consulta()
            ->andWhere(['idProyectoItem' => null])
            ->andWhere(['idSubcatalogo' => array_values(array_unique(array_map(static fn (ProyectoItem $i) => (int) $i->idSubcatalogo, $items)))])
            ->all();
        foreach ($sueltas as $detalle) {
            $porSubcatalogo[(int) $detalle->idSubcatalogo][] = $detalle;
        }

        $opciones = [];
        foreach ($items as $item) {
            $opciones[$item->id] = $porItem[$item->id] ?? $porSubcatalogo[(int) $item->idSubcatalogo] ?? [];
        }

        return $opciones;
    }

    /**
     * El menor precio VIGENTE de las opciones (ordenadas por precio). Si
     * todas estan vencidas, la mas barata igual: es lo mejor que hay.
     *
     * @param CotizacionDetalle[] $opciones
     */
    private function menor(array $opciones): ?CotizacionDetalle
    {
        foreach ($opciones as $detalle) {
            if ($detalle->cotizacion === null || !$detalle->cotizacion->estaVencida()) {
                return $detalle;
            }
        }

        return $opciones[0] ?? null;
    }

    /**
     * REGLA DE NEGOCIO CRITICA -- elegir otro precio REEMPLAZA la linea: se
     * borra la anterior y se crea una nueva con el precio y la partida
     * congelados ahora. El precio de una linea guardada nunca se modifica.
     * Si se vuelve a elegir lo mismo, solo cambia la justificacion (no se
     * vuelve a congelar el precio).
     *
     * No abre transaccion: la abre quien la llama, para poder reemplazar
     * varias lineas juntas. Devuelve null o el mensaje de error.
     */
    private function reemplazarLinea(Presupuesto $presupuesto, ProyectoItem $item, ?int $idCotizacionDetalle, ?string $precioManual, ?string $justificacion): ?string
    {
        $actual = PresupuestoDetalle::findOne(['idPresupuesto' => $presupuesto->id, 'idProyectoItem' => $item->id]);

        if ($actual !== null) {
            $mismaCotizacion = $idCotizacionDetalle !== null && (int) $actual->idCotizacionDetalle === $idCotizacionDetalle;
            $mismoManual = $idCotizacionDetalle === null && $actual->idCotizacionDetalle === null
                && (float) $actual->precioUnitario === (float) $precioManual;

            if ($mismaCotizacion || $mismoManual) {
                $actual->updateAttributes(['justificacion' => $justificacion]);

                return null;
            }
        }

        $linea = new PresupuestoDetalle();
        $linea->idPresupuesto = $presupuesto->id;
        $linea->idProyectoItem = $item->id;
        $linea->idSubcatalogo = $item->idSubcatalogo;
        $linea->cantidad = $item->cantidad;
        $linea->unidadMedida = $item->unidadMedida;
        $linea->observacion = $item->observacion;
        $linea->idCotizacionDetalle = $idCotizacionDetalle;
        $linea->precioUnitario = $precioManual;
        $linea->justificacion = $justificacion;
        // Para congelarPartida(), sin volver a consultar el item.
        $linea->populateRelation('subcatalogoItem', $item->subcatalogoItem);

        $this->presupuestos->congelarPrecio($linea);
        $this->presupuestos->congelarPartida($linea);

        // La unidad no se vuelve a validar contra el catalogo: viene de la
        // lista, donde ya quedo congelada.
        if (!$linea->validate(array_diff($linea->activeAttributes(), ['unidadMedida']))) {
            return implode(' ', $linea->getErrorSummary(true));
        }

        // Primero se borra la anterior: el indice unico no deja dos lineas
        // del mismo item, ni siquiera por un instante.
        $actual?->delete();
        $linea->save(false);

        return null;
    }
}
