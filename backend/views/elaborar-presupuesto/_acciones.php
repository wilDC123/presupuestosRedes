<?php

declare(strict_types=1);

use backend\models\Presupuesto;
use yii\helpers\Html;

/**
 * Pie de la pantalla del presupuesto: lo que se puede hacer segun su estado.
 *  - en proceso: "Enviar a revision", con la cabecera (obligatoria recien aqui),
 *  - los demas cambios de estado: un boton por cada estado de
 *    $estadosConBoton (sale de Presupuesto::transicionesPermitidas(), la
 *    unica fuente de verdad del ciclo de vida; esta vista solo decide el
 *    texto y el aspecto de cada boton),
 *  - aprobado: crear la fase siguiente.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * @var backend\models\Presupuesto $presupuesto
 * @var string[] $erroresEnvio
 * @var string[] $estadosConBoton Ver PresupuestoService::estadosConBoton()
 */

$botonEstado = static fn (string $estado, string $texto, string $clase, string $confirmar = ''): string =>
    Html::beginForm(['estado', 'idProyecto' => $proyecto->id], 'post', ['class' => 'd-inline-block'])
    . Html::hiddenInput('estado', $estado)
    . Html::submitButton($texto, ['class' => $clase] + ($confirmar === '' ? [] : ['data' => ['confirm' => $confirmar]]))
    . Html::endForm();

// Como se ve el boton de cada estado de destino, en el orden en que se muestran.
$botones = [
    Presupuesto::ESTADO_APROBADO => ['Aprobar', 'btn btn-primary', '¿Aprobar el presupuesto? La lista del proyecto queda cerrada.'],
    Presupuesto::ESTADO_OBSERVADO => ['Observar', 'btn btn-outline', ''],
    Presupuesto::ESTADO_CANCELADO => ['Cancelar', 'btn btn-danger', '¿Cancelar el presupuesto? No se puede deshacer.'],
    Presupuesto::ESTADO_EN_PROCESO => ['Corregir (volver a "En proceso")', 'btn btn-primary', ''],
];
?>
<div class="card rd-card-pad mt-3" id="elaborar-acciones">

    <?php if ($presupuesto->esBorrador()): ?>
        <?php // <details>: se abre y cierra sin JavaScript; abierto si el envio fallo. ?>
        <details<?= $erroresEnvio !== [] ? ' open' : '' ?>>
            <summary class="btn btn-primary">Enviar a revisión →</summary>

            <div class="mt-3">
                <?php if ($erroresEnvio !== []): ?>
                    <div class="alert alert-danger mb-3">
                        No se envió:
                        <ul class="mb-0">
                            <?php foreach ($erroresEnvio as $error): ?>
                                <li><?= Html::encode($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <p class="rd-nota">Para enviarlo, complete los datos de cabecera del presupuesto. Después de enviarlo los precios ya no se cambian.</p>

                <?= Html::beginForm(['enviar', 'idProyecto' => $proyecto->id], 'post', ['id' => 'enviar-form']) ?>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <?= Html::activeLabel($presupuesto, 'numeroPresupuesto', ['class' => 'form-label']) ?>
                            <?= Html::activeTextInput($presupuesto, 'numeroPresupuesto', ['class' => 'form-control', 'maxlength' => 20, 'required' => true]) ?>
                        </div>
                        <div class="col-md-3">
                            <?= Html::activeLabel($presupuesto, 'oficioDtic', ['class' => 'form-label']) ?>
                            <?= Html::activeTextInput($presupuesto, 'oficioDtic', ['class' => 'form-control', 'maxlength' => 100, 'required' => true]) ?>
                        </div>
                        <div class="col-md-4">
                            <?= Html::activeLabel($presupuesto, 'areaIntervencion', ['class' => 'form-label']) ?>
                            <?php // Si aun no se escribio, se sugiere la facultad del proyecto (se puede cambiar). ?>
                            <?= Html::activeTextInput($presupuesto, 'areaIntervencion', [
                                'class' => 'form-control',
                                'value' => $presupuesto->areaIntervencion ?: (string) $proyecto->facultad,
                                'maxlength' => 300,
                                'required' => true,
                            ]) ?>
                        </div>
                        <div class="col-md-2">
                            <?= Html::activeLabel($presupuesto, 'fecha', ['class' => 'form-label']) ?>
                            <?= Html::activeTextInput($presupuesto, 'fecha', [
                                'class' => 'form-control',
                                'value' => $presupuesto->fechaFormateada() ?: Yii::$app->formatter->asDate('now', 'php:d/m/Y'),
                                'placeholder' => 'dd/mm/aaaa',
                                'maxlength' => 10,
                                'required' => true,
                                // dd/mm/aaaa: el mismo formato que acepta Presupuesto::beforeValidate().
                                'pattern' => '\d{1,2}/\d{1,2}/\d{4}',
                                'title' => 'Formato dd/mm/aaaa',
                            ]) ?>
                        </div>
                    </div>
                    <div class="mt-3">
                        <?= Html::submitButton('Enviar a revisión', ['class' => 'btn btn-primary']) ?>
                    </div>
                <?= Html::endForm() ?>
            </div>
        </details>

    <?php else: ?>
        <div class="rd-dato">
            <strong>N.° de presupuesto:</strong> <?= Html::encode($presupuesto->numeroPresupuesto ?: '—') ?> ·
            <strong>Oficio DTIC:</strong> <?= Html::encode($presupuesto->oficioDtic ?: '—') ?> ·
            <strong>Área:</strong> <?= Html::encode($presupuesto->areaIntervencion ?: '—') ?> ·
            <strong>Fecha:</strong> <?= Html::encode($presupuesto->fechaFormateada() ?: '—') ?>
        </div>

        <div class="rd-acciones mt-2">
            <?php foreach ($botones as $estado => [$texto, $clase, $confirmar]): ?>
                <?php if (in_array($estado, $estadosConBoton, true)): ?>
                    <?= $botonEstado($estado, $texto, $clase, $confirmar) ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($presupuesto->estado === Presupuesto::ESTADO_APROBADO): ?>
                <?= Html::a('Crear proyecto de fase siguiente →', ['/proyecto/create', 'idAnterior' => $proyecto->id], ['class' => 'btn btn-primary']) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
