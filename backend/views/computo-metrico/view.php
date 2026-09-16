<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var backend\models\ComputoMetrico $model
 */

$this->title = 'Cómputo métrico #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Cómputos métricos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="computo-metrico-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Está seguro de eliminar este cómputo métrico?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'proyecto.nombre',
                'label' => 'Proyecto',
            ],
            'descripcion',
            'valor',
            'unidad',
            'referenciaOrigen',
        ],
    ]) ?>

</div>
