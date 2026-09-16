<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\Proyecto $model
 * @var yii\widgets\ActiveForm $form
 */
?>
<div class="proyecto-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'codigo')->textInput(['maxlength' => 50]) ?>

    <?= $form->field($model, 'nombre')->textInput(['maxlength' => 300]) ?>

    <?= $form->field($model, 'facultad')->textInput(['maxlength' => 200]) ?>

    <?= $form->field($model, 'edificio')->textInput(['maxlength' => 200]) ?>

    <?= $form->field($model, 'bloque')->textInput(['maxlength' => 100]) ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
