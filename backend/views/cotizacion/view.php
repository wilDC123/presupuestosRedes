<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var backend\models\Cotizacion $model
 */

$this->title = 'Cotización #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Cotizaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="cotizacion-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Está seguro de eliminar esta cotización?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'proveedor.razonSocial',
                'label' => 'Proveedor',
            ],
            'numeroReferencia',
            [
                'attribute' => 'fecha',
                'value' => $model->fechaFormateada(),
            ],
            'validezDias',
            [
                'label' => 'Documento',
                'format' => 'raw',
                'value' => empty($model->documentoRuta)
                    ? 'Sin archivo adjunto'
                    : Html::a('Ver / descargar PDF', Yii::getAlias('@web/' . $model->documentoRuta), ['target' => '_blank']),
            ],
        ],
    ]) ?>

    <h2>Líneas de la cotización</h2>

    <?php if (empty($model->cotizacionDetalles)): ?>
        <p class="text-muted">Esta cotización todavía no tiene líneas. Agrégalas desde "Editar".</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Ítem de subcatálogo</th>
                    <th>Especificación</th>
                    <th>Marca / modelo</th>
                    <th>Cantidad</th>
                    <th>Precio unitario</th>
                    <th>Precio total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($model->cotizacionDetalles as $detalle): ?>
                    <tr>
                        <td><?= Html::encode($detalle->subcatalogoItem->descripcion ?? '') ?></td>
                        <td><?= Html::encode($detalle->especificacion->titulo ?? '—') ?></td>
                        <td><?= Html::encode($detalle->marcaModelo ?? '') ?></td>
                        <td><?= Yii::$app->formatter->asDecimal($detalle->cantidad, 2) ?></td>
                        <td><?= Yii::$app->formatter->asDecimal($detalle->precioUnitario, 2) ?></td>
                        <td><?= Yii::$app->formatter->asDecimal($detalle->precioTotal, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
