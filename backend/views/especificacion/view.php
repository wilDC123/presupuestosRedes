<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var backend\models\Especificacion $model
 * @var array{proyectoItems: backend\models\ProyectoItem[], cotizacionDetalles: backend\models\CotizacionDetalle[]} $usos Ver EspecificacionService::usos()
 */

$this->title = $model->nombre();
$this->params['breadcrumbs'][] = ['label' => 'Librería de especificaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$urlDocumento = $model->urlDocumento();
$enUso = $usos['proyectoItems'] !== [] || $usos['cotizacionDetalles'] !== [];
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
            [
                'attribute' => 'idCategoria',
                'value' => $model->categoria->nombre ?? 'Sin categoría',
            ],
            'codigoNet',
            'titulo',
            'dependencia',
            'modeloSugerido',
            [
                'attribute' => 'documentoRuta',
                'format' => 'raw',
                'value' => $urlDocumento === null
                    ? '—'
                    : Html::a(Html::encode($model->documentoRuta), $urlDocumento, ['target' => '_blank', 'rel' => 'noopener']),
            ],
            [
                'attribute' => 'fechaEmision',
                'value' => $model->fechaEmisionFormateada(),
            ],
            [
                'attribute' => 'idSubcatalogo',
                'value' => $model->subcatalogoItem->descripcion ?? '—',
            ],
        ],
    ]) ?>

    <div class="card mt-3">
        <div class="rd-card-head">
            <div class="rd-card-titulo">Dónde se usa</div>
            <div class="kicker">
                <?= count($usos['proyectoItems']) ?> ítem(s) de proyecto · <?= count($usos['cotizacionDetalles']) ?> línea(s) de cotización
            </div>
        </div>

        <?php if (!$enUso): ?>
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

            <div class="rd-card-pad rd-nota">
                Al estar en uso no se puede eliminar. Si ya no debe ofrecerse, desactívela desde "Editar": los enlaces existentes se conservan.
            </div>
        <?php endif; ?>
    </div>

</div>
