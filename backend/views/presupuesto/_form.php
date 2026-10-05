<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $model
 * @var yii\widgets\ActiveForm $form
 * @var array<int, string> $proyectos [id => "codigo — nombre"], ver ProyectoService::opcionesParaPresupuesto()
 */

// "estado" NO aparece en este formulario a proposito: lo maneja el sistema
// con el boton "Cambiar estado" de la vista, nunca se edita a mano para no
// saltarse las transiciones de estado validas.
?>
<div class="presupuesto-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'idProyecto')->dropDownList($proyectos, ['prompt' => 'Seleccione un proyecto', 'class' => 'form-select'])
        ->hint('Cada proyecto tiene un solo presupuesto: aquí aparecen los proyectos que todavía no tienen uno.') ?>

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
