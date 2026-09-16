<?php

declare(strict_types=1);

use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\Pjax;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 */

$this->title = 'Presupuestos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="presupuesto-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Nuevo presupuesto', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php Pjax::begin(); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => \yii\grid\SerialColumn::class],
            [
                'attribute' => 'proyecto.nombre',
                'label' => 'Proyecto',
            ],
            'numeroPresupuesto',
            'numeroVersion',
            [
                'label' => 'Estado',
                'format' => 'raw',
                'value' => static fn ($model) => Html::tag('span', Html::encode($model->estadoLabel()), ['class' => $model->estadoBadgeClass()]),
            ],
            [
                'attribute' => 'fecha',
                'value' => static fn ($model) => $model->fechaFormateada(),
            ],
            ['class' => ActionColumn::class],
        ],
    ]); ?>
    <?php Pjax::end(); ?>

</div>
