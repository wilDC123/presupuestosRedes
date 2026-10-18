<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * Ficha de la libreria: sus datos, el contenido tecnico de una version (la
 * actual, o una anterior del historial con ?version=N, solo lectura), el
 * historial de versiones y donde se usa.
 *
 * @var yii\web\View $this
 * @var backend\models\Especificacion $model
 * @var backend\models\EspecificacionVersion|null $version la que se muestra (null: ficha sin contenido todavia)
 * @var bool $esActual si $version es la version actual
 * @var array<int, array{version: backend\models\EspecificacionVersion, emisiones: int}> $historial Ver EspecificacionService::historial()
 * @var string|null $garantiaPorDefecto Ver ConfiguracionEspecificacion
 * @var array{proyectoItems: backend\models\ProyectoItem[], cotizacionDetalles: backend\models\CotizacionDetalle[]} $usos Ver EspecificacionService::usos()
 */

$this->title = $model->nombre();
$this->params['breadcrumbs'][] = ['label' => 'Librería de especificaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$formato = Yii::$app->formatter;
$emitida = array_sum(array_column($historial, 'emisiones')) > 0;
$enUso = $usos['proyectoItems'] !== [] || $usos['cotizacionDetalles'] !== [] || $emitida;
$urlAdjunto = $version?->urlAdjunto();
?>
<div class="especificacion-view">

    <div class="rd-pagehead">
        <h1>
            <?= Html::encode($this->title) ?>
            <?= $model->activo
                ? '<span class="badge b-activo">Activa</span>'
                : '<span class="badge b-inactivo">Inactiva</span>' ?>
        </h1>
        <div class="rd-acciones">
            <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
            <?php if (!$enUso): ?>
                <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
                    'class' => 'btn btn-danger',
                    'data' => [
                        'confirm' => '¿Está seguro de eliminar esta especificación de la librería?',
                        'method' => 'post',
                    ],
                ]) ?>
            <?php endif; ?>
        </div>
    </div>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'titulo',
            [
                'attribute' => 'idCategoria',
                'value' => $model->categoria->nombre ?? 'Sin categoría',
            ],
            [
                'attribute' => 'idSubcatalogo',
                'value' => $model->subcatalogoItem->descripcion ?? '—',
            ],
        ],
    ]) ?>

    <div class="card mt-3">
        <div class="rd-card-head">
            <div class="rd-card-titulo">
                Contenido técnico
                <?php if ($version !== null): ?>
                    — <?= Html::encode($version->etiqueta()) ?><?= $esActual ? ' (actual)' : '' ?>
                <?php endif; ?>
            </div>
            <?php if ($version !== null && !$esActual): ?>
                <div class="kicker"><?= Html::a('Ver la versión actual', ['view', 'id' => $model->id]) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($version === null): ?>
            <div class="rd-vacio">
                Esta ficha todavía no tiene contenido técnico. <?= Html::a('Agréguelo desde "Editar"', ['update', 'id' => $model->id]) ?>.
            </div>
        <?php else: ?>
            <?php if (!$esActual): ?>
                <div class="rd-card-pad pb-0">
                    <div class="alert alert-secondary mb-0">
                        Versión anterior, solo de consulta: es la que tienen los formularios NET emitidos con ella. Las
                        emisiones nuevas usan la versión actual.
                    </div>
                </div>
            <?php endif; ?>

            <?= DetailView::widget([
                'model' => $version,
                'options' => ['class' => 'table detail-view mb-0'],
                'attributes' => [
                    [
                        'attribute' => 'modeloSugerido',
                        'value' => $version->modeloSugerido ?: '—',
                    ],
                    [
                        'attribute' => 'garantia',
                        'format' => 'raw',
                        'value' => $version->garantia !== null
                            ? Html::encode($version->garantia)
                            : '<span class="rd-nota">Por defecto: ' . Html::encode($garantiaPorDefecto ?: '—') . '</span>',
                    ],
                    [
                        'attribute' => 'adjuntoRuta',
                        'format' => 'raw',
                        'value' => $urlAdjunto === null
                            ? '—'
                            : Html::a('Ver documento', $urlAdjunto, ['target' => '_blank', 'rel' => 'noopener']),
                    ],
                ],
            ]) ?>

            <?php $caracteristicas = $version->caracteristicas; ?>
            <?php if ($caracteristicas === []): ?>
                <div class="rd-vacio">Sin características cargadas.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 30%">Característica</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caracteristicas as $caracteristica): ?>
                            <tr>
                                <?php if ($caracteristica->nombre === null): ?>
                                    <td colspan="2"><?= nl2br(Html::encode($caracteristica->valor)) ?></td>
                                <?php else: ?>
                                    <td class="rd-celda-titulo"><?= Html::encode($caracteristica->nombre) ?></td>
                                    <td><?= nl2br(Html::encode($caracteristica->valor)) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($historial !== []): ?>
        <div class="card mt-3">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Historial de versiones</div>
                <div class="kicker">Una versión usada en un formulario NET ya no cambia: editarla crea la siguiente.</div>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Versión</th>
                        <th>Fecha</th>
                        <th>Nota del cambio</th>
                        <th class="rd-num">Formularios NET</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $i => $entrada): ?>
                        <?php $v = $entrada['version']; ?>
                        <tr class="<?= $version !== null && $v->id === $version->id ? 'rd-fila-activa' : '' ?>">
                            <td class="mono"><?= Html::encode($v->etiqueta()) ?><?= $i === 0 ? ' (actual)' : '' ?></td>
                            <td class="mono"><?= $formato->asDate($v->fechaCreacion, 'php:d/m/Y') ?></td>
                            <td><?= Html::encode($v->notaCambio ?: '—') ?></td>
                            <td class="rd-num mono"><?= $entrada['emisiones'] ?></td>
                            <td>
                                <?= Html::a('Ver', $i === 0 ? ['view', 'id' => $model->id] : ['view', 'id' => $model->id, 'version' => $v->numero], ['class' => 'btn btn-outline btn-sm']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="card mt-3">
        <div class="rd-card-head">
            <div class="rd-card-titulo">Dónde se usa</div>
            <div class="kicker">
                <?= count($usos['proyectoItems']) ?> ítem(s) de proyecto · <?= count($usos['cotizacionDetalles']) ?> línea(s) de cotización
            </div>
        </div>

        <?php if ($usos['proyectoItems'] === [] && $usos['cotizacionDetalles'] === []): ?>
            <div class="rd-vacio">
                Todavía no está enlazada a ningún ítem. Se enlaza desde la lista de ítems de un proyecto.
            </div>
        <?php else: ?>
            <?php if ($usos['proyectoItems'] !== []): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Ítem de la lista</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usos['proyectoItems'] as $item): ?>
                            <tr>
                                <td>
                                    <?= $item->proyecto === null
                                        ? '—'
                                        : Html::a(Html::encode($item->proyecto->etiqueta()), ['/lista-items/index', 'id' => $item->idProyecto]) ?>
                                </td>
                                <td><?= Html::encode($item->subcatalogoItem->descripcion ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($usos['cotizacionDetalles'] !== []): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Cotización</th>
                            <th>Ítem cotizado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usos['cotizacionDetalles'] as $detalle): ?>
                            <tr>
                                <td>
                                    <?= Html::a(
                                        Html::encode('N.° ' . (($detalle->cotizacion->numeroReferencia ?? null) ?: $detalle->idCotizacion)
                                            . ' — ' . ($detalle->cotizacion->proveedor->razonSocial ?? '?')),
                                        ['/cotizacion/view', 'id' => $detalle->idCotizacion]
                                    ) ?>
                                </td>
                                <td><?= Html::encode($detalle->subcatalogoItem->descripcion ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($enUso): ?>
            <div class="rd-card-pad rd-nota">
                Al estar en uso<?= $emitida ? ' (y con formularios NET emitidos)' : '' ?> no se puede eliminar. Si ya no debe
                ofrecerse, desactívela desde "Editar": los enlaces y los formularios emitidos se conservan.
            </div>
        <?php endif; ?>
    </div>

</div>
