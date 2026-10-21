<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\PresupuestoPdfGenerator;
use backend\models\ConfiguracionPresupuesto;
use backend\models\CotizacionDetalle;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
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
 *  - una linea por item de la LISTA del proyecto (la eleccion del precio
 *    de cada item vive en ElaborarPresupuestoService),
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

    /**
     * Total de cada presupuesto, para el listado: [idPresupuesto => total],
     * en UNA consulta agrupada (no una por presupuesto).
     *
     * @return array<int, float>
     */
    public function totalesPorPresupuesto(): array
    {
        return array_map('floatval', PresupuestoDetalle::find()
            ->select(['total' => 'SUM(precioTotal)', 'idPresupuesto'])
            ->groupBy('idPresupuesto')
            ->indexBy('idPresupuesto')
            ->column());
    }

    /**
     * REGLA DE NEGOCIO: solo se elimina un presupuesto "en proceso"
     * (borrador). Uno enviado a revision, observado, aprobado o cancelado ya
     * fue un documento del proceso y se conserva. Devuelve false sin tocar
     * nada si no se puede.
     */
    public function eliminar(Presupuesto $presupuesto): bool
    {
        if (!$presupuesto->esBorrador()) {
            return false;
        }

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
     * Estados a los que se puede pasar con un boton de la pantalla del
     * presupuesto, segun Presupuesto::transicionesPermitidas(). Queda fuera
     * "en revision": a ese estado se llega con el formulario "Enviar a
     * revision", que pide la cabecera (ElaborarPresupuestoService::enviarARevision()).
     *
     * @return string[]
     */
    public function estadosConBoton(Presupuesto $presupuesto): array
    {
        return array_values(array_diff(
            Presupuesto::transicionesPermitidas()[$presupuesto->estado] ?? [],
            [Presupuesto::ESTADO_EN_REVISION]
        ));
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

        if ($nuevoEstado === Presupuesto::ESTADO_EN_REVISION && !$this->cumpleRequisitosDeRevision($presupuesto)) {
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
     * REGLA DE NEGOCIO: a "en revision" solo se llega con la cabecera completa
     * (escenario Presupuesto::SCENARIO_ENVIAR: numero, oficio, area y fecha)
     * y con todos los items de la lista con precio.
     *
     * ElaborarPresupuestoService::enviarARevision() revisa lo mismo ANTES, para
     * explicarle al usuario que falta. Esta es la barrera final: vive aqui, en
     * el unico metodo que cambia el estado, para que ningun otro camino (otro
     * controlador, un comando de consola) pueda saltarsela.
     */
    private function cumpleRequisitosDeRevision(Presupuesto $presupuesto): bool
    {
        $escenario = $presupuesto->scenario;
        $presupuesto->scenario = Presupuesto::SCENARIO_ENVIAR;
        $cabeceraCompleta = $presupuesto->validate();
        $presupuesto->scenario = $escenario;

        return $cabeceraCompleta
            && (new ListaItemsService())->pendientesDePresupuestar($presupuesto->proyecto) === [];
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

    /**
     * Agrupa las lineas del presupuesto por el nombre de la categoria de su
     * item de subcatalogo. Devuelve un arreglo:
     *   ['Cable redes' => ['orden' => int, 'lineas' => PresupuestoDetalle[], 'subtotal' => float], ...]
     * ordenado segun el campo "orden" de Categoria.
     */
    public function agruparPorCategoria(Presupuesto $presupuesto): array
    {
        $lineas = $presupuesto->getPresupuestoDetalles()
            // proyectoItem.especificacion y emisionVigente: la especificacion
            // enlazada en la lista y su NET, que se muestran junto a la linea
            // (vista y PDF).
            // cotizacionDetalle...proveedor: de quien es el precio (vista).
            // subcatalogoItem.catalogoSigma: el item SIGMA (PDF de la
            // relacion con el catalogo SIGMA).
            ->with(['subcatalogoItem.categoria', 'subcatalogoItem.catalogoSigma', 'proyectoItem.especificacion', 'proyectoItem.emisionVigente', 'cotizacionDetalle.cotizacion.proveedor'])
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
     * Nota, firmas y banda de pie del PDF (redes.ConfiguracionPresupuesto).
     */
    public function guardarConfiguracion(ConfiguracionPresupuesto $configuracion, array $datosFormulario): bool
    {
        return $configuracion->load($datosFormulario) && $configuracion->save();
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
            'contenido' => $generador->generarDesdeHtml($html, $this->marcaDeAgua($presupuesto)),
            'nombreArchivo' => $generador->nombreArchivo($presupuesto),
        ];
    }

    /**
     * Reporte de apoyo "Relacion con el catalogo SIGMA": cada linea del
     * presupuesto junto al item SIGMA al que apunta. En oficio horizontal (tiene
     * muchas columnas) y con la misma marca de agua que el presupuesto.
     *
     * @return array{contenido: string, nombreArchivo: string}
     */
    public function generarPdfSigma(Presupuesto $presupuesto, string $html): array
    {
        $generador = new PresupuestoPdfGenerator();

        return [
            'contenido' => $generador->generarDesdeHtml($html, $this->marcaDeAgua($presupuesto), PresupuestoPdfGenerator::HOJA_OFICIO_HORIZONTAL),
            'nombreArchivo' => $generador->nombreArchivo($presupuesto, 'relacion_sigma_'),
        ];
    }

    /**
     * Solo el aprobado sale limpio: cualquier otro estado lleva marca.
     */
    private function marcaDeAgua(Presupuesto $presupuesto): ?string
    {
        $marcas = [Presupuesto::ESTADO_APROBADO => null, Presupuesto::ESTADO_CANCELADO => 'CANCELADO'];

        return array_key_exists($presupuesto->estado, $marcas) ? $marcas[$presupuesto->estado] : 'BORRADOR';
    }

    /**
     * REGLA DE NEGOCIO CRITICA -- precio congelado: si la linea viene de una
     * cotizacion, su precioUnitario se copia desde CotizacionDetalle UNA SOLA
     * VEZ, aqui, al crear la linea. No existe (ni debe existir) ningun codigo
     * que vuelva a sincronizarlo despues: si el precio de la cotizacion
     * cambia, las lineas ya guardadas se quedan con el precio viejo a
     * proposito.
     */
    public function congelarPrecio(PresupuestoDetalle $linea): void
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
    public function congelarPartida(PresupuestoDetalle $linea): void
    {
        $linea->partida = $linea->subcatalogoItem->partida ?? null;
    }
}
