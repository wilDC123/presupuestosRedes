<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CatalogoSigma;
use backend\models\Cotizacion;
use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SolicitudCotizacion;
use backend\models\SubcatalogoItem;
use DateTimeImmutable;

/**
 * Capa de Servicio del panel general (Dashboard).
 *
 * No corresponde a una tabla: junta numeros de varias entidades para las
 * tarjetas y tablas de la pantalla de inicio. El controlador solo le pide
 * los datos ya calculados y se los pasa a la vista.
 */
class DashboardService
{
    private const ESTADOS_ABIERTOS = [
        Presupuesto::ESTADO_EN_PROCESO,
        Presupuesto::ESTADO_EN_REVISION,
        Presupuesto::ESTADO_OBSERVADO,
    ];

    /**
     * Numeros de las cuatro tarjetas superiores.
     *
     * - proyectosActivos: proyectos que todavia no tienen presupuesto, o cuyo
     *   presupuesto mas reciente sigue abierto (en proceso / en revision /
     *   observado). Los aprobados y cancelados ya no cuentan como activos.
     * - montoAprobado: suma de las lineas de los presupuestos aprobados
     *   creados en el anio en curso.
     *
     * - proyectosFaseSuperior: cuantos de los activos son fase 2 o superior.
     *
     * @return array{proyectosActivos: int, proyectosFaseSuperior: int, proyectosSinPresupuesto: int, enRevision: int, montoAprobado: float, anio: int, itemsSubcatalogo: int, totalSigma: int}
     */
    public function indicadores(): array
    {
        $ultimos = $this->ultimoPresupuestoPorProyecto();
        $totalProyectos = (int) Proyecto::find()->count();
        $sinPresupuesto = $totalProyectos - count($ultimos);

        $abiertos = 0;
        $idsCerrados = [];

        foreach ($ultimos as $idProyecto => $presupuesto) {
            if (in_array($presupuesto->estado, self::ESTADOS_ABIERTOS, true)) {
                $abiertos++;
            } else {
                $idsCerrados[] = $idProyecto;
            }
        }

        // Activos de fase 2 o superior: todos los de esa fase menos los que
        // ya tienen su presupuesto aprobado o cancelado.
        $faseSuperior = (int) Proyecto::find()
            ->andWhere(['>=', 'numeroFase', 2])
            ->andFilterWhere(['not in', 'id', $idsCerrados])
            ->count();

        $anio = (int) date('Y');

        $montoAprobado = (float) PresupuestoDetalle::find()
            ->alias('d')
            ->innerJoin(['p' => Presupuesto::tableName()], 'p.id = d.idPresupuesto')
            ->andWhere(['p.estado' => Presupuesto::ESTADO_APROBADO])
            ->andWhere(['>=', 'p.fechaCreacion', "$anio-01-01"])
            ->andWhere(['<', 'p.fechaCreacion', ($anio + 1) . '-01-01'])
            ->sum('d.precioTotal');

        return [
            'proyectosActivos' => $sinPresupuesto + $abiertos,
            'proyectosFaseSuperior' => $faseSuperior,
            'proyectosSinPresupuesto' => $sinPresupuesto,
            'enRevision' => (int) Presupuesto::find()->andWhere(['estado' => Presupuesto::ESTADO_EN_REVISION])->count(),
            'montoAprobado' => $montoAprobado,
            'anio' => $anio,
            'itemsSubcatalogo' => (int) SubcatalogoItem::find()->count(),
            'totalSigma' => (int) CatalogoSigma::find()->andWhere(['CodigoEstado' => CatalogoSigma::ESTADO_VIGENTE])->count(),
        ];
    }

    /**
     * Ultimos proyectos creados, cada uno con su presupuesto mas reciente
     * (null si aun no tiene) y el monto de ese presupuesto.
     *
     * @return array<int, array{proyecto: Proyecto, presupuesto: Presupuesto|null, monto: float|null}>
     */
    public function proyectosRecientes(int $limite = 5): array
    {
        $proyectos = Proyecto::find()
            ->orderBy(['fechaCreacion' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limite)
            ->all();

        if ($proyectos === []) {
            return [];
        }

        $ultimos = $this->ultimoPresupuestoPorProyecto(array_map(static fn (Proyecto $p) => $p->id, $proyectos));

        // Montos de esos presupuestos en UNA consulta agrupada.
        $montos = PresupuestoDetalle::find()
            ->select(['total' => 'SUM(precioTotal)', 'idPresupuesto'])
            ->andWhere(['idPresupuesto' => array_map(static fn (Presupuesto $p) => $p->id, $ultimos)])
            ->groupBy('idPresupuesto')
            ->indexBy('idPresupuesto')
            ->asArray()
            ->column();

        $filas = [];

        foreach ($proyectos as $proyecto) {
            $presupuesto = $ultimos[$proyecto->id] ?? null;

            $filas[] = [
                'proyecto' => $proyecto,
                'presupuesto' => $presupuesto,
                'monto' => $presupuesto !== null && isset($montos[$presupuesto->id]) ? (float) $montos[$presupuesto->id] : null,
            ];
        }

        return $filas;
    }

    /**
     * Tarjeta "Que tengo pendiente": avisos del trabajo que espera al
     * encargado, agrupados por tipo. Cada grupo trae sus filas (una por
     * proyecto) con el enlace a la pantalla donde se resuelve.
     *
     * Solo cuentan los proyectos abiertos: sin presupuesto o con el
     * presupuesto todavia editable. Uno aprobado o cancelado ya no tiene nada
     * pendiente. Los avisos son los mismos que el sistema da dentro de cada
     * proyecto; aqui solo se juntan para verlos sin entrar a cada uno.
     * Los grupos sin filas no se devuelven.
     *
     * $hoy solo se pasa en las pruebas.
     *
     * @return array<int, array{titulo: string, nota: string, filas: array<int, array{proyecto: Proyecto, detalle: string, url: array}>}>
     */
    public function pendientes(?DateTimeImmutable $hoy = null): array
    {
        $hoy ??= new DateTimeImmutable('today');
        $proyectoService = new ProyectoService();

        $grupos = [
            [
                'titulo' => 'Presupuestos observados',
                'nota' => 'Volvieron de revisión con observaciones: corregir y volver a enviar.',
                'filas' => $this->observados($proyectoService),
            ],
            [
                'titulo' => 'Ítems sin precio',
                'nota' => 'Presupuestos en proceso que todavía no se pueden enviar a revisión.',
                'filas' => $this->itemsSinPrecio($proyectoService),
            ],
            [
                'titulo' => 'Cotizaciones vencidas',
                'nota' => 'Líneas con el precio de una proforma cuya validez ya pasó.',
                'filas' => $this->cotizacionesVencidas($proyectoService, $hoy),
            ],
            [
                'titulo' => 'Especificaciones por reemitir',
                'nota' => 'Formularios vigentes vencidos o con una versión más nueva de la ficha.',
                'filas' => $this->especificacionesPorReemitir($hoy),
            ],
            [
                'titulo' => 'Solicitudes sin respuesta',
                'nota' => 'Solicitudes de cotización sin ninguna cotización registrada.',
                'filas' => $this->solicitudesSinRespuesta(),
            ],
        ];

        return array_values(array_filter($grupos, static fn (array $grupo) => $grupo['filas'] !== []));
    }

    /**
     * @return array<int, array{proyecto: Proyecto, detalle: string, url: array}>
     */
    private function observados(ProyectoService $proyectoService): array
    {
        $presupuestos = Presupuesto::find()
            ->with('proyecto')
            ->andWhere(['estado' => Presupuesto::ESTADO_OBSERVADO])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        return array_map(static fn (Presupuesto $p) => [
            'proyecto' => $p->proyecto,
            'detalle' => (string) $p->numeroPresupuesto,
            'url' => $proyectoService->urlPresupuesto($p->proyecto),
        ], $presupuestos);
    }

    /**
     * Presupuestos en proceso con items de la lista que todavia no tienen
     * linea. Es la regla de ListaItemsService::pendientesDePresupuestar(),
     * pero contada para todos los proyectos en UNA consulta agrupada.
     *
     * @return array<int, array{proyecto: Proyecto, detalle: string, url: array}>
     */
    private function itemsSinPrecio(ProyectoService $proyectoService): array
    {
        $presupuestos = Presupuesto::find()
            ->with('proyecto')
            ->andWhere(['estado' => Presupuesto::ESTADO_EN_PROCESO])
            ->indexBy('idProyecto')
            ->all();

        if ($presupuestos === []) {
            return [];
        }

        // El "not null" importa: un NOT IN contra una lista que trae un NULL
        // no devuelve ninguna fila en SQL.
        $presupuestados = PresupuestoDetalle::find()->select('idProyectoItem')->andWhere(['not', ['idProyectoItem' => null]]);

        $sinPrecio = ProyectoItem::find()
            ->select(['n' => 'COUNT(*)', 'idProyecto'])
            ->andWhere(['idProyecto' => array_keys($presupuestos)])
            ->andWhere(['not in', 'id', $presupuestados])
            ->groupBy('idProyecto')
            ->orderBy(['idProyecto' => SORT_ASC])
            ->indexBy('idProyecto')
            ->asArray()
            ->column();

        $filas = [];

        foreach ($sinPrecio as $idProyecto => $n) {
            $proyecto = $presupuestos[$idProyecto]->proyecto;
            $filas[] = [
                'proyecto' => $proyecto,
                'detalle' => (int) $n === 1 ? '1 ítem sin precio' : "$n ítems sin precio",
                'url' => $proyectoService->urlPresupuesto($proyecto),
            ];
        }

        return $filas;
    }

    /**
     * Lineas de presupuestos abiertos cuyo precio viene de una cotizacion
     * vencida (Cotizacion::estaVencida(), la misma regla del aviso que sale
     * al enviar y al aprobar).
     *
     * @return array<int, array{proyecto: Proyecto, detalle: string, url: array}>
     */
    private function cotizacionesVencidas(ProyectoService $proyectoService, DateTimeImmutable $hoy): array
    {
        $lineas = PresupuestoDetalle::find()
            ->alias('d')
            ->innerJoin(['p' => Presupuesto::tableName()], 'p.id = d.idPresupuesto')
            ->andWhere(['p.estado' => self::ESTADOS_ABIERTOS])
            ->andWhere(['not', ['d.idCotizacionDetalle' => null]])
            ->with(['presupuesto.proyecto', 'cotizacionDetalle.cotizacion'])
            ->orderBy(['d.idPresupuesto' => SORT_ASC])
            ->all();

        $vencidas = [];

        foreach ($lineas as $linea) {
            $cotizacion = $linea->cotizacionDetalle->cotizacion ?? null;

            if ($cotizacion instanceof Cotizacion && $cotizacion->estaVencida($hoy)) {
                $vencidas[$linea->idPresupuesto] ??= ['proyecto' => $linea->presupuesto->proyecto, 'lineas' => 0];
                $vencidas[$linea->idPresupuesto]['lineas']++;
            }
        }

        return array_values(array_map(static fn (array $dato) => [
            'proyecto' => $dato['proyecto'],
            'detalle' => $dato['lineas'] === 1 ? '1 línea con cotización vencida' : "{$dato['lineas']} líneas con cotización vencida",
            'url' => $proyectoService->urlPresupuesto($dato['proyecto']),
        ], $vencidas));
    }

    /**
     * Emisiones vigentes de proyectos abiertos que conviene reemitir: las
     * vencidas (EspecificacionEmision::estaVencida()) y las que usan una
     * version de la ficha que ya no es la mas nueva. Son dos de los avisos de
     * EspecificacionEmisionService::estadoPorItem(); el tercero (cantidad
     * distinta a la de la lista) se ve en la lista de items.
     *
     * @return array<int, array{proyecto: Proyecto, detalle: string, url: array}>
     */
    private function especificacionesPorReemitir(DateTimeImmutable $hoy): array
    {
        // Reemitir solo se permite mientras el presupuesto no este aprobado
        // ni cancelado (EspecificacionEmisionService).
        $cerrados = Presupuesto::find()
            ->select('idProyecto')
            ->andWhere(['estado' => [Presupuesto::ESTADO_APROBADO, Presupuesto::ESTADO_CANCELADO]]);

        $emisiones = EspecificacionEmision::find()
            ->with(['version', 'proyecto'])
            ->andWhere(['estado' => EspecificacionEmision::ESTADO_VIGENTE])
            ->andWhere(['not', ['idProyectoItem' => null]])
            ->andWhere(['not in', 'idProyecto', $cerrados])
            ->orderBy(['idProyecto' => SORT_ASC, 'numeroNet' => SORT_ASC])
            ->all();

        if ($emisiones === []) {
            return [];
        }

        $fichas = Especificacion::find()
            ->with('versionActual')
            ->andWhere(['id' => array_unique(array_map(static fn (EspecificacionEmision $e) => $e->version->idEspecificacion, $emisiones))])
            ->indexBy('id')
            ->all();

        $porProyecto = [];

        foreach ($emisiones as $emision) {
            $vencida = $emision->estaVencida($hoy);
            $actual = $fichas[$emision->version->idEspecificacion]->versionActual ?? null;
            $versionNueva = $actual !== null && (int) $actual->id !== (int) $emision->idVersion;

            if (!$vencida && !$versionNueva) {
                continue;
            }

            $porProyecto[$emision->idProyecto] ??= ['proyecto' => $emision->proyecto, 'vencidas' => 0, 'versionNueva' => 0];
            $porProyecto[$emision->idProyecto]['vencidas'] += $vencida ? 1 : 0;
            $porProyecto[$emision->idProyecto]['versionNueva'] += $versionNueva ? 1 : 0;
        }

        $filas = [];

        foreach ($porProyecto as $dato) {
            $partes = [];

            if ($dato['vencidas'] > 0) {
                $partes[] = $dato['vencidas'] === 1 ? '1 vencida' : "{$dato['vencidas']} vencidas";
            }
            if ($dato['versionNueva'] > 0) {
                $partes[] = $dato['versionNueva'] === 1 ? '1 con versión nueva de la ficha' : "{$dato['versionNueva']} con versión nueva de la ficha";
            }

            $filas[] = [
                'proyecto' => $dato['proyecto'],
                'detalle' => implode(' · ', $partes),
                'url' => ['/lista-items/index', 'id' => $dato['proyecto']->id],
            ];
        }

        return $filas;
    }

    /**
     * Solicitudes de cotizacion sin ninguna cotizacion registrada, de
     * proyectos que todavia eligen precios: sin presupuesto, en proceso u
     * observado. En revision, aprobado o cancelado ya no se esperan proformas.
     *
     * @return array<int, array{proyecto: Proyecto, detalle: string, url: array}>
     */
    private function solicitudesSinRespuesta(): array
    {
        $conRespuesta = Cotizacion::find()->select('idSolicitudCotizacion')->andWhere(['not', ['idSolicitudCotizacion' => null]]);
        $yaNoCotizan = Presupuesto::find()
            ->select('idProyecto')
            ->andWhere(['not in', 'estado', [Presupuesto::ESTADO_EN_PROCESO, Presupuesto::ESTADO_OBSERVADO]]);

        $solicitudes = SolicitudCotizacion::find()
            ->with('proyecto')
            ->andWhere(['not in', 'id', $conRespuesta])
            ->andWhere(['not in', 'idProyecto', $yaNoCotizan])
            ->orderBy(['idProyecto' => SORT_ASC, 'numero' => SORT_ASC])
            ->all();

        $porProyecto = [];

        foreach ($solicitudes as $solicitud) {
            $porProyecto[$solicitud->idProyecto]['proyecto'] = $solicitud->proyecto;
            $porProyecto[$solicitud->idProyecto]['solicitudes'][] = $solicitud->codigoCorto() . ' ' . $solicitud->nombre;
        }

        return array_values(array_map(static fn (array $dato) => [
            'proyecto' => $dato['proyecto'],
            'detalle' => implode(' · ', $dato['solicitudes']),
            'url' => ['/solicitud-cotizacion/index', 'id' => $dato['proyecto']->id],
        ], $porProyecto));
    }

    /**
     * Presupuesto mas reciente de cada proyecto, indexado por idProyecto.
     * Como la consulta viene ordenada por id ascendente, al recorrerla el
     * ultimo presupuesto de cada proyecto pisa a los anteriores.
     *
     * @param int[]|null $idsProyecto null = todos los proyectos
     * @return array<int, Presupuesto>
     */
    private function ultimoPresupuestoPorProyecto(?array $idsProyecto = null): array
    {
        $ultimos = [];

        $presupuestos = Presupuesto::find()
            ->andFilterWhere(['idProyecto' => $idsProyecto])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        foreach ($presupuestos as $presupuesto) {
            $ultimos[$presupuesto->idProyecto] = $presupuesto;
        }

        return $ultimos;
    }
}
