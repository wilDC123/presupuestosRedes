<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\Proveedor $model
 * @var yii\widgets\ActiveForm $form
 */
?>
<div class="proveedor-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'razonSocial')->textInput(['maxlength' => 200]) ?>

    <?= $form->field($model, 'nit')->textInput(['maxlength' => 50]) ?>

    <?= $form->field($model, 'contacto')->textInput(['maxlength' => 200]) ?>

    <?= $form->field($model, 'telefono')->textInput(['maxlength' => 100]) ?>

    <?= $form->field($model, 'direccion')->textarea(['maxlength' => 300]) ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
