<?php

declare(strict_types=1);

use backend\models\Proyecto;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\ComputoMetrico $model
 * @var yii\widgets\ActiveForm $form
 */

$proyectos = ArrayHelper::map(
    Proyecto::find()->orderBy(['nombre' => SORT_ASC])->all(),
    'id',
    'nombre'
);
?>
<div class="computo-metrico-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'idProyecto')->dropDownList($proyectos, ['prompt' => 'Seleccione un proyecto']) ?>

    <?= $form->field($model, 'descripcion')->textInput(['maxlength' => 300, 'placeholder' => 'Ej. Metros de cable horizontal Bloque B']) ?>

    <?= $form->field($model, 'valor')->textInput(['type' => 'number', 'step' => '0.01']) ?>

    <?= $form->field($model, 'unidad')->textInput(['maxlength' => 20, 'placeholder' => 'Ej. Metro, Unidad, Caja']) ?>

    <?= $form->field($model, 'referenciaOrigen')->textInput(['maxlength' => 500])->hint('Especifica el plano o documento que sustenta este valor.') ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
