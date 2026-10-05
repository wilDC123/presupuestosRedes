<?php

declare(strict_types=1);

use backend\models\Presupuesto;
use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $model
 * @var array $resumenPartidas Ver PresupuestoService::resumenPorPartida()
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
        <?= Html::a('Generar PDF', ['pdf', 'id' => $model->id], ['class' => 'btn btn-outline-secondary', 'target' => '_blank']) ?>
        <?php if ($model->puedeEditarse()): ?>
            <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?php else: ?>
            <span class="text-muted">Este presupuesto está "<?= Html::encode($model->estadoLabel()) ?>" y no se puede editar.</span>
            <?php if ($model->estado === Presupuesto::ESTADO_APROBADO): ?>
                <?= Html::a('Crear proyecto de fase siguiente →', ['/proyecto/create', 'idAnterior' => $model->idProyecto], ['class' => 'btn btn-primary']) ?>
            <?php endif; ?>
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
                'label' => 'Proyecto',
                'format' => 'raw',
                'value' => Html::a(Html::encode($model->proyecto->etiqueta()), ['/proyecto/view', 'id' => $model->idProyecto])
                    . ' <span class="pill-fase">Fase ' . (int) $model->proyecto->numeroFase . '</span>',
            ],
            'numeroPresupuesto',
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

    <?= $this->render('_resumen-partidas', ['resumenPartidas' => $resumenPartidas]) ?>

</div>
