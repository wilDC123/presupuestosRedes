<?php

declare(strict_types=1);

use backend\models\Proyecto;
use yii\helpers\Html;
use yii\web\JqueryAsset;

/**
 * Solicitudes de cotizacion del proyecto: a la izquierda los items de la
 * lista que aun no estan en ninguna solicitud; a la derecha, una tarjeta por
 * solicitud (rubro) con sus items.
 *
 * Para asignar: marcar items, elegir la solicitud de destino (o "Nueva
 * solicitud") y pulsar "Mover". Es un formulario POST normal;
 * web/js/solicitud-cotizacion.js solo muestra el campo del nombre y valida
 * antes de enviar. El servidor vuelve a validar todo.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * Cada solicitud muestra las cotizaciones que respondieron los proveedores
 * ("+ Registrar cotización recibida") y el enlace a su comparativo. Una
 * solicitud con cotizaciones ya no se elimina ni cambia sus items.
 *
 * @var backend\models\SolicitudCotizacion[] $solicitudes con items y cotizaciones cargados
 * @var backend\models\ProyectoItem[] $sinAsignar items de la lista sin solicitud
 * @var bool $editable Ver SolicitudCotizacionService::puedeEditarse()
 * @var array<int, string> $modelosSugeridos [idProyectoItem => modelo sugerido de su especificacion]
 */

if ($editable) {
    $this->registerJsFile('@web/js/solicitud-cotizacion.js', ['depends' => [JqueryAsset::class]]);
}

$this->title = 'Solicitudes de cotización';
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['/proyecto/index']];
$this->params['breadcrumbs'][] = ['label' => $proyecto->codigo ?: '#' . $proyecto->id, 'url' => ['/proyecto/view', 'id' => $proyecto->id]];
$this->params['breadcrumbs'][] = 'Solicitudes de cotización';

$formato = Yii::$app->formatter;

$destinos = [];

foreach ($solicitudes as $solicitud) {
    // Una solicitud que ya tiene cotizaciones no recibe mas items.
    if ($solicitud->cotizaciones === []) {
        $destinos[$solicitud->id] = $solicitud->codigoCorto() . ' · ' . $solicitud->nombre;
    }
}

$destinos['nueva'] = '+ Nueva solicitud...';
?>
<div class="solicitud-cotizacion">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub"><?= Html::encode($proyecto->etiqueta()) ?></div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('← Lista de ítems', ['/lista-items/index', 'id' => $proyecto->id], ['class' => 'btn btn-outline']) ?>
            <?php if ($editable && $sinAsignar !== []): ?>
                <?= Html::a('Crear una por clasificación', ['por-clasificacion', 'id' => $proyecto->id], [
                    'class' => 'btn btn-outline',
                    'data' => [
                        'method' => 'post',
                        'confirm' => 'Se creará una solicitud por cada clasificación que tenga ítems sin asignar. Después podrá mover los ítems que no correspondan. ¿Continuar?',
                    ],
                ]) ?>
            <?php endif; ?>
        </div>
    </div>

    <p class="rd-nota">
        No todos los proveedores venden lo mismo. Reparta los ítems de la lista en solicitudes, una por rubro
        (ferretería y ducteo, equipamiento de red, fibra óptica...), e imprima el documento de cada una para enviarlo.
        Cada ítem va en una sola solicitud; las cantidades son las de la lista.
    </p>

    <?php if (!$editable): ?>
        <div class="notice mb-3">
            <?php if ($proyecto->estadoLista === Proyecto::LISTA_EDICION): ?>
                La lista de ítems está en edición. Las solicitudes se arman cuando la lista se envía a cotización
                (<?= Html::a('ir a la lista', ['/lista-items/index', 'id' => $proyecto->id]) ?>), para que ítems y cantidades ya no cambien.
            <?php else: ?>
                Las solicitudes de este proyecto ya no se modifican. Todavía puede imprimirlas.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="rd-cols">

        <div class="card rd-col-1">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Ítems sin asignar</div>
                <div class="kicker"><?= count($sinAsignar) ?> de la lista</div>
            </div>

            <?php if ($sinAsignar === []): ?>
                <div class="rd-vacio">
                    <?= $solicitudes === [] ? 'La lista del proyecto no tiene ítems.' : 'Todos los ítems de la lista ya están en una solicitud.' ?>
                </div>
            <?php else: ?>
                <?= Html::beginForm(['mover', 'id' => $proyecto->id], 'post', ['id' => 'solicitud-mover-form']) ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <?php if ($editable): ?>
                                        <input type="checkbox" id="solicitud-marcar-todos" aria-label="Marcar todos">
                                    <?php endif; ?>
                                </th>
                                <th>Ítem</th>
                                <th class="rd-num">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sinAsignar as $item): ?>
                                <tr>
                                    <td>
                                        <?php if ($editable): ?>
                                            <?= Html::checkbox('items[]', false, ['value' => $item->id, 'class' => 'solicitud-item', 'id' => 'solicitud-item-' . $item->id]) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <label for="solicitud-item-<?= $item->id ?>" class="solicitud-item-label">
                                            <?= Html::encode($item->subcatalogoItem->descripcion ?? 'Ítem no disponible') ?>
                                        </label>
                                        <div class="rd-celda-sub">
                                            <?= Html::encode($item->categoria->nombre ?? '') ?>
                                            <?= !empty($item->observacion) ? ' · ' . Html::encode($item->observacion) : '' ?>
                                        </div>
                                    </td>
                                    <td class="rd-num mono">
                                        <?= $formato->asDecimal($item->cantidad, 2) ?>
                                        <div class="rd-celda-sub"><?= Html::encode((string) $item->unidadMedida) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($editable): ?>
                        <div class="rd-card-pad">
                            <div class="alert alert-danger d-none" id="solicitud-error-cliente"></div>
                            <div class="form-group">
                                <label for="solicitud-destino">Mover los marcados a</label>
                                <?= Html::dropDownList('destino', $solicitudes === [] ? 'nueva' : null, $destinos, ['class' => 'form-select', 'id' => 'solicitud-destino']) ?>
                            </div>
                            <div class="form-group<?= $solicitudes === [] ? '' : ' d-none' ?>" id="solicitud-nombre-nueva">
                                <label for="solicitud-nombre">Nombre de la solicitud (rubro)</label>
                                <?= Html::textInput('nombreNueva', '', ['class' => 'form-control', 'id' => 'solicitud-nombre', 'maxlength' => 200, 'placeholder' => 'Ej. Ferretería y ducteo']) ?>
                            </div>
                            <?= Html::submitButton('Mover', ['class' => 'btn btn-primary']) ?>
                        </div>
                    <?php endif; ?>
                <?= Html::endForm() ?>
            <?php endif; ?>
        </div>

        <div class="rd-col-2 rd-pila">
            <?php if ($solicitudes === []): ?>
                <div class="card rd-vacio">
                    Todavía no hay solicitudes de cotización.
                    <?= $editable ? 'Marque ítems a la izquierda y muévalos a una "Nueva solicitud", o use "Crear una por clasificación".' : '' ?>
                </div>
            <?php endif; ?>

            <?php foreach ($solicitudes as $solicitud): ?>
                <?php $conCotizaciones = $solicitud->cotizaciones !== []; ?>
                <div class="card">
                    <div class="rd-card-head">
                        <div>
                            <div class="rd-card-titulo">
                                <span class="mono"><?= Html::encode($solicitud->codigoCorto()) ?></span>
                                · <?= Html::encode($solicitud->nombre) ?>
                            </div>
                            <?php if (!empty($solicitud->nota)): ?>
                                <div class="rd-celda-sub"><?= Html::encode($solicitud->nota) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="rd-acciones">
                            <span class="kicker"><?= count($solicitud->items) ?> ítems</span>
                            <?= Html::a('Imprimir', ['imprimir', 'id' => $solicitud->id], ['class' => 'btn btn-outline btn-sm', 'target' => '_blank']) ?>
                            <?php if ($editable && !$conCotizaciones): ?>
                                <?= Html::a('Eliminar', ['eliminar', 'id' => $solicitud->id], [
                                    'class' => 'btn btn-danger btn-sm',
                                    'data' => [
                                        'method' => 'post',
                                        'confirm' => '¿Eliminar ' . $solicitud->codigoCorto() . '? Sus ítems volverán a "sin asignar".',
                                    ],
                                ]) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php $editaReferencias = $editable && !$conCotizaciones; ?>
                    <?php if ($solicitud->items === []): ?>
                        <div class="rd-vacio">Esta solicitud no tiene ítems.</div>
                    <?php else: ?>
                        <?php // Formulario de las marcas y modelos de referencia; "Quitar" va en su propio formulario (data-method). ?>
                        <?= $editaReferencias ? Html::beginForm(['referencias', 'id' => $solicitud->id], 'post', ['id' => 'referencias-' . $solicitud->id]) : '' ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ítem</th>
                                    <th>Marca y modelo de referencia</th>
                                    <th>Unidad</th>
                                    <th class="rd-num">Cantidad</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solicitud->items as $fila): ?>
                                    <?php $item = $fila->proyectoItem; ?>
                                    <tr>
                                        <td>
                                            <?= Html::encode($item->subcatalogoItem->descripcion ?? 'Ítem no disponible') ?>
                                            <?php if (!empty($item->observacion)): ?>
                                                <div class="rd-celda-sub"><?= Html::encode($item->observacion) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($editaReferencias): ?>
                                                <?php $sugerido = $modelosSugeridos[(int) $fila->idProyectoItem] ?? null; ?>
                                                <?= Html::textInput('referencias[' . $fila->idProyectoItem . ']', (string) $fila->marcaModeloReferencia, [
                                                    'class' => 'form-control form-control-sm',
                                                    'maxlength' => 300,
                                                    'placeholder' => $sugerido !== null ? 'Sugerido por la especificación: ' . $sugerido : 'Opcional: marca, modelo',
                                                    'aria-label' => 'Marca y modelo de referencia',
                                                ]) ?>
                                            <?php else: ?>
                                                <?= Html::encode((string) ($fila->marcaModeloReferencia ?: '—')) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= Html::encode((string) ($item->unidadMedida ?? '')) ?></td>
                                        <td class="rd-num mono"><?= $formato->asDecimal($item->cantidad ?? 0, 2) ?></td>
                                        <td class="rd-num">
                                            <?php if ($editaReferencias): ?>
                                                <?= Html::a('Quitar', ['quitar-item', 'id' => $proyecto->id, 'item' => $fila->idProyectoItem], [
                                                    'class' => 'btn btn-outline btn-sm',
                                                    'data' => ['method' => 'post'],
                                                ]) ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($editaReferencias): ?>
                            <div class="rd-card-pad pt-0">
                                <?= Html::submitButton('Guardar marcas y modelos', ['class' => 'btn btn-outline btn-sm']) ?>
                                <span class="rd-nota">Salen en el documento junto a la descripción del ítem. Lo que el proveedor ofrece se registra en su cotización.</span>
                            </div>
                        <?php endif; ?>
                        <?= $editaReferencias ? Html::endForm() : '' ?>
                    <?php endif; ?>

                    <div class="rd-card-pad">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="rd-card-titulo">Cotizaciones recibidas</div>
                            <div class="rd-acciones">
                                <?php if ($conCotizaciones): ?>
                                    <?= Html::a('Comparativo', ['comparativo', 'id' => $solicitud->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                <?php endif; ?>
                                <?php if ($editable && $solicitud->items !== []): ?>
                                    <?= Html::a('+ Registrar cotización recibida', ['cotizar', 'id' => $solicitud->id], ['class' => 'btn btn-primary btn-sm']) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!$conCotizaciones): ?>
                            <div class="rd-nota">
                                Ningún proveedor ha respondido todavía. Cuando llegue una cotización, regístrela aquí:
                                los ítems ya vienen cargados, solo se escriben los precios.
                            </div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Proveedor</th>
                                        <th>Fecha</th>
                                        <th class="rd-num">Ítems</th>
                                        <th class="rd-num">Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitud->cotizaciones as $cotizacion): ?>
                                        <tr>
                                            <td>
                                                <?= Html::encode($cotizacion->proveedor->razonSocial ?? '?') ?>
                                                <?php if (!empty($cotizacion->numeroReferencia)): ?>
                                                    <div class="rd-celda-sub">Ref. <?= Html::encode($cotizacion->numeroReferencia) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= Html::encode($cotizacion->fechaFormateada() ?: '—') ?>
                                                <?= $cotizacion->estaVencida() ? '<span class="badge b-cancelado">Vencida</span>' : '' ?>
                                            </td>
                                            <td class="rd-num mono"><?= count($cotizacion->cotizacionDetalles) ?> de <?= count($solicitud->items) ?></td>
                                            <td class="rd-num mono"><?= $formato->asDecimal($cotizacion->total(), 2) ?></td>
                                            <td class="rd-num">
                                                <?php if (!empty($cotizacion->documentoRuta)): ?>
                                                    <?= Html::a('PDF', $cotizacion->urlDocumento(), ['class' => 'btn btn-outline btn-sm', 'target' => '_blank']) ?>
                                                <?php endif; ?>
                                                <?php if ($editable): ?>
                                                    <?= Html::a('Corregir', ['editar-cotizacion', 'id' => $cotizacion->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                                    <?= Html::a('Eliminar', ['eliminar-cotizacion', 'id' => $cotizacion->id], [
                                                        'class' => 'btn btn-danger btn-sm',
                                                        'data' => [
                                                            'method' => 'post',
                                                            'confirm' => '¿Eliminar la cotización de ' . ($cotizacion->proveedor->razonSocial ?? 'este proveedor') . '?',
                                                        ],
                                                    ]) ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <?php if ($editable): ?>
                        <?php // <details>: el formulario para renombrar queda plegado hasta que se pulsa. ?>
                        <details class="rd-card-pad">
                            <summary class="rd-nota">Cambiar nombre o nota</summary>
                            <?= Html::beginForm(['actualizar', 'id' => $solicitud->id], 'post', ['class' => 'mt-2']) ?>
                                <div class="form-group">
                                    <label>Nombre de la solicitud (rubro)</label>
                                    <?= Html::textInput('SolicitudCotizacion[nombre]', $solicitud->nombre, ['class' => 'form-control', 'maxlength' => 200, 'required' => true]) ?>
                                </div>
                                <div class="form-group">
                                    <label>Nota para el proveedor (opcional, sale en el documento)</label>
                                    <?= Html::textarea('SolicitudCotizacion[nota]', (string) $solicitud->nota, ['class' => 'form-control', 'rows' => 2, 'maxlength' => 500]) ?>
                                </div>
                                <?= Html::submitButton('Guardar', ['class' => 'btn btn-outline btn-sm']) ?>
                            <?= Html::endForm() ?>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

</div>
