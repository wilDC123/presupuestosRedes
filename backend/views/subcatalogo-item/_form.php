<?php

declare(strict_types=1);

use backend\models\Categoria;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\SubcatalogoItem $model
 * @var yii\widgets\ActiveForm $form
 */

$categorias = ArrayHelper::map(
    Categoria::find()->orderBy(['orden' => SORT_ASC])->all(),
    'id',
    'nombre'
);
?>
<div class="subcatalogo-item-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'idCategoria')->dropDownList($categorias, ['prompt' => 'Seleccione una categoría']) ?>

    <?= $form->field($model, 'descripcion')->textInput(['maxlength' => 300]) ?>

    <?= $form->field($model, 'unidadDefecto')->textInput(['maxlength' => 20]) ?>

    <?= $form->field($model, 'idSigma')->textInput(['maxlength' => 36])->hint('Solo lectura del catálogo institucional SIGMA. Dejar vacío si el ítem no proviene de ahí.') ?>

    <?= $form->field($model, 'activo')->checkbox() ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
