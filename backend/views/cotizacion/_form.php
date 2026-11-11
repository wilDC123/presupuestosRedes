<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\Cotizacion $model
 * @var array<int, string> $proveedores [id => razon social], solo ACTIVOS (mas el de la cotizacion). Ver ProveedorService::opcionesActivos()
 * @var backend\models\CotizacionDetalle[]|null $modelsDetalle Solo se pasa desde update.php
 * @var array<int, string> $items Solo desde update.php. Ver SubcatalogoItemService::opciones()
 * @var array $especificaciones Solo desde update.php. Ver EspecificacionService::selector()
 * @var yii\widgets\ActiveForm $form
 */
?>
<div class="cotizacion-form">

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <?= $form->field($model, 'idProveedor')->dropDownList($proveedores, ['prompt' => 'Seleccione un proveedor']) ?>

    <?= $form->field($model, 'numeroReferencia')->textInput(['maxlength' => 100]) ?>

    <?php // type="date": calendario del navegador; envia aaaa-mm-dd, que el modelo guarda tal cual. ?>
    <?= $form->field($model, 'fecha')->input('date') ?>

    <?= $form->field($model, 'validezDias')->textInput(['type' => 'number']) ?>

    <?php if (!$model->isNewRecord && !empty($model->documentoRuta)): ?>
        <p>
            Proforma actual:
            <?= Html::a($model->textoDocumento(), $model->urlDocumento(), ['target' => '_blank', 'rel' => 'noopener']) ?>
        </p>
    <?php endif; ?>

    <?php // La proforma: el enlace donde esta guardada (Drive) O el PDF subido, 2026-11-11. ?>
    <?= $form->field($model, 'documentoEnlace')->textInput(['maxlength' => 500, 'placeholder' => 'https://drive.google.com/…'])
        ->hint('Si la proforma está en el Drive, pegue aquí su enlace. Bórrelo para quitarlo.') ?>

    <?= $form->field($model, 'documentoFile')->fileInput()->hint('O suba el PDF (máximo 10 MB). Use una sola de las dos. Dejar vacío para no cambiar el archivo actual.') ?>

    <?php if ($modelsDetalle !== null): ?>
        <?= $this->render('_detalle-form', [
            'modelsDetalle' => $modelsDetalle,
            'items' => $items,
            'especificaciones' => $especificaciones,
        ]) ?>
    <?php endif; ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
