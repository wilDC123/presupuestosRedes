<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Cuadro comparativo de una solicitud: una fila por item y una columna por
 * proveedor que respondio, con su precio unitario. El menor precio de cada
 * item va resaltado. Es solo una ayuda: el precio del presupuesto lo elige
 * el encargado.
 *
 * @var yii\web\View $this
 * @var backend\models\SolicitudCotizacion $solicitud
 * @var array{cotizaciones: backend\models\Cotizacion[], filas: array<int, array{item: backend\models\ProyectoItem, precios: array<int, float>, menor: int|null}>} $comparativo Ver CotizacionService::comparativo()
 */

$proyecto = $solicitud->proyecto;
$this->title = 'Comparativo de precios — ' . $solicitud->codigoCorto();
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['/proyecto/index']];
$this->params['breadcrumbs'][] = ['label' => $proyecto->codigo ?: '#' . $proyecto->id, 'url' => ['/proyecto/view', 'id' => $proyecto->id]];
$this->params['breadcrumbs'][] = ['label' => 'Solicitudes de cotización', 'url' => ['index', 'id' => $proyecto->id]];
$this->params['breadcrumbs'][] = 'Comparativo';

$formato = Yii::$app->formatter;
$cotizaciones = $comparativo['cotizaciones'];
?>
<div class="solicitud-comparativo">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub"><?= Html::encode($solicitud->codigo() . ' · ' . $solicitud->nombre) ?></div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('← Solicitudes', ['index', 'id' => $proyecto->id], ['class' => 'btn btn-outline']) ?>
            <?php if ($cotizaciones !== []): ?>
                <?= Html::a('Armar presupuesto →', ['/presupuesto/armar', 'idProyecto' => $proyecto->id], ['class' => 'btn btn-primary']) ?>
            <?php endif; ?>
        </div>
    </div>

    <p class="rd-nota">
        Precio unitario de cada proveedor; el menor de cada ítem va resaltado. Es una ayuda para comparar: el precio
        que va al presupuesto se elige en "Armar presupuesto", donde el menor ya viene marcado.
    </p>

    <div class="card">
        <?php if ($cotizaciones === []): ?>
            <div class="rd-vacio">Esta solicitud todavía no tiene cotizaciones registradas.</div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Ítem</th>
                        <th class="rd-num">Cantidad</th>
                        <?php foreach ($cotizaciones as $cotizacion): ?>
                            <th class="rd-num">
                                <?= Html::encode($cotizacion->proveedor->razonSocial ?? '?') ?>
                                <div class="rd-celda-sub">
                                    <?= Html::encode($cotizacion->fechaFormateada()) ?>
                                    <?= $cotizacion->estaVencida() ? '<span class="badge b-cancelado">Vencida</span>' : '' ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparativo['filas'] as $fila): ?>
                        <tr>
                            <td><?= Html::encode($fila['item']->subcatalogoItem->descripcion ?? 'Ítem no disponible') ?></td>
                            <td class="rd-num mono"><?= $formato->asDecimal($fila['item']->cantidad, 2) ?></td>
                            <?php foreach ($cotizaciones as $cotizacion): ?>
                                <?php $precio = $fila['precios'][$cotizacion->id] ?? null; ?>
                                <td class="rd-num mono<?= $fila['menor'] === $cotizacion->id && count($fila['precios']) > 1 ? ' rd-menor' : '' ?>">
                                    <?= $precio === null ? '<span class="rd-celda-sub">No cotizó</span>' : $formato->asDecimal($precio, 2) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="subtotal-row">
                        <td colspan="2">Total cotizado</td>
                        <?php foreach ($cotizaciones as $cotizacion): ?>
                            <td class="rd-num mono"><?= $formato->asDecimal($cotizacion->total(), 2) ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>

</div>
