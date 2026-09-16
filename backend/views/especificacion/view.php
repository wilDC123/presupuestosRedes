<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var backend\models\Especificacion $model
 */

$this->title = $model->titulo ?: $model->codigoNet ?: ('#' . $model->id);
$this->params['breadcrumbs'][] = ['label' => 'Especificaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="especificacion-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Está seguro de eliminar esta especificación?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'subcatalogoItem.descripcion',
                'label' => 'Ítem de subcatálogo',
            ],
            'codigoNet',
            'titulo',
            'dependencia',
            'modeloSugerido',
            'documentoRuta',
            [
                'attribute' => 'fechaEmision',
                'value' => $model->fechaEmisionFormateada(),
            ],
            [
                'attribute' => 'activo',
                'value' => $model->activo ? 'Sí' : 'No',
            ],
        ],
    ]) ?>

</div>
