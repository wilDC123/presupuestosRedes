<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Panel general. Todos los numeros vienen de DashboardService (datos reales
 * de la base), la vista solo los muestra.
 *
 * @var yii\web\View $this
 * @var array{proyectosActivos: int, proyectosFaseSuperior: int, proyectosSinPresupuesto: int, enRevision: int, montoAprobado: float, anio: int, itemsSubcatalogo: int, totalSigma: int} $indicadores
 * @var array<int, array{proyecto: backend\models\Proyecto, presupuesto: backend\models\Presupuesto|null, monto: float|null}> $proyectosRecientes
 */

$this->title = 'Panel general';
$formato = Yii::$app->formatter;
?>
<div class="site-index">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
        <div class="rd-acciones">
            <?= Html::a('+ Nuevo proyecto', ['/proyecto/create'], ['class' => 'btn btn-primary']) ?>
        </div>
    </div>

    <div class="rd-kpis">
        <div class="card rd-card-pad">
            <div class="kicker">Proyectos activos</div>
            <div class="rd-kpi-valor"><?= $formato->asInteger($indicadores['proyectosActivos']) ?></div>
            <div class="rd-kpi-nota"><?= $formato->asInteger($indicadores['proyectosFaseSuperior']) ?> en fase 2 o superior</div>
        </div>
        <div class="card rd-card-pad">
            <div class="kicker">En revisión</div>
            <div class="rd-kpi-valor"><?= $formato->asInteger($indicadores['enRevision']) ?></div>
            <div class="rd-kpi-nota">esperando aprobación</div>
        </div>
        <div class="card rd-card-pad">
            <div class="kicker">Monto aprobado (Bs.)</div>
            <div class="rd-kpi-valor"><?= $formato->asDecimal($indicadores['montoAprobado'], 2) ?></div>
            <div class="rd-kpi-nota">presupuestos aprobados <?= $indicadores['anio'] ?></div>
        </div>
        <div class="card rd-card-pad">
            <div class="kicker">Subcatálogo de redes</div>
            <div class="rd-kpi-valor"><?= $formato->asInteger($indicadores['itemsSubcatalogo']) ?></div>
            <div class="rd-kpi-nota">de <?= $formato->asInteger($indicadores['totalSigma']) ?> ítems SIGMA</div>
        </div>
    </div>

    <div class="rd-cols">

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Proyectos recientes</div>
                <?= Html::a('Ver todos', ['/proyecto/index']) ?>
            </div>

            <?php if ($proyectosRecientes === []): ?>
                <div class="rd-vacio">Todavía no hay proyectos registrados.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Proyecto</th>
                            <th>Fase</th>
                            <th>Estado</th>
                            <th class="rd-num">Monto Bs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proyectosRecientes as $fila): ?>
                            <tr>
                                <td class="mono"><?= Html::encode($fila['proyecto']->codigo ?: '—') ?></td>
                                <td><?= Html::a(Html::encode($fila['proyecto']->nombre), ['/proyecto/view', 'id' => $fila['proyecto']->id]) ?></td>
                                <td>Fase <?= (int) $fila['proyecto']->numeroFase ?></td>
                                <td>
                                    <?php if ($fila['presupuesto'] === null): ?>
                                        <span class="badge b-inactivo">Sin presupuesto</span>
                                    <?php else: ?>
                                        <span class="<?= $fila['presupuesto']->estadoBadgeClass() ?>"><?= Html::encode($fila['presupuesto']->estadoLabel()) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="rd-num mono"><?= $fila['monto'] === null ? '—' : $formato->asDecimal($fila['monto'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card rd-col-1 rd-card-pad">
            <div class="rd-card-titulo mb-2">Cotizaciones por vincular</div>
            <div class="rd-nota">
                Aún no disponible: se habilita cuando las proformas se registren por proyecto y
                cada fila se enlace con su ítem de la lista.
            </div>
        </div>

    </div>

</div>
