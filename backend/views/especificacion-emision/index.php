<?php

declare(strict_types=1);

use backend\models\EspecificacionEmision;
use yii\grid\GridView;
use yii\helpers\Html;

/**
 * Registro de los formularios NET emitidos: reemplaza el Excel "1000
 * Especificaciones" de DTIC. Los filtros viajan por GET (?anio=, ?estado=,
 * ?dependencia=, ?buscar=) y "Exportar" descarga lo mismo como CSV.
 *
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider Ver EspecificacionEmisionService::registro()
 * @var array{anio: string, estado: string, dependencia: string, buscar: string} $filtros
 * @var array{anios: array<int, string>, dependencias: array<string, string>} $opciones Ver EspecificacionEmisionService::opcionesRegistro()
 * @var array<string, string> $estados
 */

$this->title = 'Formularios NET emitidos';
$this->params['breadcrumbs'][] = $this->title;
$formato = Yii::$app->formatter;
$hayFiltros = implode('', $filtros) !== '';
?>
<div class="especificacion-emision-index">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                Todos los formularios de especificación técnica con código NET. Se emiten y reemiten desde la lista de
                ítems de cada proyecto; los reemplazados y anulados se conservan.
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('Exportar a Excel (CSV)', array_merge(['exportar'], array_filter($filtros)), ['class' => 'btn btn-outline']) ?>
            <?= Html::a('Configuración del formulario NET', ['/especificacion/configuracion'], ['class' => 'btn btn-outline']) ?>
        </div>
    </div>

    <?php // rd-filtros + form-select: los filtros comparten una fila (form-control ocupa el 100% del ancho). ?>
    <?= Html::beginForm(['index'], 'get', ['class' => 'rd-filtros']) ?>
        <?= Html::dropDownList('anio', $filtros['anio'], $opciones['anios'], [
            'class' => 'form-select',
            'prompt' => 'Todos los años',
            'aria-label' => 'Año',
        ]) ?>
        <?= Html::dropDownList('estado', $filtros['estado'], $estados, [
            'class' => 'form-select',
            'prompt' => 'Todos los estados',
            'aria-label' => 'Estado',
        ]) ?>
        <?= Html::dropDownList('dependencia', $filtros['dependencia'], $opciones['dependencias'], [
            'class' => 'form-select',
            'prompt' => 'Todas las dependencias',
            'aria-label' => 'Dependencia',
        ]) ?>
        <?= Html::textInput('buscar', $filtros['buscar'], [
            'class' => 'form-control',
            'placeholder' => 'NET (880 o 880/26), título o código de proyecto...',
        ]) ?>
        <?= Html::submitButton('Filtrar', ['class' => 'btn btn-outline']) ?>
        <?php if ($hayFiltros): ?>
            <?= Html::a('Quitar filtros', ['index'], ['class' => 'btn btn-outline']) ?>
        <?php endif; ?>
    <?= Html::endForm() ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table'],
        'summary' => 'Mostrando {begin}–{end} de {totalCount} formularios',
        'emptyText' => $hayFiltros
            ? 'Ningún formulario coincide con los filtros.'
            : 'Todavía no se emitió ningún formulario NET. Se emiten al enlazar una especificación a un ítem de la lista de un proyecto.',
        'columns' => [
            [
                'label' => 'NET',
                'format' => 'raw',
                'contentOptions' => ['class' => 'mono text-nowrap'],
                'value' => static fn (EspecificacionEmision $e) => Html::a(Html::encode($e->codigoNet()), ['/especificacion/formulario', 'id' => $e->id], ['target' => '_blank']),
            ],
            [
                'label' => 'Fecha',
                'contentOptions' => ['class' => 'mono'],
                'value' => static fn (EspecificacionEmision $e) => $formato->asDate($e->fechaEmision, 'php:d/m/Y'),
            ],
            [
                'label' => 'Dependencia',
                'value' => static fn (EspecificacionEmision $e) => $e->dependencia ?: '—',
            ],
            [
                'label' => 'Especificación',
                'format' => 'raw',
                'value' => static fn (EspecificacionEmision $e) => '<div class="rd-celda-titulo">'
                    . Html::a(Html::encode($e->titulo), ['/especificacion/view', 'id' => $e->version->idEspecificacion, 'version' => $e->version->numero])
                    . ' <span class="rd-celda-sub">' . Html::encode($e->version->etiqueta()) . '</span></div>'
                    . (!empty($e->version->modeloSugerido) ? '<div class="rd-celda-sub">Modelo sugerido: ' . Html::encode($e->version->modeloSugerido) . '</div>' : ''),
            ],
            [
                'label' => 'Cantidad',
                'headerOptions' => ['class' => 'rd-num'],
                'contentOptions' => ['class' => 'rd-num mono text-nowrap'],
                'value' => static fn (EspecificacionEmision $e) => $formato->asDecimal($e->cantidad, 2) . ' ' . $e->unidad,
            ],
            [
                'label' => 'Proyecto',
                'format' => 'raw',
                'value' => static fn (EspecificacionEmision $e) => $e->proyecto === null
                    ? '—'
                    : Html::a(Html::encode($e->proyecto->codigo ?: '#' . $e->idProyecto), ['/lista-items/index', 'id' => $e->idProyecto])
                        . ($e->proyectoItem !== null
                            ? '<div class="rd-celda-sub">' . Html::encode($e->proyectoItem->subcatalogoItem->descripcion ?? '') . '</div>'
                            : '<div class="rd-celda-sub">Ítem quitado de la lista</div>'),
            ],
            [
                'label' => 'Estado',
                'format' => 'raw',
                'value' => static fn (EspecificacionEmision $e) => Html::tag('span', Html::encode($e->estadoLabel()), ['class' => $e->estadoBadgeClass()])
                    . ($e->reemplazadaPor !== null
                        ? '<div class="rd-celda-sub">por ' . Html::a(Html::encode($e->reemplazadaPor->codigoNet()), ['/especificacion/formulario', 'id' => $e->idReemplazadaPor], ['target' => '_blank']) . '</div>'
                        : '')
                    . (!empty($e->motivo) ? '<div class="rd-celda-sub">' . Html::encode($e->motivo) . '</div>' : ''),
            ],
            [
                'label' => 'Elaborado por',
                'value' => static fn (EspecificacionEmision $e) => $e->elaboradoPor ?: '—',
            ],
        ],
    ]) ?>

</div>
