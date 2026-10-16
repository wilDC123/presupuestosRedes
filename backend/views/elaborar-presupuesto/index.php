<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JqueryAsset;

/**
 * Paso "Presupuesto" del proyecto: el presupuesto como documento vivo.
 *
 * Una fila por item de la lista y una columna por proveedor. Marcar otro
 * precio lo guarda solo (AJAX, web/js/elaborar-presupuesto.js); si no es el
 * menor, primero pide la justificacion. Mientras el presupuesto no es un
 * borrador ("en proceso") todo es de solo lectura.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * @var backend\models\Presupuesto $presupuesto
 * @var array $pasos Ver ProyectoService::pasos()
 * @var array $tabla Ver ElaborarPresupuestoService::tabla()
 * @var array{sinPrecio: string[], vencidas: int} $control Ver ElaborarPresupuestoService::control()
 * @var array $resumenPartidas Ver PresupuestoService::resumenPorPartida()
 */

$borrador = $presupuesto->esBorrador();

if ($borrador) {
    $this->registerJsFile('@web/js/elaborar-presupuesto.js', ['depends' => [JqueryAsset::class]]);
}

$this->title = 'Presupuesto';
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['/proyecto/index']];
$this->params['breadcrumbs'][] = ['label' => $proyecto->codigo ?: '#' . $proyecto->id, 'url' => ['/proyecto/view', 'id' => $proyecto->id]];
$this->params['breadcrumbs'][] = 'Presupuesto';

$formato = Yii::$app->formatter;
$proveedores = $tabla['proveedores'];
$columnas = 5 + count($proveedores);
?>
<div class="elaborar-presupuesto">

    <div class="rd-pagehead">
        <div>
            <h1>
                <?= Html::encode($this->title) ?>
                <span class="<?= $presupuesto->estadoBadgeClass() ?>"><?= Html::encode($presupuesto->estadoLabel()) ?></span>
            </h1>
            <div class="rd-pagehead-sub"><?= Html::encode($proyecto->etiqueta()) ?> · Presupuesto #<?= $presupuesto->id ?></div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('← Proyecto', ['/proyecto/view', 'id' => $proyecto->id], ['class' => 'btn btn-outline']) ?>
            <?= Html::a($presupuesto->estado === 'aprobado' ? 'Generar PDF' : 'PDF borrador', ['/presupuesto/pdf', 'id' => $presupuesto->id], ['class' => 'btn btn-outline', 'target' => '_blank']) ?>
        </div>
    </div>

    <?= $this->render('/proyecto/_pasos', ['pasos' => $pasos, 'actual' => 'Presupuesto']) ?>

    <?php if ($tabla['totalFilas'] === 0): ?>
        <div class="card rd-vacio">
            La lista del proyecto no tiene ítems.
            <?= Html::a('Abrir la lista', ['/lista-items/index', 'id' => $proyecto->id]) ?>
        </div>
    <?php else: ?>

    <?php if ($borrador && $proveedores !== []): ?>
        <div class="card rd-card-pad mb-3">
            <?= Html::beginForm(['adjudicar', 'idProyecto' => $proyecto->id], 'post', ['class' => 'rd-filtros mb-0', 'id' => 'adjudicar-form']) ?>
                <span class="rd-dato mb-0"><strong>Elegir automáticamente:</strong></span>
                <?= Html::submitButton('Menor precio por ítem', ['class' => 'btn btn-outline', 'name' => 'modo', 'value' => 'menor']) ?>
                <span class="rd-celda-sub">o todo a</span>
                <?= Html::dropDownList('idProveedor', null, $proveedores, ['class' => 'form-select', 'style' => 'max-width: 220px']) ?>
                <?= Html::textInput('justificacion', '', [
                    'class' => 'form-control',
                    'maxlength' => 300,
                    'placeholder' => 'Justificación (si no es el menor en todo)',
                ]) ?>
                <?= Html::submitButton('Adjudicar todo', ['class' => 'btn btn-outline', 'name' => 'modo', 'value' => 'proveedor']) ?>
            <?= Html::endForm() ?>
        </div>
    <?php endif; ?>

    <?php if (!$borrador): ?>
        <div class="alert alert-secondary">
            El presupuesto está "<?= Html::encode($presupuesto->estadoLabel()) ?>": los precios ya no se cambian.
        </div>
    <?php endif; ?>

    <div class="card">
        <table class="table" id="elaborar-tabla"
               data-url="<?= Html::encode(Url::to(['elegir', 'idProyecto' => $proyecto->id])) ?>">
            <thead>
                <tr>
                    <th>Ítem</th>
                    <th class="rd-num">Cant.</th>
                    <th>Ud.</th>
                    <?php foreach ($proveedores as $nombreProveedor): ?>
                        <th class="rd-num"><?= Html::encode($nombreProveedor) ?></th>
                    <?php endforeach; ?>
                    <th>Manual</th>
                    <th class="rd-num">Total</th>
                </tr>
            </thead>
            <?php foreach ($tabla['grupos'] as $idCategoria => $grupo): ?>
                <tbody>
                    <tr class="cat-row"><td colspan="<?= $columnas ?>"><?= Html::encode($grupo['nombre']) ?></td></tr>
                    <?php foreach ($grupo['filas'] as $fila): ?>
                        <?php
                        $item = $fila['item'];
                        $linea = $fila['linea'];
                        $nombre = 'opcion-' . $item->id;
                        $guardada = $linea === null ? '' : ($linea->idCotizacionDetalle === null ? 'manual' : (string) $linea->idCotizacionDetalle);
                        ?>
                        <tr class="elaborar-fila"
                            data-item="<?= $item->id ?>"
                            data-categoria="<?= $idCategoria ?>"
                            data-menor="<?= $fila['menor'] ?? '' ?>"
                            data-guardada="<?= Html::encode($guardada) ?>"
                            data-precio-guardado="<?= $guardada === 'manual' ? Html::encode((string) (float) $linea->precioUnitario) : '' ?>">
                            <td>
                                <div class="rd-celda-titulo"><?= Html::encode($item->subcatalogoItem->descripcion ?? 'Ítem no disponible') ?></div>
                                <?php if (!empty($item->observacion)): ?>
                                    <div class="rd-celda-sub"><?= Html::encode($item->observacion) ?></div>
                                <?php endif; ?>
                                <?php if ($item->especificacion !== null): ?>
                                    <div class="rd-celda-sub">Especificación técnica: <?= Html::encode($item->especificacion->referencia()) ?></div>
                                <?php endif; ?>
                                <div class="elaborar-justificacion<?= ($linea->justificacion ?? null) === null ? ' d-none' : '' ?> mt-1">
                                    <?php if ($borrador): ?>
                                        <div class="d-flex gap-2">
                                            <?= Html::textInput('justificacion', (string) ($linea->justificacion ?? ''), [
                                                'class' => 'form-control form-control-sm elaborar-texto-justificacion',
                                                'maxlength' => 300,
                                                'placeholder' => '¿Por qué no el menor precio?',
                                                'aria-label' => 'Justificación',
                                            ]) ?>
                                            <button type="button" class="btn btn-primary btn-sm elaborar-guardar">Guardar</button>
                                        </div>
                                    <?php else: ?>
                                        <div class="rd-celda-sub">Justificación: <?= Html::encode((string) $linea->justificacion) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="small elaborar-estado"></div>
                            </td>
                            <td class="rd-num mono"><?= $formato->asDecimal($item->cantidad, 2) ?></td>
                            <td><?= Html::encode((string) $item->unidadMedida) ?></td>
                            <?php foreach ($proveedores as $idProveedor => $nombreProveedor): ?>
                                <?php $detalle = $fila['celdas'][$idProveedor] ?? null; ?>
                                <?php if ($detalle === null): ?>
                                    <td class="rd-num"><span class="rd-celda-sub">No cotizó</span></td>
                                <?php else: ?>
                                    <td class="rd-num mono<?= (int) $detalle->id === $fila['menor'] && count($fila['celdas']) > 1 ? ' rd-menor' : '' ?>">
                                        <label class="mb-0">
                                            <?= Html::radio($nombre, $guardada === (string) $detalle->id, [
                                                'value' => $detalle->id,
                                                'class' => 'elaborar-opcion',
                                                'disabled' => !$borrador,
                                            ]) ?>
                                            <?= $formato->asDecimal($detalle->precioUnitario, 2) ?>
                                        </label>
                                        <?php if ($detalle->cotizacion !== null && $detalle->cotizacion->estaVencida()): ?>
                                            <div><span class="badge b-cancelado">Vencida</span></div>
                                        <?php endif; ?>
                                        <?php if ($detalle->idProyectoItem === null): ?>
                                            <div class="rd-celda-sub">suelta</div>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <td>
                                <label class="d-flex align-items-center gap-1 mb-0">
                                    <?= Html::radio($nombre, $guardada === 'manual', [
                                        'value' => 'manual',
                                        'class' => 'elaborar-opcion',
                                        'disabled' => !$borrador,
                                    ]) ?>
                                    <?= Html::textInput('precio', $guardada === 'manual' ? (string) (float) $linea->precioUnitario : '', [
                                        'class' => 'qty elaborar-precio',
                                        'type' => 'number',
                                        'step' => '0.01',
                                        'min' => '0',
                                        'aria-label' => 'Precio unitario manual',
                                        'disabled' => !$borrador,
                                    ]) ?>
                                </label>
                            </td>
                            <td class="rd-num mono elaborar-total"><?= $linea === null ? '—' : $formato->asDecimal($linea->precioTotal, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal-row">
                        <td colspan="<?= $columnas - 1 ?>">Subtotal <?= Html::encode($grupo['nombre']) ?></td>
                        <td class="rd-num mono elaborar-subtotal" data-categoria="<?= $idCategoria ?>"><?= $formato->asDecimal($grupo['subtotal'], 2) ?></td>
                    </tr>
                </tbody>
            <?php endforeach; ?>
            <tfoot>
                <tr class="subtotal-row">
                    <td colspan="<?= $columnas - 1 ?>">
                        Total general Bs. · <span id="elaborar-con-precio"><?= $tabla['conPrecio'] ?></span> de <?= $tabla['totalFilas'] ?> ítems con precio
                    </td>
                    <td class="rd-num mono" id="elaborar-total-general"><?= $formato->asDecimal($tabla['total'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="rd-cols mt-3">
        <div class="card rd-col-2 rd-card-pad" id="elaborar-partidas">
            <?= $this->render('/presupuesto/_resumen-partidas', ['resumenPartidas' => $resumenPartidas]) ?>
        </div>
        <div class="card rd-col-1 rd-card-pad" id="elaborar-control">
            <?= $this->render('_control', ['control' => $control, 'presupuesto' => $presupuesto]) ?>
        </div>
    </div>

    <?php endif; ?>

</div>
