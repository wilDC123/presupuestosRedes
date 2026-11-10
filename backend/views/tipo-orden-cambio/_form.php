<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\TipoOrdenCambio $model
 * @var yii\widgets\ActiveForm $form
 */
?>
<div class="tipo-orden-cambio-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'nombre')
        ->textInput(['maxlength' => 150, 'placeholder' => 'Ej. Cambios en el alcance de los proyectos'])
        ->hint('Corregir el nombre lo cambia también en las órdenes de cambio que lo usan.') ?>

    <?php if (!$model->isNewRecord): ?>
        <?= $form->field($model, 'activo')->checkbox()->hint('Un tipo inactivo ya no aparece para elegir, pero las órdenes de cambio que lo tienen lo conservan.') ?>
    <?php endif; ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
