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

$this->title = 'Especificaciones';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="especificacion-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Nueva especificación', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php Pjax::begin(); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => \yii\grid\SerialColumn::class],
            'codigoNet',
            'titulo',
            [
                'attribute' => 'subcatalogoItem.descripcion',
                'label' => 'Ítem de subcatálogo',
            ],
            [
                'attribute' => 'fechaEmision',
                'value' => static fn ($model) => $model->fechaEmisionFormateada(),
            ],
            [
                'attribute' => 'activo',
                'value' => static fn ($model) => $model->activo ? 'Sí' : 'No',
                'filter' => false,
            ],
            ['class' => ActionColumn::class],
        ],
    ]); ?>
    <?php Pjax::end(); ?>

</div>
