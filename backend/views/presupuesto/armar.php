<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\web\JqueryAsset;

/**
 * "Armar presupuesto": los items de la lista sin linea, cada uno con los
 * precios cotizados para esa fila (el menor ya marcado), y un solo boton
 * que crea todas las lineas.
 *
 * Formulario POST:
 *   lineas[idProyectoItem][opcion]  idCotizacionDetalle, 'manual' o '' (dejar pendiente)
 *   lineas[idProyectoItem][precio]  solo con 'manual'
 *
 * web/js/armar-presupuesto.js recalcula los totales y valida el precio
 * manual antes de enviar. El servidor vuelve a validar todo en
 * PresupuestoService::armar().
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * @var backend\models\Presupuesto|null $presupuesto null si el proyecto todavia no tiene
 * @var array<int, array{nombre: string, filas: array<int, array{item: backend\models\ProyectoItem, opciones: backend\models\CotizacionDetalle[], menor: int|null}>}> $grupos Ver PresupuestoService::filasParaArmar()
 * @var array<int, array{opcion?: string, precio?: string}> $elecciones lo enviado, si el guardado fallo
 * @var array<int, string> $errores [idProyectoItem => mensaje], [0 => mensaje general]
 */

$this->registerJsFile('@web/js/armar-presupuesto.js', ['depends' => [JqueryAsset::class]]);

$this->title = 'Armar presupuesto';
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['/proyecto/index']];
$this->params['breadcrumbs'][] = ['label' => $proyecto->codigo ?: '#' . $proyecto->id, 'url' => ['/proyecto/view', 'id' => $proyecto->id]];
if ($presupuesto !== null) {
    $this->params['breadcrumbs'][] = ['label' => 'Presupuesto', 'url' => ['view', 'id' => $presupuesto->id]];
}
$this->params['breadcrumbs'][] = 'Armar';

$formato = Yii::$app->formatter;
$totalFilas = array_sum(array_map(static fn ($grupo) => count($grupo['filas']), $grupos));
?>
<div class="presupuesto-armar">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                <?= Html::encode($proyecto->etiqueta()) ?> ·
                <?= $presupuesto === null
                    ? 'el presupuesto se crea al generar'
                    : 'Presupuesto #' . $presupuesto->id . ' (' . Html::encode($presupuesto->estadoLabel()) . ')' ?>
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('← Proyecto', ['/proyecto/view', 'id' => $proyecto->id], ['class' => 'btn btn-outline']) ?>
            <?php if ($presupuesto !== null): ?>
                <?= Html::a('Ver presupuesto', ['view', 'id' => $presupuesto->id], ['class' => 'btn btn-outline']) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($errores[0])): ?>
        <div class="alert alert-danger"><?= Html::encode($errores[0]) ?></div>
    <?php elseif ($errores !== []): ?>
        <div class="alert alert-danger">No se guardó ninguna línea. Revise los ítems marcados en rojo.</div>
    <?php endif; ?>

    <?php if ($totalFilas === 0): ?>
        <div class="card rd-vacio">
            Todos los ítems de la lista ya están en el presupuesto.
            <?php if ($presupuesto !== null): ?>
                <?= Html::a('Ver presupuesto →', ['view', 'id' => $presupuesto->id]) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>

    <p class="rd-nota">
        Cada ítem muestra los precios que los proveedores cotizaron para esa fila de la lista; el menor ya está
        marcado. Puede elegir otro, escribir un precio manual o dejar el ítem pendiente para después. El precio
        elegido queda congelado en el presupuesto.
    </p>

    <?= Html::beginForm(['armar', 'idProyecto' => $proyecto->id], 'post', ['id' => 'armar-form']) ?>

    <div class="card">
        <table class="table" id="armar-tabla">
            <thead>
                <tr>
                    <th>Ítem</th>
                    <th>Unidad</th>
                    <th class="rd-num">Cantidad</th>
                    <th>Precio</th>
                    <th class="rd-num">Total</th>
                </tr>
            </thead>
            <?php foreach ($grupos as $grupo): ?>
                <tbody>
                    <tr class="cat-row"><td colspan="5"><?= Html::encode($grupo['nombre']) ?></td></tr>
                    <?php foreach ($grupo['filas'] as $fila): ?>
                        <?php
                        $item = $fila['item'];
                        $nombre = "lineas[{$item->id}]";
                        $enviado = $elecciones[$item->id] ?? null;
                        // Sin envio previo: el menor precio, o 'manual' si no hay cotizaciones.
                        $elegida = is_array($enviado) ? (string) ($enviado['opcion'] ?? '') : (string) ($fila['menor'] ?? 'manual');
                        $precioManual = is_array($enviado) ? (string) ($enviado['precio'] ?? '') : '';
                        $error = $errores[$item->id] ?? null;
                        ?>
                        <tr class="armar-fila<?= $error !== null ? ' table-danger' : '' ?>" data-cantidad="<?= Html::encode((string) (float) $item->cantidad) ?>">
                            <td>
                                <div class="rd-celda-titulo"><?= Html::encode($item->subcatalogoItem->descripcion ?? 'Ítem no disponible') ?></div>
                                <?php if (!empty($item->observacion)): ?>
                                    <div class="rd-celda-sub"><?= Html::encode($item->observacion) ?></div>
                                <?php endif; ?>
                                <?php if ($item->especificacion !== null): ?>
                                    <div class="rd-celda-sub">Especificación técnica: <?= Html::encode($item->especificacion->referencia()) ?></div>
                                <?php endif; ?>
                                <?php if ($error !== null): ?>
                                    <div class="text-danger small mt-1"><?= Html::encode($error) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= Html::encode((string) $item->unidadMedida) ?></td>
                            <td class="rd-num mono"><?= $formato->asDecimal($item->cantidad, 2) ?></td>
                            <td>
                                <?php foreach ($fila['opciones'] as $detalle): ?>
                                    <?php $cotizacion = $detalle->cotizacion; ?>
                                    <label class="d-block mb-1">
                                        <?= Html::radio("{$nombre}[opcion]", $elegida === (string) $detalle->id, [
                                            'value' => $detalle->id,
                                            'class' => 'armar-opcion',
                                            'data-precio' => (string) (float) $detalle->precioUnitario,
                                        ]) ?>
                                        <?= Html::encode($cotizacion->proveedor->razonSocial ?? '?') ?> —
                                        <span class="mono"><?= $formato->asDecimal($detalle->precioUnitario, 2) ?></span>
                                        <?php if ($detalle->marcaModelo): ?>
                                            <span class="rd-celda-sub">(<?= Html::encode($detalle->marcaModelo) ?>)</span>
                                        <?php endif; ?>
                                        <?php if ((int) $detalle->id === $fila['menor'] && count($fila['opciones']) > 1): ?>
                                            <span class="badge b-activo">Menor</span>
                                        <?php endif; ?>
                                        <?php if ($detalle->idProyectoItem === null): ?>
                                            <span class="rd-celda-sub">cotización suelta</span>
                                        <?php endif; ?>
                                        <?php if ($cotizacion !== null && $cotizacion->estaVencida()): ?>
                                            <span class="badge b-cancelado">Vencida</span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>

                                <label class="d-flex align-items-center gap-2 mb-1">
                                    <?= Html::radio("{$nombre}[opcion]", $elegida === 'manual', ['value' => 'manual', 'class' => 'armar-opcion']) ?>
                                    Precio manual
                                    <?= Html::textInput("{$nombre}[precio]", $precioManual, [
                                        'class' => 'qty armar-precio',
                                        'type' => 'number',
                                        'step' => '0.01',
                                        'min' => '0',
                                        'aria-label' => 'Precio unitario manual',
                                    ]) ?>
                                </label>

                                <label class="d-block mb-0 rd-celda-sub">
                                    <?= Html::radio("{$nombre}[opcion]", $elegida === '', ['value' => '', 'class' => 'armar-opcion']) ?>
                                    Dejar pendiente
                                </label>

                                <?php if ($fila['opciones'] === []): ?>
                                    <div class="rd-celda-sub mt-1">Sin cotizaciones: escriba el precio o déjelo vacío para completarlo después.</div>
                                <?php endif; ?>
                            </td>
                            <td class="rd-num mono armar-total">—</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php endforeach; ?>
            <tfoot>
                <tr class="subtotal-row">
                    <td colspan="4">
                        Total: <span id="armar-con-precio">0</span> de <?= $totalFilas ?> ítem(s) con precio
                    </td>
                    <td class="rd-num mono" id="armar-total-general">0.00</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="alert alert-danger d-none mt-3" id="armar-error-cliente"></div>

    <div class="rd-acciones mt-3">
        <?= Html::submitButton('Generar presupuesto', ['class' => 'btn btn-primary']) ?>
        <span class="rd-nota">Los ítems pendientes se pueden completar después desde esta misma pantalla.</span>
    </div>

    <?= Html::endForm() ?>

    <?php endif; ?>

</div>
