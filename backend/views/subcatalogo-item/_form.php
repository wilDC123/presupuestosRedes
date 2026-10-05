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
 * @var string|null $partidaSigma partida que SIGMA tiene para el idSigma del item (null si no se pudo obtener)
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

    <?php if ($partidaSigma !== null): ?>
        <?php // Copiada de SIGMA: se muestra pero no se envia (disabled); el servicio la vuelve a copiar al guardar. ?>
        <div class="form-group">
            <label class="control-label">Partida</label>
            <input type="text" class="form-control" value="<?= Html::encode($partidaSigma) ?>" disabled>
            <div class="hint-block">Copiada del catálogo SIGMA (IdGasto). No se edita a mano.</div>
        </div>
    <?php else: ?>
        <?php // Sin validacion JS: si el usuario escribe un ID Sigma a mano, recien el servidor sabe si SIGMA trae la partida. ?>
        <?= $form->field($model, 'partida', ['enableClientValidation' => false])
            ->textInput(['maxlength' => 5])
            ->hint('5 dígitos (ej. 25200). Obligatoria a mano porque este ítem no tiene partida en el catálogo SIGMA.') ?>
    <?php endif; ?>

    <?= $form->field($model, 'activo')->checkbox() ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
