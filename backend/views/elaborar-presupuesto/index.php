<?php

declare(strict_types=1);

use backend\models\PresupuestoDetalle;
use backend\services\ElaborarPresupuestoService;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JqueryAsset;

/**
 * Paso "Presupuesto" del proyecto: el presupuesto como documento vivo.
 *
 * Una fila por item de la lista, una columna por proveedor y la columna
 * "Precio directo" (sin cotizacion, con fuente opcional y, si el item SIGMA
 * tiene precio referencial, el boton para usarlo). Marcar otro precio lo
 * guarda solo (AJAX, web/js/elaborar-presupuesto.js); si no es el menor,
 * aparece la justificacion (opcional). Mientras el presupuesto no es un
 * borrador ("en proceso") todo es de solo lectura.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * @var backend\models\Presupuesto $presupuesto
 * @var array $pasos Ver ProyectoService::pasos()
 * @var array $tabla Ver ElaborarPresupuestoService::tabla()
 * @var array{sinPrecio: string[], vencidas: int} $control Ver ElaborarPresupuestoService::control()
 * @var array $resumenPartidas Ver PresupuestoService::resumenPorPartida()
 * @var string[] $estadosConBoton Ver PresupuestoService::estadosConBoton()
 * @var string[] $erroresEnvio mensajes si "Enviar a revision" fallo (el panel se abre)
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
            <?= Html::a('Relación SIGMA (PDF)', ['/presupuesto/pdf-sigma', 'id' => $presupuesto->id], ['class' => 'btn btn-outline', 'target' => '_blank', 'title' => 'Cada ítem presupuestado con su ítem del catálogo SIGMA']) ?>
            <?= Html::a('Configuración del PDF', ['/presupuesto/configuracion', 'proyecto' => $proyecto->id], ['class' => 'btn btn-outline']) ?>
        </div>
    </div>

    <?= $this->render('/proyecto/_pasos', ['pasos' => $pasos, 'actual' => 'Presupuesto']) ?>

    <?php if ($tabla['totalFilas'] === 0): ?>
        <div class="card rd-vacio">
            La lista del proyecto no tiene ítems.
            <?= Html::a('Abrir la lista', ['/lista-items/index', 'id' => $proyecto->id]) ?>
        </div>
    <?php else: ?>

    <?php if ($borrador && ($proveedores !== [] || $tabla['conSigmaSinPrecio'] > 0)): ?>
        <div class="card rd-card-pad mb-3">
            <?= Html::beginForm(['adjudicar', 'idProyecto' => $proyecto->id], 'post', ['class' => 'rd-filtros mb-0', 'id' => 'adjudicar-form']) ?>
                <span class="rd-dato mb-0"><strong>Elegir automáticamente:</strong></span>
                <?php if ($proveedores !== []): ?>
                    <?= Html::submitButton('Menor precio por ítem', ['class' => 'btn btn-outline', 'name' => 'modo', 'value' => 'menor']) ?>
                    <span class="rd-celda-sub">o todo a</span>
                    <?= Html::dropDownList('idProveedor', null, $proveedores, ['class' => 'form-select', 'style' => 'max-width: 220px']) ?>
                    <?= Html::textInput('justificacion', '', [
                        'class' => 'form-control',
                        'maxlength' => 300,
                        'placeholder' => 'Justificación (opcional)',
                    ]) ?>
                    <?= Html::submitButton('Adjudicar todo', ['class' => 'btn btn-outline', 'name' => 'modo', 'value' => 'proveedor']) ?>
                <?php endif; ?>
                <?php if ($tabla['conSigmaSinPrecio'] > 0): ?>
                    <?= Html::submitButton('Precio SIGMA en los ítems sin precio (' . $tabla['conSigmaSinPrecio'] . ')', [
                        'class' => 'btn btn-outline',
                        'name' => 'modo',
                        'value' => 'sigma',
                        'title' => 'Pone como precio directo el precio referencial del catálogo SIGMA. No cambia los precios ya elegidos.',
                    ]) ?>
                <?php endif; ?>
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
                    <th title="Precio sin cotización: escrito a mano o el precio referencial de SIGMA">Precio directo</th>
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
                        $directo = ElaborarPresupuestoService::DIRECTO;
                        $guardada = $linea === null ? '' : ($linea->esPrecioDirecto() ? $directo : (string) $linea->idCotizacionDetalle);
                        $esDirecto = $guardada === $directo;
                        ?>
                        <tr class="elaborar-fila"
                            data-item="<?= $item->id ?>"
                            data-categoria="<?= $idCategoria ?>"
                            data-menor="<?= $fila['menor'] ?? '' ?>"
                            data-guardada="<?= Html::encode($guardada) ?>"
                            data-precio-guardado="<?= $esDirecto ? Html::encode((string) (float) $linea->precioUnitario) : '' ?>"
                            data-fuente-guardada="<?= $esDirecto ? Html::encode((string) $linea->fuentePrecio) : '' ?>">
                            <td>
                                <div class="rd-celda-titulo"><?= Html::encode($item->subcatalogoItem->descripcion ?? 'Ítem no disponible') ?></div>
                                <?php if (!empty($item->observacion)): ?>
                                    <div class="rd-celda-sub"><?= Html::encode($item->observacion) ?></div>
                                <?php endif; ?>
                                <?php if ($item->especificacion !== null): ?>
                                    <div class="rd-celda-sub">Especificación técnica: <?= Html::encode($item->referenciaEspecificacion()) ?></div>
                                <?php endif; ?>
                                <?php
                                // Visible cuando el precio guardado no es el menor (en
                                // borrador, para escribirla; despues, solo si hay texto).
                                $noEsElMenor = $guardada !== '' && $fila['menor'] !== null && $guardada !== (string) $fila['menor'];
                                $verJustificacion = $borrador ? $noEsElMenor : !empty($linea?->justificacion);
                                ?>
                                <div class="elaborar-justificacion<?= $verJustificacion ? '' : ' d-none' ?> mt-1">
                                    <?php if ($borrador): ?>
                                        <div class="d-flex gap-2">
                                            <?= Html::textInput('justificacion', (string) ($linea->justificacion ?? ''), [
                                                'class' => 'form-control form-control-sm elaborar-texto-justificacion',
                                                'maxlength' => 300,
                                                'placeholder' => 'Justificación (opcional): ¿por qué no el menor precio?',
                                                'aria-label' => 'Justificación',
                                            ]) ?>
                                            <button type="button" class="btn btn-outline btn-sm elaborar-guardar text-nowrap">Guardar justificación</button>
                                        </div>
                                    <?php else: ?>
                                        <div class="rd-celda-sub">Justificación: <?= Html::encode((string) ($linea?->justificacion)) ?></div>
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
                            <td class="elaborar-directo">
                                <label class="d-flex align-items-center gap-1 mb-0">
                                    <?= Html::radio($nombre, $esDirecto, [
                                        'value' => $directo,
                                        'class' => 'elaborar-opcion',
                                        'disabled' => !$borrador,
                                    ]) ?>
                                    <?= Html::textInput('precio', $esDirecto ? (string) (float) $linea->precioUnitario : '', [
                                        'class' => 'qty elaborar-precio',
                                        'type' => 'number',
                                        'step' => '0.01',
                                        'min' => '0',
                                        'aria-label' => 'Precio unitario directo',
                                        'disabled' => !$borrador,
                                    ]) ?>
                                </label>
                                <?php if ($borrador): ?>
                                    <?= Html::textInput('fuente', $esDirecto ? (string) $linea->fuentePrecio : '', [
                                        'class' => 'form-control form-control-sm mt-1 elaborar-fuente',
                                        'maxlength' => 200,
                                        'placeholder' => 'Fuente (opcional)',
                                        'aria-label' => 'Fuente del precio directo',
                                        'title' => 'De dónde sale el precio: compra anterior, página del proveedor…',
                                    ]) ?>
                                    <?php if ($fila['sigma'] !== null): ?>
                                        <?php // El precio de HOY del catalogo; se congela al guardarlo. ?>
                                        <button type="button" class="btn btn-link btn-sm p-0 elaborar-usar-sigma"
                                                data-precio="<?= Html::encode((string) $fila['sigma']) ?>"
                                                data-fuente="<?= Html::encode(PresupuestoDetalle::FUENTE_SIGMA) ?>"
                                                title="Usar el precio referencial del catálogo SIGMA">
                                            SIGMA: <?= $formato->asDecimal($fila['sigma'], 2) ?>
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($esDirecto && !empty($linea->fuentePrecio)): ?>
                                    <div class="rd-celda-sub">Fuente: <?= Html::encode($linea->fuentePrecio) ?></div>
                                <?php endif; ?>
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
            <?php if ($presupuesto->manoDeObra !== null): ?>
                <?php // Solo informativa (se cambia en el panel de abajo): no suma al total. ?>
                <tbody>
                    <tr class="cat-row"><td colspan="<?= $columnas ?>">Mano de obra</td></tr>
                    <tr>
                        <td><div class="rd-celda-titulo"><?= Html::encode($presupuesto->manoDeObra) ?></div></td>
                        <td class="rd-num mono"><?= $formato->asDecimal(1, 2) ?></td>
                        <td><?= Html::encode($presupuesto::UNIDAD_MANO_DE_OBRA) ?></td>
                        <td colspan="<?= count($proveedores) + 1 ?>"><span class="rd-celda-sub">Personal de la DTIC: no se cotiza</span></td>
                        <td class="rd-num mono"><?= $formato->asDecimal(0, 2) ?></td>
                    </tr>
                </tbody>
            <?php endif; ?>
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

    <?php if ($borrador): ?>
        <div class="card rd-card-pad mt-3" id="elaborar-mano-de-obra">
            <?= Html::beginForm(['mano-de-obra', 'idProyecto' => $proyecto->id], 'post', ['class' => 'rd-filtros mb-0']) ?>
                <label class="d-flex align-items-center gap-2 mb-0 text-nowrap">
                    <?= Html::checkbox('incluir', $presupuesto->manoDeObra !== null, ['value' => 1]) ?>
                    <strong>Incluir mano de obra</strong>
                </label>
                <?= Html::textInput('texto', $presupuesto->manoDeObra ?? $presupuesto::MANO_DE_OBRA_DTIC, [
                    'class' => 'form-control',
                    'maxlength' => 300,
                    'aria-label' => 'Texto de la mano de obra',
                ]) ?>
                <?= Html::submitButton('Guardar', ['class' => 'btn btn-outline']) ?>
            <?= Html::endForm() ?>
            <div class="rd-nota mt-2">Sale en el PDF como la última fila, antes del total: unidad "<?= Html::encode($presupuesto::UNIDAD_MANO_DE_OBRA) ?>", cantidad 1 y precios en 0.00. No se cotiza ni suma al total.</div>
        </div>
    <?php endif; ?>

    <div class="rd-cols mt-3">
        <div class="card rd-col-2 rd-card-pad" id="elaborar-partidas">
            <?= $this->render('/presupuesto/_resumen-partidas', ['resumenPartidas' => $resumenPartidas]) ?>
        </div>
        <div class="card rd-col-1 rd-card-pad" id="elaborar-control">
            <?= $this->render('_control', ['control' => $control, 'presupuesto' => $presupuesto]) ?>
        </div>
    </div>

    <?= $this->render('_acciones', [
        'proyecto' => $proyecto,
        'presupuesto' => $presupuesto,
        'erroresEnvio' => $erroresEnvio,
        'estadosConBoton' => $estadosConBoton,
    ]) ?>

    <?php endif; ?>

</div>
