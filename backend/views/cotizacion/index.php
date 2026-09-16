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

$this->title = 'Cotizaciones';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="cotizacion-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Nueva cotización', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php Pjax::begin(); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => \yii\grid\SerialColumn::class],
            [
                'attribute' => 'proveedor.razonSocial',
                'label' => 'Proveedor',
            ],
            'numeroReferencia',
            [
                'attribute' => 'fecha',
                'value' => static fn ($model) => $model->fechaFormateada(),
            ],
            'validezDias',
            [
                'label' => 'PDF',
                'format' => 'raw',
                'value' => static function ($model) {
                    if (empty($model->documentoRuta)) {
                        return '<span class="text-muted">Sin adjunto</span>';
                    }

                    return Html::a('Ver PDF', Yii::getAlias('@web/' . $model->documentoRuta), ['target' => '_blank']);
                },
            ],
            ['class' => ActionColumn::class],
        ],
    ]); ?>
    <?php Pjax::end(); ?>

</div>
