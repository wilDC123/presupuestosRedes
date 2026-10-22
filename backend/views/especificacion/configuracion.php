<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Configuracion del formulario de especificacion tecnica: los textos fijos
 * que llevan todos los formularios (el correlativo RD-PI-ET se fija en
 * Codificación de documentos). Valen para las emisiones NUEVAS: cada
 * emision guarda su propia copia, asi que cambiar algo aqui no altera los
 * documentos ya emitidos.
 *
 * @var yii\web\View $this
 * @var backend\models\ConfiguracionEspecificacion $configuracion
 */

$this->title = 'Configuración del formulario de especificación';
$this->params['breadcrumbs'][] = ['label' => 'Librería de especificaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Configuración';
?>
<div class="especificacion-configuracion">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                Lo que cambie aquí vale para los formularios que se emitan desde ahora. Los ya emitidos no cambian.
            </div>
        </div>
    </div>

    <?php $form = ActiveForm::begin(); ?>

    <p class="rd-nota">
        El código de cada formulario (RD-PI-ET-001, RD-PI-ET-002…) se fija en
        <?= Html::a('Codificación de documentos', ['/codificacion/index']) ?>.
    </p>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Textos del formulario</div></div>
        <div class="rd-card-pad">
            <?= $form->field($configuracion, 'textoCabecera')->textarea(['rows' => 2, 'maxlength' => 500])
                ->hint('Una línea por renglón (ej. el nombre de la universidad y "D.T.I.C.").') ?>
            <?= $form->field($configuracion, 'textoInstrucciones')->textarea(['rows' => 4, 'maxlength' => 1000]) ?>
            <?= $form->field($configuracion, 'garantiaPorDefecto')->textarea(['rows' => 2, 'maxlength' => 500])
                ->hint('Se usa en las fichas que no tienen una garantía propia.') ?>
            <?= $form->field($configuracion, 'textoNotaFinal')->textarea(['rows' => 2, 'maxlength' => 500]) ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Valores por defecto de cada emisión</div></div>
        <div class="rd-card-pad">
            <div class="row">
                <div class="col-md-4">
                    <?= $form->field($configuracion, 'vigenciaDias')->textInput(['type' => 'number', 'min' => 1])
                        ->hint('"Formulario válido de N días desde su emisión". Pasado ese plazo el sistema avisa que hay que reemitirlo.') ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($configuracion, 'validezOfertaDias')->textInput(['type' => 'number', 'min' => 1]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($configuracion, 'tiempoEntregaDias')->textInput(['type' => 'number', 'min' => 1]) ?>
                </div>
            </div>
            <p class="rd-nota mb-0">El lugar de entrega por defecto es la facultad del proyecto; se puede cambiar al reemitir.
                La validez de la oferta y el tiempo de entrega también salen en la solicitud de cotización, como
                "(mínimo N días)" y "(máximo N días)".</p>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
