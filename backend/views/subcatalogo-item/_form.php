<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\SubcatalogoItem $model
 * @var yii\widgets\ActiveForm $form
 * @var array<int, string> $categorias [id => nombre], ver CategoriaService::opciones()
 * @var array<int, string> $unidades [id => "Metro (m)"], ver UnidadMedidaService::opcionesPorId()
 * @var backend\models\CatalogoSigma|null $sigma fila de dbo.CatalogoSigma del idSigma del item (null si no viene de SIGMA)
 * @var string[] $usadoPor nombres DTIC de otros items que apuntan al mismo idSigma
 * @var string|null $partidaSigma partida que SIGMA tiene para el idSigma del item (null si no se pudo obtener)
 */
?>
<div class="subcatalogo-item-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'idCategoria')->dropDownList($categorias, ['prompt' => 'Seleccione una categoría']) ?>

    <?= $form->field($model, 'descripcion')
        ->textInput(['maxlength' => 300])
        ->hint('Nombre corto y genérico que usa la DTIC (ej. "Switch PoE", "Regleta tapacable"). Las medidas o detalles de una compra concreta van en la observación de la línea del presupuesto, no aquí.') ?>

    <?php if ($sigma !== null): ?>
        <div class="alert alert-secondary">
            <strong>Ítem SIGMA elegido (solo lectura):</strong><br>
            <?= Html::encode($sigma->Descripcion) ?><br>
            Partida: <?= Html::encode($partidaSigma ?? 'SIGMA no la indica') ?>
        </div>
    <?php endif; ?>

    <?php if ($usadoPor !== []): ?>
        <div class="alert alert-warning">
            Este ítem SIGMA ya está usado por: <?= Html::encode(implode(', ', $usadoPor)) ?>.
            Puede guardarlo igual si se trata de un ítem DTIC distinto.
        </div>
    <?php endif; ?>

    <?= $form->field($model, 'idUnidadMedida')
        ->dropDownList($unidades, ['prompt' => 'Seleccione una unidad'])
        ->hint('Se copia a la lista del proyecto al agregar el ítem. Si falta una, créela en ' . Html::a('Unidades de medida', ['/unidad-medida/index'], ['target' => '_blank']) . '.') ?>

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
