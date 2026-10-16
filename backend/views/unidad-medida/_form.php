<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\UnidadMedida $model
 * @var yii\widgets\ActiveForm $form
 */
?>
<div class="unidad-medida-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'nombre')->textInput(['maxlength' => 50, 'placeholder' => 'Ej. Metro']) ?>

    <?= $form->field($model, 'abreviatura')
        ->textInput(['maxlength' => 10, 'placeholder' => 'Ej. m'])
        ->hint('Es lo que sale en la lista, el presupuesto y el PDF. Cambiarla no modifica las listas ni presupuestos ya hechos: guardan la abreviatura que tenían.') ?>

    <?php if (!$model->isNewRecord): ?>
        <?= $form->field($model, 'activo')->checkbox()->hint('Una unidad inactiva ya no aparece para elegir, pero los ítems que la usan la conservan.') ?>
    <?php endif; ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
