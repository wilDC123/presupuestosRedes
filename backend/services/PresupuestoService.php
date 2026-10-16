<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\PresupuestoPdfGenerator;
use backend\models\CotizacionDetalle;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use Throwable;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * Capa de Servicio para Presupuesto y sus lineas (PresupuestoDetalle).
 *
 * Es el servicio con mas reglas de negocio del sistema:
 *  - que presupuestos se pueden editar (aprobado/cancelado NO),
 *  - que cambios de estado son validos,
 *  - el precio unitario "congelado" al agregar una linea,
 *  - la partida presupuestaria "congelada" al agregar una linea,
 *  - cada linea nace de un item de la LISTA del proyecto, una sola vez,
 *  - "Armar presupuesto": todas las lineas de una vez, con el precio que
 *    cada proveedor cotizo para ESA fila de la lista (el menor, marcado),
 *  - que la cotizacion elegida para una linea sea del mismo item,
 *  - que lineas usan una cotizacion vencida (se avisa, no se bloquea),
 *  - la agrupacion por categoria con subtotales y total general,
 *  - el resumen por partida presupuestaria (lo que consolida la parte financiera).
 */
class PresupuestoService
{
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Presupuesto::find()->with('proyecto')->orderBy(['id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }

    public function obtener(int $id): ?Presupuesto
    {
        return Presupuesto::findOne($id);
    }

    public function crear(Presupuesto $presupuesto, array $datosFormulario): bool
    {
        return $presupuesto->load($datosFormulario) && $presupuesto->save();
    }

    /**
     * Rechaza (devuelve false sin tocar nada) si el presupuesto no es editable.
     */
    public function actualizar(Presupuesto $presupuesto, array $datosFormulario): bool
    {
        if (!$this->puedeEditarse($presupuesto)) {
            return false;
        }

        return $presupuesto->load($datosFormulario) && $presupuesto->save();
    }

    public function eliminar(Presupuesto $presupuesto): bool
    {
        return $presupuesto->delete() !== false;
    }

    /**
     * REGLA DE NEGOCIO CRITICA: un presupuesto 'aprobado' o 'cancelado' no se
     * edita directamente; una ampliacion se registra como un proyecto nuevo
     * de fase siguiente (ProyectoService::nuevo()).
     *
     * La condicion en si esta en Presupuesto::puedeEditarse() porque las
     * vistas (view.php, detalle.php) la consultan para mostrar u ocultar
     * botones. Los controladores la consultan SIEMPRE por aqui.
     */
    public function puedeEditarse(Presupuesto $presupuesto): bool
    {
        return $presupuesto->puedeEditarse();
    }

    /**
     * Valida la transicion contra Presupuesto::transicionesPermitidas() (la
     * unica fuente de verdad del ciclo de vida, tambien usada por la vista
     * para dibujar los botones de estado).
     */
    public function puedeTransicionarA(Presupuesto $presupuesto, string $nuevoEstado): bool
    {
        return in_array($nuevoEstado, Presupuesto::transicionesPermitidas()[$presupuesto->estado] ?? [], true);
    }

    /**
     * Cambia el estado solo si la transicion es valida. Si no lo es, devuelve
     * false sin tocar la base de datos.
     */
    public function cambiarEstado(Presupuesto $presupuesto, string $nuevoEstado): bool
    {
        if (!$this->puedeTransicionarA($presupuesto, $nuevoEstado)) {
            return false;
        }

        $presupuesto->estado = $nuevoEstado;

        if ($nuevoEstado !== Presupuesto::ESTADO_APROBADO) {
            return $presupuesto->save(false);
        }

        // Al aprobar, la lista de items del proyecto queda cerrada: las dos
        // cosas juntas (una transaccion).
        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $presupuesto->save(false);
            (new ListaItemsService())->cerrar($presupuesto->proyecto);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Linea para el formulario "Presupuestar item", ya asociada al
     * presupuesto. Si llega un item de la lista del proyecto que aun no se
     * presupuesto, la linea viene con su item, cantidad, unidad y
     * observacion; si no, viene vacia (y no se puede guardar: ver agregarLinea()).
     */
    public function nuevaLinea(Presupuesto $presupuesto, ?int $idProyectoItem = null): PresupuestoDetalle
    {
        $linea = new PresupuestoDetalle();
        $linea->idPresupuesto = $presupuesto->id;
        $linea->idProyectoItem = $idProyectoItem;

        $itemLista = $this->itemDeListaSinPresupuestar($presupuesto, $linea);

        if ($itemLista === null) {
            $linea->idProyectoItem = null;

            return $linea;
        }

        $linea->idSubcatalogo = $itemLista->idSubcatalogo;
        $linea->cantidad = $itemLista->cantidad;
        $linea->unidadMedida = $itemLista->unidadMedida;
        $linea->observacion = $itemLista->observacion;

        return $linea;
    }

    /**
     * Cotizaciones que puede usar una linea de ese item del subcatalogo,
     * como [idCotizacionDetalle => "Proveedor — precio (marca) [especificacion]"].
     *
     * Si la linea viene de un item de la lista, primero van los precios
     * cotizados para ESA fila y despues las cotizaciones sueltas (las que no
     * salen de una solicitud). Los precios cotizados para OTRA fila de la
     * lista no se ofrecen: con el mismo item dos veces en la lista (cable de
     * 2 cajas y de 1 caja) seria facil cruzarlos.
     *
     * @return array<int, string>
     */
    public function opcionesCotizacion(int $idSubcatalogo, ?int $idProyectoItem = null): array
    {
        $query = CotizacionDetalle::find()
            ->andWhere(['idSubcatalogo' => $idSubcatalogo])
            ->with(['cotizacion.proveedor', 'especificacion']);

        if ($idProyectoItem !== null) {
            $query->andWhere(['or', ['idProyectoItem' => $idProyectoItem], ['idProyectoItem' => null]])
                // SQL Server ordena los NULL primero: DESC deja la fila arriba.
                ->orderBy(['idProyectoItem' => SORT_DESC, 'precioUnitario' => SORT_ASC]);
        }

        $opciones = [];

        foreach ($query->all() as $detalle) {
            $texto = ($detalle->cotizacion->proveedor->razonSocial ?? '?')
                . ' — ' . Yii::$app->formatter->asDecimal($detalle->precioUnitario, 2);

            if ($idProyectoItem !== null && $detalle->idProyectoItem === null) {
                $texto .= ' (cotización suelta)';
            }

            if ($detalle->marcaModelo) {
                $texto .= " ({$detalle->marcaModelo})";
            }

            if (!empty($detalle->especificacion->titulo)) {
                $texto .= " [{$detalle->especificacion->titulo}]";
            }

            if ($detalle->cotizacion !== null && $detalle->cotizacion->estaVencida()) {
                $texto .= ' — VENCIDA';
            }

            $opciones[$detalle->id] = $texto;
        }

        return $opciones;
    }

    /**
     * Agrega una linea al presupuesto, congelando su precio unitario y su
     * partida presupuestaria. Devuelve
     * false si el presupuesto no es editable, si no llegaron datos, o si la
     * validacion fallo (los errores quedan en $linea->errors).
     */
    public function agregarLinea(Presupuesto $presupuesto, PresupuestoDetalle $linea, array $datosFormulario): bool
    {
        if (!$this->puedeEditarse($presupuesto) || !$linea->load($datosFormulario)) {
            return false;
        }

        // Se fuerza despues de load() para que el formulario no pueda
        // "mover" la linea a otro presupuesto.
        $linea->idPresupuesto = $presupuesto->id;

        // REGLA DE NEGOCIO: el presupuesto nace de la lista de items. Cada
        // linea corresponde a un item de la lista de ESTE proyecto que
        // todavia no se presupuesto, y su item del subcatalogo es el de la
        // lista (no se toma del formulario).
        $itemLista = $this->itemDeListaSinPresupuestar($presupuesto, $linea);

        if ($itemLista === null) {
            $linea->addError('idProyectoItem', 'Elija un ítem de la lista del proyecto que todavía no esté presupuestado.');

            return false;
        }

        $linea->idSubcatalogo = $itemLista->idSubcatalogo;

        if (!$linea->validate()) {
            return false;
        }

        if (!$this->cotizacionCorrespondeAlItem($linea)) {
            // addError() deja el mensaje en el campo, igual que un error de
            // rules(): la vista lo muestra debajo del desplegable.
            $linea->addError('idCotizacionDetalle', 'La cotización elegida es de otro ítem. Elija una cotización de este mismo ítem de la lista o ingrese el precio manualmente.');

            return false;
        }

        $this->congelarPrecio($linea);
        $this->congelarPartida($linea);

        return $linea->save(false);
    }

    /**
     * Tabla de "Armar presupuesto": los items de la lista que todavia no
     * tienen linea, agrupados por clasificacion, cada uno con los precios
     * que puede usar:
     *  - los cotizados para ESA fila de la lista (desde una solicitud),
     *  - si no tiene ninguno, las cotizaciones sueltas de su mismo item del
     *    subcatalogo (las que no salen de ninguna solicitud).
     * 'menor' es el idCotizacionDetalle con el menor precio unitario: la
     * opcion que la pantalla marca por defecto.
     *
     * @return array<int, array{nombre: string, filas: array<int, array{item: ProyectoItem, opciones: CotizacionDetalle[], menor: int|null}>}>
     */
    public function filasParaArmar(Proyecto $proyecto): array
    {
        $listaItems = new ListaItemsService();
        $pendientes = $listaItems->pendientesDePresupuestar($proyecto);
        $opciones = $this->opcionesParaArmar($pendientes);
        $grupos = [];

        foreach ($listaItems->agrupar($proyecto, $pendientes) as $idCategoria => $grupo) {
            $filas = [];

            foreach ($grupo['items'] as $item) {
                $opcionesItem = $opciones[$item->id] ?? [];
                $filas[] = [
                    'item' => $item,
                    'opciones' => $opcionesItem,
                    // Ya vienen ordenadas por precio: la primera es la menor.
                    'menor' => $opcionesItem === [] ? null : (int) $opcionesItem[0]->id,
                ];
            }

            $grupos[$idCategoria] = ['nombre' => $grupo['nombre'], 'filas' => $filas];
        }

        return $grupos;
    }

    /**
     * Guarda de una vez las lineas elegidas en "Armar presupuesto":
     *   lineas[idProyectoItem][opcion]  idCotizacionDetalle, 'manual' o '' (pendiente)
     *   lineas[idProyectoItem][precio]  solo con 'manual'
     *
     * Usa el presupuesto del proyecto si ya tiene uno (cada proyecto tiene
     * uno solo); si no, lo crea en "en proceso", sin el formulario previo:
     * numero, oficio y area se completan despues con "Editar".
     *
     * Un item con 'manual' y sin precio, o con '', queda pendiente (no es un
     * error). Todo va en UNA transaccion: si una fila no es valida no se
     * guarda ninguna (ni el presupuesto nuevo). Devuelve el presupuesto, o
     * null con los mensajes en $errores ([idProyectoItem => mensaje], o
     * [0 => mensaje] si el error es general).
     *
     * @param mixed $elecciones lo que llega en POST "lineas"
     * @param array<int, string> $errores (por referencia)
     * @param int $agregadas cuantas lineas se guardaron (por referencia)
     */
    public function armar(Proyecto $proyecto, $elecciones, array &$errores = [], int &$agregadas = 0): ?Presupuesto
    {
        $elecciones = is_array($elecciones) ? $elecciones : [];
        $presupuesto = $proyecto->presupuesto;

        if ($presupuesto !== null && !$this->puedeEditarse($presupuesto)) {
            $errores[0] = 'El presupuesto está "' . $presupuesto->estadoLabel() . '" y ya no se le agregan líneas.';

            return null;
        }

        $pendientes = (new ListaItemsService())->pendientesDePresupuestar($proyecto);
        $opciones = $this->opcionesParaArmar($pendientes);
        $lineas = [];

        foreach ($pendientes as $item) {
            $eleccion = $elecciones[$item->id] ?? [];
            $opcion = is_array($eleccion) ? trim((string) ($eleccion['opcion'] ?? '')) : '';
            $precio = is_array($eleccion) ? trim((string) ($eleccion['precio'] ?? '')) : '';

            if ($opcion === '' || ($opcion === 'manual' && $precio === '')) {
                continue;
            }

            $linea = new PresupuestoDetalle();
            $linea->idProyectoItem = $item->id;
            $linea->idSubcatalogo = $item->idSubcatalogo;
            $linea->cantidad = $item->cantidad;
            $linea->unidadMedida = $item->unidadMedida;
            $linea->observacion = $item->observacion;
            // Para congelarPartida(), sin volver a consultar el item.
            $linea->populateRelation('subcatalogoItem', $item->subcatalogoItem);

            if ($opcion === 'manual') {
                if (!is_numeric($precio) || (float) $precio < 0) {
                    $errores[$item->id] = 'El precio manual debe ser un número mayor o igual a 0.';
                    continue;
                }

                $linea->precioUnitario = $precio;
            } else {
                // REGLA DE NEGOCIO: solo un precio ofrecido para ESTA fila
                // (el formulario podria enviar cualquier id).
                $permitidos = array_map(static fn (CotizacionDetalle $d) => (int) $d->id, $opciones[$item->id] ?? []);

                if (!ctype_digit($opcion) || !in_array((int) $opcion, $permitidos, true)) {
                    $errores[$item->id] = 'La cotización elegida no corresponde a este ítem.';
                    continue;
                }

                $linea->idCotizacionDetalle = (int) $opcion;
            }

            $lineas[] = $linea;
        }

        if ($errores !== []) {
            return null;
        }

        if ($lineas === []) {
            $errores[0] = 'No eligió ningún precio. Marque una cotización o escriba un precio manual en al menos un ítem.';

            return null;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            if ($presupuesto === null) {
                $presupuesto = new Presupuesto();
                $presupuesto->idProyecto = $proyecto->id;
                $presupuesto->estado = Presupuesto::ESTADO_EN_PROCESO;

                if (!$presupuesto->save()) {
                    $transaccion->rollBack();
                    $errores[0] = implode(' ', $presupuesto->getErrorSummary(true));

                    return null;
                }
            }

            foreach ($lineas as $linea) {
                $linea->idPresupuesto = $presupuesto->id;
                $this->congelarPrecio($linea);
                $this->congelarPartida($linea);

                // La unidad no se vuelve a validar contra el catalogo: viene
                // de la lista, donde ya quedo congelada (aunque despues se
                // haya desactivado esa unidad).
                if (!$linea->validate(array_diff($linea->activeAttributes(), ['unidadMedida']))) {
                    $transaccion->rollBack();
                    $errores[(int) $linea->idProyectoItem] = implode(' ', $linea->getErrorSummary(true));

                    return null;
                }

                $linea->save(false);
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        $agregadas = count($lineas);

        return $presupuesto;
    }

    /**
     * true si la linea tomo su precio de una cotizacion que ya esta vencida
     * (ver Cotizacion::estaVencida()). Es solo informativo: una cotizacion
     * vencida NO impide agregar la linea; el controlador muestra un aviso.
     */
    public function lineaUsaCotizacionVencida(PresupuestoDetalle $linea): bool
    {
        if (empty($linea->idCotizacionDetalle)) {
            return false;
        }

        $cotizacion = $linea->cotizacionDetalle->cotizacion ?? null;

        return $cotizacion !== null && $cotizacion->estaVencida();
    }

    /**
     * Lineas del presupuesto cuyo precio viene de una cotizacion vencida.
     * Se usa al aprobar, para advertir (sin bloquear) que hay precios que el
     * proveedor ya no esta obligado a mantener.
     *
     * @return PresupuestoDetalle[]
     */
    public function lineasConCotizacionVencida(Presupuesto $presupuesto): array
    {
        $lineas = $presupuesto->getPresupuestoDetalles()
            ->andWhere(['not', ['idCotizacionDetalle' => null]])
            ->with(['subcatalogoItem', 'cotizacionDetalle.cotizacion'])
            ->all();

        return array_values(array_filter($lineas, fn (PresupuestoDetalle $linea) => $this->lineaUsaCotizacionVencida($linea)));
    }

    public function obtenerLinea(int $idLinea): ?PresupuestoDetalle
    {
        return PresupuestoDetalle::findOne($idLinea);
    }

    /**
     * Rechaza (devuelve false sin borrar) si el presupuesto de la linea no
     * es editable.
     */
    public function eliminarLinea(PresupuestoDetalle $linea): bool
    {
        if (!$this->puedeEditarse($linea->presupuesto)) {
            return false;
        }

        return $linea->delete() !== false;
    }

    /**
     * Agrupa las lineas del presupuesto por el nombre de la categoria de su
     * item de subcatalogo. Devuelve un arreglo:
     *   ['Cable redes' => ['orden' => int, 'lineas' => PresupuestoDetalle[], 'subtotal' => float], ...]
     * ordenado segun el campo "orden" de Categoria.
     */
    public function agruparPorCategoria(Presupuesto $presupuesto): array
    {
        $lineas = $presupuesto->getPresupuestoDetalles()
            // proyectoItem.especificacion: la especificacion enlazada en la
            // lista, que se muestra junto a la linea (vista y PDF).
            ->with(['subcatalogoItem.categoria', 'proyectoItem.especificacion'])
            ->all();

        $grupos = [];

        foreach ($lineas as $linea) {
            $categoria = $linea->subcatalogoItem->categoria ?? null;
            $nombreCategoria = $categoria->nombre ?? 'Sin categoría';
            $ordenCategoria = $categoria->orden ?? 999;

            if (!isset($grupos[$nombreCategoria])) {
                $grupos[$nombreCategoria] = [
                    'orden' => $ordenCategoria,
                    'lineas' => [],
                    'subtotal' => 0.0,
                ];
            }

            $grupos[$nombreCategoria]['lineas'][] = $linea;
            $grupos[$nombreCategoria]['subtotal'] += (float) $linea->precioTotal;
        }

        uasort($grupos, static fn ($a, $b) => $a['orden'] <=> $b['orden']);

        return $grupos;
    }

    /**
     * @param array $grupos resultado de agruparPorCategoria()
     */
    public function totalGeneral(array $grupos): float
    {
        return (float) array_sum(array_column($grupos, 'subtotal'));
    }

    /**
     * Resumen del presupuesto por partida presupuestaria, que es como lo
     * consolida la parte financiera. Agrupa por la partida GUARDADA en cada
     * linea (PresupuestoDetalle.partida, congelada al crearla), no por la
     * partida actual del item: asi el resumen de un presupuesto ya aprobado
     * no cambia aunque despues se corrija el subcatalogo.
     *
     * Es otra forma de sumar las MISMAS lineas que agruparPorCategoria(), asi
     * que la suma de estos subtotales siempre coincide con el total general.
     *
     * Devuelve una lista ordenada por partida; las lineas sin partida van al
     * final, agrupadas como "Sin partida":
     *   [['partida' => '34500', 'lineas' => 2, 'subtotal' => 150.0], ...]
     *
     * @return array<int, array{partida: string, lineas: int, subtotal: float}>
     */
    public function resumenPorPartida(Presupuesto $presupuesto): array
    {
        $porPartida = [];

        foreach ($presupuesto->getPresupuestoDetalles()->all() as $linea) {
            // trim(): la columna es de largo fijo; '' agrupa las lineas sin partida.
            $partida = trim((string) $linea->partida);

            if (!isset($porPartida[$partida])) {
                $porPartida[$partida] = ['lineas' => 0, 'subtotal' => 0.0];
            }

            $porPartida[$partida]['lineas']++;
            $porPartida[$partida]['subtotal'] += (float) $linea->precioTotal;
        }

        ksort($porPartida, SORT_STRING);

        $resumen = [];
        $sinPartida = null;

        foreach ($porPartida as $partida => $datos) {
            // (string): PHP convierte las claves numericas ("34500") a entero.
            $fila = ['partida' => (string) $partida] + $datos;

            if ($fila['partida'] === '') {
                $fila['partida'] = 'Sin partida';
                $sinPartida = $fila;
                continue;
            }

            $resumen[] = $fila;
        }

        if ($sinPartida !== null) {
            $resumen[] = $sinPartida;
        }

        return $resumen;
    }

    /**
     * Convierte el HTML ya renderizado (la vista "pdf" la arma el
     * controlador, porque renderizar vistas es tarea de la capa de
     * Presentacion) en el PDF consolidado.
     *
     * @return array{contenido: string, nombreArchivo: string}
     */
    public function generarPdf(Presupuesto $presupuesto, string $html): array
    {
        $generador = new PresupuestoPdfGenerator();

        return [
            'contenido' => $generador->generarDesdeHtml($html),
            'nombreArchivo' => $generador->nombreArchivo($presupuesto),
        ];
    }

    /**
     * El item de la lista al que apunta la linea, solo si es de la lista del
     * proyecto de este presupuesto y todavia no tiene linea. null en
     * cualquier otro caso.
     */
    private function itemDeListaSinPresupuestar(Presupuesto $presupuesto, PresupuestoDetalle $linea): ?ProyectoItem
    {
        if (empty($linea->idProyectoItem)) {
            return null;
        }

        $itemLista = ProyectoItem::findOne(['id' => (int) $linea->idProyectoItem, 'idProyecto' => $presupuesto->idProyecto]);

        if ($itemLista === null || PresupuestoDetalle::find()->andWhere(['idProyectoItem' => $itemLista->id])->exists()) {
            return null;
        }

        return $itemLista;
    }

    /**
     * Una linea solo puede tomar el precio de una cotizacion hecha para SU
     * MISMO item del subcatalogo. Sin esta verificacion, el formulario
     * permitiria presupuestar "Cable UTP" con el precio cotizado para un
     * "Switch". Una linea sin cotizacion (precio manual) siempre es valida.
     */
    private function cotizacionCorrespondeAlItem(PresupuestoDetalle $linea): bool
    {
        if (empty($linea->idCotizacionDetalle)) {
            return true;
        }

        $cotizacionDetalle = CotizacionDetalle::findOne($linea->idCotizacionDetalle);

        // (int) en ambos lados: lo que llega del formulario es texto ("7") y
        // lo que viene de la base es entero (7). Un precio cotizado para OTRA
        // fila de la lista tampoco vale (ver opcionesCotizacion()).
        return $cotizacionDetalle !== null
            && (int) $cotizacionDetalle->idSubcatalogo === (int) $linea->idSubcatalogo
            && ($cotizacionDetalle->idProyectoItem === null || (int) $cotizacionDetalle->idProyectoItem === (int) $linea->idProyectoItem);
    }

    /**
     * REGLA DE NEGOCIO CRITICA -- precio congelado: si la linea viene de una
     * cotizacion, su precioUnitario se copia desde CotizacionDetalle UNA SOLA
     * VEZ, aqui, al crear la linea. No existe (ni debe existir) ningun codigo
     * que vuelva a sincronizarlo despues: si el precio de la cotizacion
     * cambia, las lineas ya guardadas se quedan con el precio viejo a
     * proposito.
     */
    /**
     * Precios que puede usar cada item en "Armar presupuesto", del menor al
     * mayor: los cotizados para esa fila o, si no tiene ninguno, las
     * cotizaciones sueltas de su item del subcatalogo. Dos consultas en
     * total, no una por item.
     *
     * @param ProyectoItem[] $items
     * @return array<int, CotizacionDetalle[]> [idProyectoItem => opciones]
     */
    private function opcionesParaArmar(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $propias = CotizacionDetalle::find()
            ->andWhere(['idProyectoItem' => array_map(static fn (ProyectoItem $i) => $i->id, $items)])
            ->with('cotizacion.proveedor')
            ->orderBy(['precioUnitario' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $sueltas = CotizacionDetalle::find()
            ->andWhere(['idProyectoItem' => null])
            ->andWhere(['idSubcatalogo' => array_unique(array_map(static fn (ProyectoItem $i) => $i->idSubcatalogo, $items))])
            ->with('cotizacion.proveedor')
            ->orderBy(['precioUnitario' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $porItem = [];
        foreach ($propias as $detalle) {
            $porItem[(int) $detalle->idProyectoItem][] = $detalle;
        }

        $porSubcatalogo = [];
        foreach ($sueltas as $detalle) {
            $porSubcatalogo[(int) $detalle->idSubcatalogo][] = $detalle;
        }

        $opciones = [];
        foreach ($items as $item) {
            $opciones[$item->id] = $porItem[$item->id] ?? $porSubcatalogo[(int) $item->idSubcatalogo] ?? [];
        }

        return $opciones;
    }

    private function congelarPrecio(PresupuestoDetalle $linea): void
    {
        if (empty($linea->idCotizacionDetalle)) {
            return;
        }

        $cotizacionDetalle = CotizacionDetalle::findOne($linea->idCotizacionDetalle);

        if ($cotizacionDetalle === null) {
            return;
        }

        $linea->precioUnitario = $cotizacionDetalle->precioUnitario;

        if (empty($linea->unidadMedida)) {
            $linea->unidadMedida = $cotizacionDetalle->subcatalogoItem?->abreviaturaUnidad();
        }
    }

    /**
     * REGLA DE NEGOCIO CRITICA -- partida congelada: la partida
     * presupuestaria de la linea se copia desde su SubcatalogoItem UNA SOLA
     * VEZ, aqui, al crear la linea (mismo criterio que congelarPrecio()). No
     * existe (ni debe existir) ningun codigo que vuelva a sincronizarla
     * despues: si la partida del item cambia en el subcatalogo, las lineas ya
     * guardadas se quedan con la partida vieja a proposito, porque asi fue
     * presentado y aprobado ese presupuesto.
     *
     * Se asigna siempre (aunque el item no tenga partida y quede null) para
     * que nunca se guarde un valor de partida enviado desde el formulario.
     */
    private function congelarPartida(PresupuestoDetalle $linea): void
    {
        $linea->partida = $linea->subcatalogoItem->partida ?? null;
    }
}
