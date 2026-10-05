<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CatalogoSigma;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\SubcatalogoItem;

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
     * @return array{proyectosActivos: int, proyectosSinPresupuesto: int, enRevision: int, montoAprobado: float, anio: int, itemsSubcatalogo: int, totalSigma: int}
     */
    public function indicadores(): array
    {
        $ultimos = $this->ultimoPresupuestoPorProyecto();
        $totalProyectos = (int) Proyecto::find()->count();
        $sinPresupuesto = $totalProyectos - count($ultimos);

        $abiertos = 0;

        foreach ($ultimos as $presupuesto) {
            if (in_array($presupuesto->estado, self::ESTADOS_ABIERTOS, true)) {
                $abiertos++;
            }
        }

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
