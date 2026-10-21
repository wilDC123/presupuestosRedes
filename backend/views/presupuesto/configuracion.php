<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Configuracion del PDF del presupuesto: lo que sale despues del total (nota,
 * dos firmas y banda de pie con los contactos). El presupuesto no guarda
 * copia, asi que un cambio tambien se ve al reimprimir los anteriores.
 *
 * @var yii\web\View $this
 * @var backend\models\ConfiguracionPresupuesto $configuracion
 * @var int|null $proyecto id del proyecto desde donde se entro (para volver)
 */

$this->title = 'Configuración del PDF del presupuesto';
$this->params['breadcrumbs'][] = ['label' => 'Presupuestos', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Configuración';

$volver = $proyecto !== null
    ? Html::a('← Presupuesto del proyecto', ['/elaborar-presupuesto/index', 'idProyecto' => $proyecto], ['class' => 'btn btn-outline'])
    : Html::a('← Presupuestos', ['index'], ['class' => 'btn btn-outline']);
?>
<div class="presupuesto-configuracion">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                Sale después del total, en todos los presupuestos (también al reimprimir los anteriores). Un campo vacío no sale.
            </div>
        </div>
        <div class="rd-acciones"><?= $volver ?></div>
    </div>

    <?php $form = ActiveForm::begin(); ?>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Nota</div></div>
        <div class="rd-card-pad">
            <?= $form->field($configuracion, 'textoNota')->textarea(['rows' => 2, 'maxlength' => 500])
                ->label(false)
                ->hint('Opcional. Sale debajo del total general.') ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Firmas</div></div>
        <div class="rd-card-pad">
            <div class="row g-3">
                <?php foreach ([1 => 'Firma izquierda', 2 => 'Firma derecha'] as $n => $titulo): ?>
                    <div class="col-md-6">
                        <div class="rd-dato"><strong><?= $titulo ?></strong></div>
                        <?= $form->field($configuracion, "firma{$n}Prefijo")->textInput(['maxlength' => 20, 'placeholder' => 'ej. V°B°'])
                            ->hint('Opcional. Sale antes del nombre.') ?>
                        <?= $form->field($configuracion, "firma{$n}Nombre")->textInput(['maxlength' => 150, 'placeholder' => 'ej. Ing. Nombre Apellido'])
                            ->hint('Con su tratamiento (Ing., Lic.…). Sin nombre, la firma no sale.') ?>
                        <?= $form->field($configuracion, "firma{$n}Cargo")->textInput(['maxlength' => 200])
                            ->hint('Se guarda en mayúsculas.') ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Banda de pie (contactos)</div></div>
        <div class="rd-card-pad">
            <div class="row g-3">
                <?php foreach (['Izquierda' => 'Columna izquierda', 'Derecha' => 'Columna derecha'] as $lado => $titulo): ?>
                    <div class="col-md-6">
                        <div class="rd-dato"><strong><?= $titulo ?></strong></div>
                        <?= $form->field($configuracion, "pie{$lado}Titulo")->textInput(['maxlength' => 150])
                            ->hint('Sale en negrita, centrado.') ?>
                        <?= $form->field($configuracion, "pie{$lado}Lineas")->textarea(['rows' => 3, 'maxlength' => 500])
                            ->hint('Un renglón por línea (teléfonos, internos, correo).') ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="rd-nota mb-0">Sale al final del documento, con fondo verde claro, debajo de las firmas.</p>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
