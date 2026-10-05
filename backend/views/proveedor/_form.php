<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Formulario de proveedor (crear y editar). Los botones Cancelar / Guardar
 * van en el encabezado de la pagina, fuera del <form>; el atributo HTML
 * form="proveedor-form" los conecta con este formulario.
 *
 * @var yii\web\View $this
 * @var backend\models\Proveedor $model
 * @var yii\widgets\ActiveForm $form
 */
?>
<div class="rd-cols proveedor-form">

    <div class="card rd-col-2 rd-card-pad">
        <div class="rd-card-titulo mb-3">Datos del proveedor</div>

        <?php $form = ActiveForm::begin(['id' => 'proveedor-form']); ?>

        <div class="field">
            <label>ID</label>
            <input type="text" class="form-control" disabled
                   value="<?= $model->isNewRecord ? 'Se genera automáticamente' : Html::encode((string) $model->id) ?>">
        </div>

        <?= $form->field($model, 'razonSocial')
            ->textInput(['maxlength' => 200, 'placeholder' => 'Ej. Fibra Andina SRL'])
            ->hint('Nombre legal o comercial con el que se identifica al proveedor.') ?>

        <?= $form->field($model, 'nit')->textInput(['maxlength' => 50, 'placeholder' => '0000000000']) ?>

        <?= $form->field($model, 'contacto')->textInput(['maxlength' => 200, 'placeholder' => 'Nombre de la persona de contacto']) ?>

        <?= $form->field($model, 'telefono')->textInput(['maxlength' => 100, 'placeholder' => '+591 4 ...']) ?>

        <?= $form->field($model, 'email')->textInput(['maxlength' => 200, 'placeholder' => 'ventas@proveedor.bo']) ?>

        <?= $form->field($model, 'direccion')->textInput(['maxlength' => 300, 'placeholder' => 'Ciudad y dirección']) ?>

        <?= $form->field($model, 'activo')->checkbox()
            ->hint('Un proveedor inactivo se conserva con su historial de cotizaciones.') ?>

        <?php ActiveForm::end(); ?>
    </div>

    <div class="card rd-col-1 rd-card-pad">
        <div class="kicker mb-2">Sobre este registro</div>
        <div class="rd-nota">
            El proveedor queda disponible de inmediato para vincularlo a cotizaciones dentro de
            cualquier proyecto. Si ya tiene cotizaciones registradas no se puede eliminar:
            en ese caso se marca como inactivo.
        </div>
    </div>

</div>
