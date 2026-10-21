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

    <?= $form->field($model, 'fecha')->textInput([
        'value' => $model->fechaFormateada(),
        'placeholder' => 'dd/mm/aaaa',
        'maxlength' => 10,
    ])->hint('Formato: dd/mm/aaaa') ?>

    <?= $form->field($model, 'validezDias')->textInput(['type' => 'number']) ?>

    <?php if (!$model->isNewRecord && !empty($model->documentoRuta)): ?>
        <p>
            Archivo actual:
            <?= Html::a('Ver PDF', $model->urlDocumento(), ['target' => '_blank']) ?>
        </p>
    <?php endif; ?>

    <?= $form->field($model, 'documentoFile')->fileInput()->hint('Solo archivos PDF, máximo 10 MB. Dejar vacío para no cambiar el archivo actual.') ?>

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
