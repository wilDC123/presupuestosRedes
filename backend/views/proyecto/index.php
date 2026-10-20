<?php

declare(strict_types=1);

use yii\grid\GridView;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider proyectos con su presupuesto y tipo ya cargados
 */

$this->title = 'Proyectos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="proyecto-index">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
        <div class="rd-acciones">
            <?= Html::a('+ Nuevo proyecto', ['create'], ['class' => 'btn btn-primary']) ?>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table'],
        'summary' => 'Mostrando {begin}–{end} de {totalCount} proyectos',
        'emptyText' => 'Todavía no hay proyectos registrados.',
        'columns' => [
            [
                'attribute' => 'codigo',
                'label' => 'Código',
                'contentOptions' => ['class' => 'mono'],
                'value' => static fn ($model) => $model->codigo ?: '—',
            ],
            [
                'attribute' => 'nombre',
                'label' => 'Proyecto',
                'format' => 'raw',
                'value' => static fn ($model) => Html::a(Html::encode($model->nombre), ['view', 'id' => $model->id]),
            ],
            [
                'label' => 'Tipo',
                'value' => static fn ($model) => $model->tipoProyecto?->nombre ?? '—',
            ],
            [
                'attribute' => 'numeroFase',
                'label' => 'Fase',
                'format' => 'raw',
                'value' => static fn ($model) => '<span class="pill-fase">Fase ' . (int) $model->numeroFase . '</span>',
            ],
            [
                'attribute' => 'facultad',
                'value' => static fn ($model) => $model->facultad ?: '—',
            ],
            [
                'label' => 'Presupuesto',
                'format' => 'raw',
                'value' => static fn ($model) => $model->presupuesto === null
                    ? '<span class="badge b-inactivo">Sin presupuesto</span>'
                    : Html::tag('span', Html::encode($model->presupuesto->estadoLabel()), ['class' => $model->presupuesto->estadoBadgeClass()]),
            ],
            [
                'attribute' => 'fechaCreacion',
                'label' => 'Creado',
                'format' => ['date', 'php:d/m/Y'],
            ],
        ],
    ]); ?>

</div>
