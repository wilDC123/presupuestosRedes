<?php

declare(strict_types=1);

use backend\models\Proveedor;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\Cotizacion $model
 * @var backend\models\CotizacionDetalle[]|null $modelsDetalle Solo se pasa desde update.php
 * @var yii\widgets\ActiveForm $form
 */

$proveedores = ArrayHelper::map(
    Proveedor::find()->orderBy(['razonSocial' => SORT_ASC])->all(),
    'id',
    'razonSocial'
);
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
            <?= Html::a('Ver PDF', Yii::getAlias('@web/' . $model->documentoRuta), ['target' => '_blank']) ?>
        </p>
    <?php endif; ?>

    <?= $form->field($model, 'documentoFile')->fileInput()->hint('Solo archivos PDF, máximo 10 MB. Dejar vacío para no cambiar el archivo actual.') ?>

    <?php if ($modelsDetalle !== null): ?>
        <?= $this->render('_detalle-form', ['modelsDetalle' => $modelsDetalle]) ?>
    <?php endif; ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
