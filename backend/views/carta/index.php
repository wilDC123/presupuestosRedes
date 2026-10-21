<?php

declare(strict_types=1);

use backend\models\CartaPresupuesto;
use yii\helpers\Html;
use yii\web\JqueryAsset;
use yii\widgets\ActiveForm;

/**
 * Carta de entrega del presupuesto: se escribe en cualquier momento (no hace
 * falta que el presupuesto este aprobado) y se imprime cuando el presupuesto
 * ya tiene numero de oficio y numero de presupuesto. Ver CartaService.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * @var backend\models\Presupuesto $presupuesto
 * @var backend\models\CartaPresupuesto $carta guardada, o nueva ya llena sin guardar
 * @var string[] $falta lo que falta para imprimir (vacio = se puede)
 */

$this->title = 'Carta de entrega';
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['/proyecto/index']];
$this->params['breadcrumbs'][] = ['label' => $proyecto->codigo ?: $proyecto->nombre, 'url' => ['/proyecto/view', 'id' => $proyecto->id]];
$this->params['breadcrumbs'][] = ['label' => 'Presupuesto', 'url' => ['/elaborar-presupuesto/index', 'idProyecto' => $proyecto->id]];
$this->params['breadcrumbs'][] = 'Carta de entrega';

// Cambia DECANO <-> DECANA en el cargo al elegir otro tratamiento.
$this->registerJsFile('@web/js/carta.js', ['depends' => [JqueryAsset::class]]);

$marcadores = 'Al imprimir, {oficio} y {numeroPresupuesto} se reemplazan con los del presupuesto, y {especificaciones} con los números de los formularios NET vigentes del proyecto, separados por comas (ej. 896, 897, 900).';
?>
<div class="carta-index">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                <?= Html::encode($proyecto->etiqueta()) ?> · Presupuesto #<?= $presupuesto->id ?>
                <?= $carta->isNewRecord ? '· <strong>Todavía no se guardó</strong>: los textos vienen de la configuración.' : '' ?>
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('← Presupuesto', ['/elaborar-presupuesto/index', 'idProyecto' => $proyecto->id], ['class' => 'btn btn-outline']) ?>
            <?php if ($falta === []): ?>
                <?= Html::a('Imprimir carta', ['imprimir', 'idProyecto' => $proyecto->id], ['class' => 'btn btn-primary', 'target' => '_blank', 'id' => 'carta-imprimir']) ?>
            <?php endif; ?>
            <?= Html::a('Configuración de la carta', ['configuracion', 'proyecto' => $proyecto->id], ['class' => 'btn btn-outline']) ?>
        </div>
    </div>

    <?php if ($falta !== []): ?>
        <div class="alert alert-info">
            Puede escribir y guardar la carta ahora. Para imprimirla falta:
            <ul class="mb-0">
                <?php foreach ($falta as $motivo): ?>
                    <li><?= Html::encode($motivo) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php $form = ActiveForm::begin(['id' => 'carta-form']); ?>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Destinatario</div></div>
        <div class="rd-card-pad">
            <div class="row g-3">
                <div class="col-md-3">
                    <?= $form->field($carta, 'fecha')->textInput([
                        'value' => $carta->fechaFormateada(),
                        'placeholder' => 'dd/mm/aaaa',
                        'maxlength' => 10,
                        'pattern' => '\d{1,2}/\d{1,2}/\d{4}',
                        'title' => 'Formato dd/mm/aaaa',
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($carta, 'tratamiento')->dropDownList(array_combine(CartaPresupuesto::TRATAMIENTOS, CartaPresupuesto::TRATAMIENTOS), ['id' => 'carta-tratamiento'])
                        ->hint('Sale como "Señora:" sobre el nombre.') ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($carta, 'destinatarioNombre')->textInput(['maxlength' => 150, 'placeholder' => 'ej. Msc. María Esther Santos Parraga'])
                        ->hint('Con su grado: Ing., Lic., Msc., Dr.…') ?>
                </div>
                <div class="col-12">
                    <?= $form->field($carta, 'destinatarioCargo')->textInput(['maxlength' => 200, 'id' => 'carta-cargo'])
                        ->hint('Se guarda en mayúsculas. Al cambiar el tratamiento, DECANO/DECANA cambia solo.') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Texto</div></div>
        <div class="rd-card-pad">
            <?= $form->field($carta, 'referencia')->textarea(['rows' => 2, 'maxlength' => 500])
                ->hint('Sale en negrita después de "Ref.". Se guarda en mayúsculas.') ?>
            <?= $form->field($carta, 'saludo')->textInput(['maxlength' => 150]) ?>
            <?= $form->field($carta, 'cuerpo')->textarea(['rows' => 10, 'maxlength' => 4000])
                ->hint('Deje una línea en blanco entre párrafos. ' . $marcadores) ?>
            <?= $form->field($carta, 'despedida')->textInput(['maxlength' => 150]) ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Firmas</div></div>
        <div class="rd-card-pad">
            <div class="row g-3">
                <?php foreach ([1 => 'Firma izquierda', 2 => 'Firma derecha', 3 => 'Firma de abajo, centrada (V°B°)'] as $n => $titulo): ?>
                    <div class="col-md-4">
                        <div class="rd-dato"><strong><?= $titulo ?></strong></div>
                        <?= $form->field($carta, "firma{$n}Prefijo")->textInput(['maxlength' => 20, 'placeholder' => 'ej. V°B°']) ?>
                        <?= $form->field($carta, "firma{$n}Nombre")->textInput(['maxlength' => 150]) ?>
                        <?= $form->field($carta, "firma{$n}Cargo")->textInput(['maxlength' => 200]) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="rd-nota mb-0">Vienen de la configuración de la carta; aquí se cambian solo para esta carta. Sin nombre, la firma no sale.</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Pie</div></div>
        <div class="rd-card-pad">
            <?= $form->field($carta, 'copia')->textInput(['maxlength' => 200])->hint('Sale como "Copia: …". Vacío no sale.') ?>
            <?= $form->field($carta, 'adjunto')->textInput(['maxlength' => 300])->hint('Sale como "Adjunto: …". ' . $marcadores) ?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
