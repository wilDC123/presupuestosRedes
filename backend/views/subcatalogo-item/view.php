<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var backend\models\SubcatalogoItem $model
 */

$this->title = $model->descripcion;
$this->params['breadcrumbs'][] = ['label' => 'Subcatálogo de ítems', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="subcatalogo-item-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Está seguro de eliminar este ítem?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'idSigma',
            [
                'attribute' => 'categoria.nombre',
                'label' => 'Categoría',
            ],
            'descripcion',
            'unidadDefecto',
            [
                'attribute' => 'activo',
                'value' => $model->activo ? 'Sí' : 'No',
            ],
            'fechaRegistro',
        ],
    ]) ?>

</div>
