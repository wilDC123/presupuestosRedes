<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CorrelativoDocumento;
use backend\models\CotizacionDetalle;
use backend\models\HistorialProyecto;
use backend\models\Presupuesto;
use backend\models\PresupuestoComplemento;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use Throwable;
use Yii;

/**
 * Capa de Servicio de la pantalla "Presupuesto" del proyecto: el
 * presupuesto como DOCUMENTO VIVO.
 *
 * Cada item de la lista es una linea del presupuesto. No se "agregan"
 * lineas: se ELIGE el precio de cada item entre lo que cotizaron los
 * proveedores o se escribe un PRECIO DIRECTO (sin cotizacion, con su fuente
 * opcional; p. ej. el PrecioReferencial de SIGMA). Las reglas:
 *  - al entrar, cada item sin linea recibe el MENOR precio vigente,
 *  - elegir otro precio REEMPLAZA la linea por una nueva, con el precio y la
 *    partida congelados en ese momento (PresupuestoService::congelarPrecio()
 *    y congelarPartida()); el precio de una linea guardada nunca se
 *    recalcula,
 *  - elegir un precio que no es el menor admite una justificacion OPCIONAL
 *    (el presupuesto es un precio referencial, no la adjudicacion de una
 *    compra; si se escribe, sale en el anexo del PDF),
 *  - solo se modifica mientras el presupuesto es un borrador ("en proceso").
 */
class ElaborarPresupuestoService
{
    /** Valor de la opcion "Precio directo" (sin cotizacion) en elegir(). */
    public const DIRECTO = 'directo';

    private PresupuestoService $presupuestos;
    private HistorialService $historial;

    /**
     * Precios que reemplazarLinea() fue cambiando en esta operacion ("Switch:
     * 1.250,00 (Proveedor A) → 1.180,00 (Proveedor B)"); anotarPrecios() los
     * pasa al historial en un solo registro.
     *
     * @var string[]
     */
    private array $cambiosDePrecio = [];

    public function __construct()
    {
        $this->presupuestos = new PresupuestoService();
        $this->historial = new HistorialService();
    }

    /**
     * El presupuesto del proyecto (cada proyecto tiene uno solo). Si todavia
     * no tiene, lo crea en "en proceso" con el siguiente codigo RD-PI-PR (ver
     * CodificacionService); los demas datos de cabecera se piden recien al
     * enviarlo a revision. Un presupuesto creado antes de que existiera el
     * codigo, y que nunca tuvo numero, recibe el suyo aqui.
     */
    public function delProyecto(Proyecto $proyecto): Presupuesto
    {
        $presupuesto = $proyecto->presupuesto;

        if ($presupuesto !== null && trim((string) $presupuesto->numeroPresupuesto) !== '') {
            return $presupuesto;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $esNuevo = $presupuesto === null;
            if ($esNuevo) {
                $presupuesto = new Presupuesto();
                $presupuesto->idProyecto = $proyecto->id;
                $presupuesto->estado = Presupuesto::ESTADO_EN_PROCESO;
                // Casi todos llevan la mano de obra de la DTIC: nace puesta y
                // el encargado la quita si no corresponde (2026-10-23).
                $presupuesto->manoDeObra = Presupuesto::MANO_DE_OBRA_DTIC;
            }

            // Con el proceso del proyecto (RD-GR-PR-016); el numero es uno
            // solo para todos los procesos.
            $numero = (new CodificacionService())->tomar(CorrelativoDocumento::TIPO_PRESUPUESTO);
            $presupuesto->numeroPresupuesto = CorrelativoDocumento::codigo(
                CorrelativoDocumento::TIPO_PRESUPUESTO,
                $numero,
                $proyecto->proceso->codigo ?? CorrelativoDocumento::PROCESO
            );
            $presupuesto->save(false);
            $this->historial->registrar(
                $proyecto,
                HistorialProyecto::ACCION_PRESUPUESTO_CREADO,
                $esNuevo ? $presupuesto->numeroPresupuesto : $presupuesto->numeroPresupuesto . ' (número asignado a un presupuesto de antes)'
            );
            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

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

                if ($menor !== null && $this->reemplazarLinea($presupuesto, $item, (int) $menor->id, null, null, null) === null) {
                    $creadas++;
                }
            }

            $this->anotarPrecios($presupuesto, 'Menor precio cotizado a los ítems que no tenían precio');
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
     *     item: ProyectoItem, celdas: array<int, CotizacionDetalle>, menor: int|null, linea: PresupuestoDetalle|null,
     *     sigma: float|null
     *   }>}>,
     *   conSigmaSinPrecio: int,
     *   total: float, conPrecio: int, totalFilas: int
     * }
     */
    public function tabla(Presupuesto $presupuesto): array
    {
        $listaItems = new ListaItemsService();
        $items = $presupuesto->proyecto->getItems()->with(['subcatalogoItem.catalogoSigma', 'categoria', 'especificacion.versionActual', 'emisionVigente.version'])->all();
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
        $conSigmaSinPrecio = 0;

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

                $sigma = $this->precioSigma($item);

                if ($linea !== null) {
                    $subtotal += (float) $linea->precioTotal;
                    $conPrecio++;
                } elseif ($sigma !== null) {
                    $conSigmaSinPrecio++;
                }

                $filas[] = [
                    'item' => $item,
                    'celdas' => $celdas,
                    'menor' => $menor === null ? null : (int) $menor->id,
                    'linea' => $linea,
                    // Sugerencia para el precio directo.
                    'sigma' => $sigma,
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
            'conSigmaSinPrecio' => $conSigmaSinPrecio,
            'totalFilas' => count($items),
        ];
    }

    /**
     * Elige el precio de un item:
     *   $opcion  idCotizacionDetalle de una cotizacion de ESTA fila, o
     *            self::DIRECTO (precio escrito sin cotizacion)
     *   $precio, $fuente  solo con self::DIRECTO; la fuente es opcional
     *   $justificacion  opcional; se guarda solo si lo elegido no es el menor precio
     *
     * Devuelve null si se guardo, o el mensaje de por que no.
     */
    public function elegir(Presupuesto $presupuesto, int $idProyectoItem, string $opcion, string $precio = '', string $justificacion = '', string $fuente = ''): ?string
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

        if ($opcion === self::DIRECTO) {
            if ($precio === '' || !is_numeric($precio) || (float) $precio < 0) {
                return 'Escriba un precio directo mayor o igual a 0.';
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

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $error = $this->reemplazarLinea(
                $presupuesto,
                $item,
                $idCotizacionDetalle,
                $idCotizacionDetalle === null ? $precio : null,
                // La justificacion solo tiene sentido si NO es el menor precio.
                $esMenor || $menor === null || $justificacion === '' ? null : $justificacion,
                $idCotizacionDetalle === null ? $fuente : null
            );

            if ($error === null) {
                $this->anotarPrecios($presupuesto, null);
                $transaccion->commit();
            } else {
                $this->cambiosDePrecio = [];
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
     *    menor, la linea lleva $justificacion (opcional).
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
            $elecciones[] = [$item, (int) $elegida->id, $esMenor || $justificacion === '' ? null : $justificacion];
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

                $error = $this->reemplazarLinea($presupuesto, $item, $idCotizacionDetalle, null, $justificacionLinea, null);

                if ($error !== null) {
                    $this->cambiosDePrecio = [];
                    $transaccion->rollBack();

                    return ($item->subcatalogoItem->descripcion ?? 'Ítem') . ': ' . $error;
                }

                if ((int) $antes !== $idCotizacionDetalle) {
                    $cambiadas++;
                }
            }

            $this->anotarPrecios($presupuesto, $modo === 'menor' ? 'Atajo: menor precio por ítem' : 'Atajo: todo a un proveedor');
            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return null;
    }

    /**
     * Atajo: a cada item que todavia NO tiene precio le pone como precio
     * directo el PrecioReferencial de su item SIGMA (fuente
     * PresupuestoDetalle::FUENTE_SIGMA). Nunca cambia un precio ya elegido.
     * Devuelve null si se aplico, o el mensaje de por que no. $aplicados
     * (por referencia) es para el mensaje de la pantalla.
     */
    public function usarPrecioSigma(Presupuesto $presupuesto, int &$aplicados = 0): ?string
    {
        $aplicados = 0;

        if (!$presupuesto->esBorrador()) {
            return 'El presupuesto está "' . $presupuesto->estadoLabel() . '" y ya no se modifica.';
        }

        $sinPrecio = (new ListaItemsService())->pendientesDePresupuestar($presupuesto->proyecto);
        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach ($sinPrecio as $item) {
                $sigma = $this->precioSigma($item);

                if ($sigma === null) {
                    continue;
                }

                $error = $this->reemplazarLinea($presupuesto, $item, null, (string) $sigma, null, PresupuestoDetalle::FUENTE_SIGMA);

                if ($error !== null) {
                    $this->cambiosDePrecio = [];
                    $transaccion->rollBack();

                    return ($item->subcatalogoItem->descripcion ?? 'Ítem') . ': ' . $error;
                }

                $aplicados++;
            }

            $this->anotarPrecios($presupuesto, 'Atajo: precio SIGMA en los ítems sin precio');
            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return $aplicados === 0 ? 'Ningún ítem sin precio tiene precio referencial en SIGMA.' : null;
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
     * "Enviar a revision": en proceso -> en revision, solo si
     *  - todos los items de la lista tienen precio (control()), y
     *  - la cabecera esta completa (escenario Presupuesto::SCENARIO_ENVIAR).
     * Las cotizaciones vencidas no bloquean (el controlador avisa).
     *
     * $cabecera es lo que llega del formulario (Presupuesto[...]); se copian
     * SOLO oficio, area y fecha: nunca el estado, el proyecto ni el codigo
     * (RD-PI-PR, lo asigna el sistema).
     * Devuelve false con los mensajes en $errores; los errores de cada campo
     * quedan ademas en $presupuesto->errors.
     *
     * @param string[] $errores (por referencia)
     */
    public function enviarARevision(Presupuesto $presupuesto, array $cabecera, array &$errores = []): bool
    {
        $errores = [];

        if (!$presupuesto->esBorrador()) {
            $errores[] = 'Solo se envía a revisión un presupuesto "En proceso".';

            return false;
        }

        foreach (['oficioDtic', 'areaIntervencion', 'fecha'] as $atributo) {
            if (array_key_exists($atributo, $cabecera) && is_string($cabecera[$atributo])) {
                $presupuesto->$atributo = $cabecera[$atributo];
            }
        }

        $presupuesto->scenario = Presupuesto::SCENARIO_ENVIAR;
        $sinPrecio = $this->control($presupuesto)['sinPrecio'];

        if ($sinPrecio !== []) {
            $errores[] = 'Faltan precios: ' . implode(', ', $sinPrecio) . '.';
        }

        if (!$presupuesto->validate()) {
            $errores = array_merge($errores, $presupuesto->getErrorSummary(false));
        }

        if ($errores !== []) {
            return false;
        }

        // cambiarEstado() vuelve a verificar la transicion y estos mismos
        // requisitos (es la barrera final), y guarda la cabecera junto con el
        // estado nuevo.
        return $this->presupuestos->cambiarEstado($presupuesto, Presupuesto::ESTADO_EN_REVISION);
    }

    /**
     * Pone o quita la fila de mano de obra a cargo de la DTIC (ver
     * Presupuesto::MANO_DE_OBRA_DTIC). Si se incluye sin texto, se usa el
     * texto de siempre. Como los precios, solo se cambia en borrador.
     * Devuelve el mensaje de error, o null si se guardo.
     */
    public function guardarManoDeObra(Presupuesto $presupuesto, bool $incluir, string $texto): ?string
    {
        if (!$presupuesto->esBorrador()) {
            return 'Solo se cambia la mano de obra de un presupuesto "En proceso".';
        }

        $texto = trim($texto);
        $presupuesto->manoDeObra = $incluir ? ($texto !== '' ? $texto : Presupuesto::MANO_DE_OBRA_DTIC) : null;

        if (!$presupuesto->save(true, ['manoDeObra'])) {
            return implode(' ', $presupuesto->getErrorSummary(false));
        }

        return null;
    }

    /**
     * Reemplaza TODAS las filas de una seccion sin precio (materiales que
     * aporta la DTIC o trabajos complementarios, ver PresupuestoComplemento)
     * por las que vienen del formulario, en ese orden. Una fila con todo en
     * blanco se ignora (es la fila vacia que quedo en el formulario); sin
     * filas, la seccion no sale en el PDF. Si una fila de material elige un
     * item del subcatalogo y deja en blanco el nombre o la unidad, se toman
     * del item. Como los precios, solo se cambia en borrador; si una fila no
     * es valida no se guarda ninguna.
     *
     * @param array<int, array<string, mixed>> $filas
     * @param string[] $errores mensajes por fila ("Fila 2: ...")
     */
    public function guardarComplementos(Presupuesto $presupuesto, string $tipo, array $filas, array &$errores = []): bool
    {
        $errores = [];

        if (!$presupuesto->esBorrador()) {
            $errores[] = 'Solo se cambian los materiales y trabajos de un presupuesto "En proceso".';
            return false;
        }

        if (!isset(PresupuestoComplemento::TIPOS[$tipo])) {
            $errores[] = 'Sección no válida.';
            return false;
        }

        $nuevas = [];

        foreach (array_values($filas) as $numero => $datos) {
            $datos = array_map(static fn ($valor) => is_string($valor) ? trim($valor) : '', (array) $datos);

            // El subtitulo solo no cuenta: en los trabajos es un desplegable que siempre tiene valor.
            if (implode('', array_diff_key($datos, ['grupo' => true])) === '') {
                continue;
            }

            $fila = new PresupuestoComplemento();
            $fila->setAttributes(array_intersect_key($datos, array_flip(['grupo', 'descripcion', 'cantidad', 'unidadMedida', 'idSubcatalogo', 'unidadEncargada', 'observacion'])));
            $fila->idPresupuesto = $presupuesto->id;
            $fila->tipo = $tipo;
            $fila->orden = count($nuevas) + 1;

            if ($tipo === PresupuestoComplemento::TIPO_MATERIAL && ctype_digit((string) $fila->idSubcatalogo)) {
                $item = SubcatalogoItem::findOne((int) $fila->idSubcatalogo);

                if ($item !== null) {
                    $fila->descripcion = $fila->descripcion ?: $item->descripcion;
                    $fila->unidadMedida = $fila->unidadMedida ?: $item->abreviaturaUnidad();
                }
            }

            if (!$fila->validate()) {
                $errores[] = 'Fila ' . ($numero + 1) . ': ' . implode(' ', $fila->getErrorSummary(false));
            }

            $nuevas[] = $fila;
        }

        if ($errores !== []) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            PresupuestoComplemento::deleteAll(['idPresupuesto' => $presupuesto->id, 'tipo' => $tipo]);

            foreach ($nuevas as $fila) {
                $fila->save(false);
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        unset($presupuesto->complementos);

        return true;
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
     * PrecioReferencial del item SIGMA al que apunta el item, o null si no
     * apunta a ninguno o el catalogo no tiene precio (0 o vacio). Es el
     * precio de HOY del catalogo: se congela recien al guardarlo en la linea.
     */
    private function precioSigma(ProyectoItem $item): ?float
    {
        $precio = (float) ($item->subcatalogoItem->catalogoSigma->PrecioReferencial ?? 0);

        return $precio > 0 ? $precio : null;
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
    private function reemplazarLinea(Presupuesto $presupuesto, ProyectoItem $item, ?int $idCotizacionDetalle, ?string $precioDirecto, ?string $justificacion, ?string $fuente): ?string
    {
        $actual = PresupuestoDetalle::findOne(['idPresupuesto' => $presupuesto->id, 'idProyectoItem' => $item->id]);
        $fuente = $fuente === null || trim($fuente) === '' ? null : trim($fuente);

        if ($actual !== null) {
            $mismaCotizacion = $idCotizacionDetalle !== null && (int) $actual->idCotizacionDetalle === $idCotizacionDetalle;
            $mismoDirecto = $idCotizacionDetalle === null && $actual->idCotizacionDetalle === null
                && (float) $actual->precioUnitario === (float) $precioDirecto;

            // Mismo precio: solo cambian los textos (el precio no se vuelve a congelar).
            if ($mismaCotizacion || $mismoDirecto) {
                $actual->justificacion = $justificacion;
                $actual->fuentePrecio = $mismoDirecto ? $fuente : null;

                if (!$actual->validate(['justificacion', 'fuentePrecio'])) {
                    return implode(' ', $actual->getErrorSummary(true));
                }

                $actual->updateAttributes(['justificacion' => $actual->justificacion, 'fuentePrecio' => $actual->fuentePrecio]);

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
        $linea->precioUnitario = $precioDirecto;
        $linea->justificacion = $justificacion;
        $linea->fuentePrecio = $idCotizacionDetalle === null ? $fuente : null;
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

        $this->cambiosDePrecio[] = ($item->subcatalogoItem->descripcion ?? 'Ítem') . ': '
            . ($actual === null ? '' : $this->precioTexto($actual) . ' → ') . $this->precioTexto($linea);

        return null;
    }

    /**
     * "1.250,00 (Proveedor A)" o "300,00 (precio directo: Mercado)": el
     * precio unitario congelado de la linea y de donde salio.
     */
    private function precioTexto(PresupuestoDetalle $linea): string
    {
        if ($linea->idCotizacionDetalle !== null) {
            $origen = $linea->cotizacionDetalle->cotizacion->proveedor->razonSocial ?? 'cotización';
        } else {
            $origen = 'precio directo' . ($linea->fuentePrecio === null || $linea->fuentePrecio === '' ? '' : ': ' . $linea->fuentePrecio);
        }

        return Yii::$app->formatter->asDecimal((float) $linea->precioUnitario, 2) . ' (' . $origen . ')';
    }

    /**
     * Pasa al historial, en UN registro, los precios que cambio la operacion
     * (si cambio alguno), con un titulo opcional para los atajos. Se llama
     * dentro de la transaccion de la operacion.
     */
    private function anotarPrecios(Presupuesto $presupuesto, ?string $titulo): void
    {
        if ($this->cambiosDePrecio !== []) {
            $lineas = $titulo === null ? $this->cambiosDePrecio : array_merge([$titulo . ':'], $this->cambiosDePrecio);
            $this->historial->registrar($presupuesto->proyecto, HistorialProyecto::ACCION_PRECIOS, implode("\n", $lineas));
        }

        $this->cambiosDePrecio = [];
    }
}
