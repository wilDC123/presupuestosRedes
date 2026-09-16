<?php

declare(strict_types=1);

use backend\models\Proyecto;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $model
 * @var yii\widgets\ActiveForm $form
 */

// idVersionAnterior, numeroVersion y estado NO aparecen en este formulario a
// proposito: los maneja el sistema (crearNuevaVersion() y el boton "Cambiar
// estado" de la vista), nunca se editan a mano para no romper el versionado
// ni saltarse las transiciones de estado validas.

$proyectos = ArrayHelper::map(
    Proyecto::find()->orderBy(['nombre' => SORT_ASC])->all(),
    'id',
    'nombre'
);
?>
<div class="presupuesto-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'idProyecto')->dropDownList($proyectos, ['prompt' => 'Seleccione un proyecto']) ?>

    <?= $form->field($model, 'numeroPresupuesto')->textInput(['maxlength' => 20]) ?>

    <?= $form->field($model, 'oficioDtic')->textInput(['maxlength' => 100]) ?>

    <?= $form->field($model, 'areaIntervencion')->textInput(['maxlength' => 300]) ?>

    <?= $form->field($model, 'fecha')->textInput([
        'value' => $model->fechaFormateada(),
        'placeholder' => 'dd/mm/aaaa',
        'maxlength' => 10,
    ])->hint('Formato: dd/mm/aaaa') ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
