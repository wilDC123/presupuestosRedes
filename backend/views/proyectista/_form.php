<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\Proyectista $model
 * @var yii\widgets\ActiveForm $form
 */
?>
<div class="proyectista-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'nombre')
        ->textInput(['maxlength' => 200, 'placeholder' => 'Ej. Ing. Paola Rojas'])
        ->hint('Los proyectos ya guardados conservan el nombre con el que se asignó.') ?>

    <?php if (!$model->isNewRecord): ?>
        <?= $form->field($model, 'activo')->checkbox()->hint('Un proyectista inactivo ya no aparece para elegir, pero los proyectos que lo tienen lo conservan.') ?>
    <?php endif; ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
