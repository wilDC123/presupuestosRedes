<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Formulario "Abrir nueva versión" de una fase aprobada: copia la lista y el
 * presupuesto (precios tal cual, sin recalcular) a una fila nueva de
 * Proyecto; la fase vieja queda de solo lectura. VersionProyectoService.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $version nueva, todavia sin guardar
 * @var backend\models\Proyecto $fase la fase aprobada sobre la que se abre
 */

$this->title = 'Abrir nueva versión';
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $fase->identificador(), 'url' => ['view', 'id' => $fase->id]];
$this->params['breadcrumbs'][] = 'Nueva versión';
?>
<div class="proyecto-version">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                <?= Html::encode($fase->identificador() . ' — ' . $fase->nombre) ?>
                · actualmente <strong>versión <?= Html::encode($fase->numeroVersionTexto()) ?></strong>
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('Cancelar', ['view', 'id' => $fase->id], ['class' => 'btn btn-outline']) ?>
            <?= Html::submitButton('Abrir versión →', ['class' => 'btn btn-primary', 'form' => 'version-form']) ?>
        </div>
    </div>

    <?php $form = ActiveForm::begin(['id' => 'version-form']); ?>

    <div class="rd-cols">
        <div class="card rd-col-2 rd-card-pad">
            <div class="rd-card-titulo mb-2">Tipo de versión</div>

            <div class="form-check mb-2">
                <input type="radio" name="tipoVersion" value="menor" class="form-check-input" id="tipo-version-menor" checked>
                <label class="form-check-label" for="tipo-version-menor">Subversión — cambios menores</label>
                <div class="rd-nota">Actualizar precios, corregir cantidades, especificaciones o cambiar un ítem por otro equivalente.</div>
            </div>
            <div class="form-check mb-3">
                <input type="radio" name="tipoVersion" value="mayor" class="form-check-input" id="tipo-version-mayor">
                <label class="form-check-label" for="tipo-version-mayor">Versión nueva — cambios importantes</label>
                <div class="rd-nota">Agregar o quitar ítems, agregar condiciones previas o cambiar las condiciones de la fase.</div>
            </div>

            <?= $form->field($version, 'motivoVersion')->textarea(['rows' => 3, 'maxlength' => 1000])
                ->hint('Por qué se abre (ej. "Cotizaciones vencidas por demora en el financiamiento; se ajustan ítems").') ?>
        </div>

        <div class="card rd-col-1 rd-card-pad">
            <div class="rd-card-titulo mb-2">Cómo funciona</div>
            <p class="rd-nota">
                Se copian la lista de ítems y el presupuesto de esta fase a una versión nueva, con los <strong>precios
                tal cual</strong> (no se recalculan): usted los corrige a mano donde haga falta, o agrega y quita ítems.
            </p>
            <p class="rd-nota">
                Esta versión (<?= Html::encode($fase->numeroVersionTexto()) ?>) queda de solo lectura, sin borrarse: sus
                especificaciones emitidas y su carta de entrega quedan como están.
            </p>
            <p class="rd-nota mb-0">
                La versión nueva saca su propio código de lista y de presupuesto; el código de la ficha técnica no cambia.
            </p>
        </div>
    </div>

    <?php ActiveForm::end(); ?>

</div>
