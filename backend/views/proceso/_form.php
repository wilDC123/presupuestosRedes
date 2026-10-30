<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\Proceso $model
 * @var yii\widgets\ActiveForm $form
 */
?>
<div class="proceso-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'codigo')
        ->textInput(['maxlength' => 2, 'placeholder' => 'Ej. PI', 'style' => 'max-width: 120px; text-transform: uppercase;'])
        ->hint('Dos letras, como en la tabla de codificación de procesos del lineamiento. Irá en el código de los documentos: '
            . 'cuando un proyecto ya usa el proceso, no se puede cambiar.') ?>

    <?= $form->field($model, 'nombre')
        ->textInput(['maxlength' => 200, 'placeholder' => 'Ej. Proyectos de infraestructura de redes y servicios']) ?>

    <?php if (!$model->isNewRecord): ?>
        <?= $form->field($model, 'activo')->checkbox()->hint('Un proceso inactivo ya no aparece para elegir, pero los proyectos que lo usan lo conservan.') ?>
    <?php endif; ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
