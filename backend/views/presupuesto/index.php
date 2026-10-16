<?php

declare(strict_types=1);

use yii\grid\GridView;
use yii\helpers\Html;

/**
 * Menu "Presupuestos": los presupuestos de todos los proyectos, para
 * consultar. Cada uno se elabora en el paso "Presupuesto" de su proyecto.
 *
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var array<int, float> $totales [idPresupuesto => total], ver PresupuestoService::totalesPorPresupuesto()
 */

$this->title = 'Presupuestos';
$this->params['breadcrumbs'][] = $this->title;
$formato = Yii::$app->formatter;
?>
<div class="presupuesto-index">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">El presupuesto de cada proyecto se elabora en su paso "Presupuesto".</div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('Ir a proyectos', ['/proyecto/index'], ['class' => 'btn btn-outline']) ?>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table'],
        'summary' => 'Mostrando {begin}–{end} de {totalCount} presupuestos',
        'emptyText' => 'Todavía no hay presupuestos. Se crean al entrar al paso "Presupuesto" de un proyecto.',
        'columns' => [
            [
                'label' => 'Código',
                'contentOptions' => ['class' => 'mono'],
                'value' => static fn ($model) => $model->proyecto->codigo ?? '—',
            ],
            [
                'label' => 'Proyecto',
                'format' => 'raw',
                'value' => static fn ($model) => Html::a(Html::encode($model->proyecto->nombre ?? '—'), ['/elaborar-presupuesto/index', 'idProyecto' => $model->idProyecto])
                    . ' <span class="pill-fase">Fase ' . (int) ($model->proyecto->numeroFase ?? 1) . '</span>',
            ],
            [
                'attribute' => 'numeroPresupuesto',
                'value' => static fn ($model) => $model->numeroPresupuesto ?: '—',
            ],
            [
                'label' => 'Estado',
                'format' => 'raw',
                'value' => static fn ($model) => Html::tag('span', Html::encode($model->estadoLabel()), ['class' => $model->estadoBadgeClass()]),
            ],
            [
                'attribute' => 'fecha',
                'value' => static fn ($model) => $model->fechaFormateada() ?: '—',
            ],
            [
                'label' => 'Total (Bs.)',
                'headerOptions' => ['class' => 'rd-num'],
                'contentOptions' => ['class' => 'rd-num mono'],
                'value' => static fn ($model) => $formato->asDecimal($totales[$model->id] ?? 0, 2),
            ],
            [
                'format' => 'raw',
                'value' => static fn ($model) => '<div class="rd-acciones">'
                    . Html::a('Abrir', ['/elaborar-presupuesto/index', 'idProyecto' => $model->idProyecto], ['class' => 'btn btn-outline btn-sm'])
                    . Html::a('PDF', ['pdf', 'id' => $model->id], ['class' => 'btn btn-outline btn-sm', 'target' => '_blank'])
                    . ($model->esBorrador()
                        ? Html::a('Eliminar', ['delete', 'id' => $model->id], [
                            'class' => 'btn btn-danger btn-sm',
                            'data' => ['confirm' => '¿Eliminar este presupuesto en proceso y sus precios elegidos?', 'method' => 'post'],
                        ])
                        : '')
                    . '</div>',
            ],
        ],
    ]) ?>

</div>
