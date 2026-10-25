<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\TipoProyecto $model
 * @var yii\widgets\ActiveForm $form
 */
?>
<div class="tipo-proyecto-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'nombre')
        ->textInput(['maxlength' => 100, 'placeholder' => 'Ej. Videovigilancia'])
        ->hint('Corregir el nombre lo cambia también en los proyectos y plantillas que ya lo usan.') ?>

    <?php if (!$model->isNewRecord): ?>
        <?= $form->field($model, 'activo')->checkbox()->hint('Un tipo inactivo ya no aparece para elegir, pero los proyectos y plantillas que lo usan lo conservan.') ?>
    <?php endif; ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
