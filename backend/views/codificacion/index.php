<?php

declare(strict_types=1);

use backend\models\CorrelativoDocumento;
use yii\helpers\Html;

/**
 * Codificacion de documentos: el siguiente numero de cada tipo de documento
 * (proyecto, presupuesto, especificacion tecnica), segun el lineamiento de la
 * DTIC RD-GD-LI-001.
 *
 * Los campos se llaman CorrelativoDocumento[PT][siguiente], etc.: asi
 * CorrelativoDocumento::loadMultiple() carga cada fila con su valor.
 *
 * @var yii\web\View $this
 * @var CorrelativoDocumento[] $correlativos indexados por tipo
 * @var array<string, string|null> $ultimos el ultimo numero usado de cada tipo ("015")
 */

$this->title = 'Codificación de documentos';
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="codificacion-index">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                Lineamiento RD-GD-LI-001 de la D.T.I.C.: unidad (RD, Redes y Telecomunicaciones) – proceso – tipo de
                documento – número de 3 cifras como mínimo.
            </div>
        </div>
    </div>

    <?= Html::beginForm(['index'], 'post') ?>

    <div class="card mb-3">
        <div class="rd-card-pad">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Último usado</th>
                        <th style="width: 220px;">Siguiente número</th>
                        <th>El próximo será</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($correlativos as $tipo => $correlativo): ?>
                        <tr>
                            <td><?= Html::encode($correlativo->nombreTipo()) ?> <span class="mono">(<?= Html::encode($tipo) ?>)</span></td>
                            <td class="mono"><?= Html::encode($ultimos[$tipo] ?? '—') ?></td>
                            <td>
                                <?= Html::activeTextInput($correlativo, "[$tipo]siguiente", [
                                    'class' => 'form-control' . ($correlativo->hasErrors('siguiente') ? ' is-invalid' : ''),
                                    'type' => 'number',
                                    'min' => 1,
                                    'required' => true,
                                ]) ?>
                                <?php if ($correlativo->hasErrors('siguiente')): ?>
                                    <div class="invalid-feedback"><?= Html::encode($correlativo->getFirstError('siguiente')) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= Html::encode(CorrelativoDocumento::codigo(
                                $tipo,
                                max(1, (int) $correlativo->siguiente),
                                CorrelativoDocumento::PROCESO
                            )) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="rd-nota">
        El sistema asigna el número solo: el proyecto al crearlo, el presupuesto al entrar por primera vez a su paso
        "Presupuesto" y la especificación técnica al enlazarla a un ítem de la lista. Cada número sube de 1 en 1 y nunca se
        vuelve a usar; aquí solo se puede poner uno mayor que el último usado (por ejemplo, para seguir el registro de Google
        Drive).
    </p>
    <p class="rd-nota">
        <strong>Proceso:</strong> el proyecto siempre lleva PI. El presupuesto y las especificaciones llevan el proceso del
        proyecto (RD-GR-PR-016 si el proyecto es de GR), pero el número es uno solo para todos los procesos: arriba se
        muestran con PI.
        Ningún código lleva el año ni vuelve a 001 al cambiar de año: las especificaciones técnicas siguen su numeración
        (RD-PI-ET-999, RD-PI-ET-1000…) como los demás. Al reemitir una especificación conserva su código. Los formularios
        emitidos antes con código NET y los números de presupuesto escritos a mano antes conservan el suyo.
    </p>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?= Html::endForm() ?>

</div>
