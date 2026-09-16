<?php

declare(strict_types=1);

use backend\models\SubcatalogoItem;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\Especificacion $model
 * @var yii\widgets\ActiveForm $form
 */

$items = ArrayHelper::map(
    SubcatalogoItem::find()->orderBy(['descripcion' => SORT_ASC])->all(),
    'id',
    'descripcion'
);
?>
<div class="especificacion-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'idSubcatalogo')->dropDownList($items, ['prompt' => 'Seleccione un ítem de subcatálogo']) ?>

    <?= $form->field($model, 'codigoNet')->textInput(['maxlength' => 20, 'placeholder' => 'NET:832/26']) ?>

    <?= $form->field($model, 'titulo')->textInput(['maxlength' => 300]) ?>

    <?= $form->field($model, 'dependencia')->textInput(['maxlength' => 200]) ?>

    <?= $form->field($model, 'modeloSugerido')->textInput(['maxlength' => 200]) ?>

    <?= $form->field($model, 'documentoRuta')->textInput(['maxlength' => 500])->hint('Ruta al PDF/DOCX original con el detalle técnico completo. No se sube el archivo aquí, solo se guarda la referencia.') ?>

    <?= $form->field($model, 'fechaEmision')->textInput([
        'value' => $model->fechaEmisionFormateada(),
        'placeholder' => 'dd/mm/aaaa',
        'maxlength' => 10,
    ])->hint('Formato: dd/mm/aaaa') ?>

    <?= $form->field($model, 'activo')->checkbox() ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
