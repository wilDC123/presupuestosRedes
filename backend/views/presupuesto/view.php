<?php

declare(strict_types=1);

use backend\models\Presupuesto;
use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $model
 */

$this->title = 'Presupuesto #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Presupuestos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$estados = Presupuesto::estados();
$transicionesValidas = Presupuesto::transicionesPermitidas()[$model->estado] ?? [];
?>
<div class="presupuesto-view">

    <h1>
        <?= Html::encode($this->title) ?>
        <?= Html::tag('span', Html::encode($model->estadoLabel()), ['class' => $model->estadoBadgeClass()]) ?>
    </h1>

    <p>
        <?= Html::a('Ver líneas del presupuesto', ['detalle', 'id' => $model->id], ['class' => 'btn btn-secondary']) ?>
        <?php if ($model->puedeEditarse()): ?>
            <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?php else: ?>
            <span class="text-muted">Este presupuesto está "<?= Html::encode($model->estadoLabel()) ?>" y no se puede editar directamente.</span>
            <?= Html::a('Crear nueva versión', ['crear-nueva-version', 'id' => $model->id], [
                'class' => 'btn btn-warning',
                'data' => [
                    'confirm' => '¿Crear una nueva versión de este presupuesto? Podrá agregar o modificar líneas en la nueva versión.',
                    'method' => 'post',
                ],
            ]) ?>
        <?php endif; ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Está seguro de eliminar este presupuesto?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?php if (!empty($transicionesValidas)): ?>
        <p>
            Cambiar estado a:
            <?php foreach ($transicionesValidas as $estadoDestino): ?>
                <?= Html::beginForm(['cambiar-estado', 'id' => $model->id], 'post', ['style' => 'display:inline-block; margin-right: 5px;']) ?>
                    <?= Html::hiddenInput('estado', $estadoDestino) ?>
                    <?= Html::submitButton(Html::encode($estados[$estadoDestino]), ['class' => 'btn btn-secondary']) ?>
                <?= Html::endForm() ?>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'proyecto.nombre',
                'label' => 'Proyecto',
            ],
            'numeroPresupuesto',
            'numeroVersion',
            [
                'label' => 'Versión anterior',
                'format' => 'raw',
                'value' => $model->versionAnterior === null
                    ? 'Ninguna (primera versión)'
                    : Html::a('Presupuesto #' . $model->versionAnterior->id, ['view', 'id' => $model->versionAnterior->id]),
            ],
            [
                'label' => 'Versión siguiente',
                'format' => 'raw',
                'value' => $model->versionSiguiente === null
                    ? 'Ninguna (última versión)'
                    : Html::a('Presupuesto #' . $model->versionSiguiente->id, ['view', 'id' => $model->versionSiguiente->id]),
            ],
            'oficioDtic',
            'areaIntervencion',
            [
                'attribute' => 'estado',
                'format' => 'raw',
                'value' => Html::tag('span', Html::encode($model->estadoLabel()), ['class' => $model->estadoBadgeClass()]),
            ],
            [
                'attribute' => 'fecha',
                'value' => $model->fechaFormateada(),
            ],
            'fechaCreacion',
        ],
    ]) ?>

</div>
