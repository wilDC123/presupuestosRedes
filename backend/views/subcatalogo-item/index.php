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

$this->title = 'Subcatálogo de ítems';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="subcatalogo-item-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Nuevo ítem', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php Pjax::begin(); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => \yii\grid\SerialColumn::class],
            'descripcion',
            [
                'attribute' => 'categoria.nombre',
                'label' => 'Categoría',
            ],
            'unidadDefecto',
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
