<?php

declare(strict_types=1);

use yii\grid\GridView;
use yii\helpers\Html;

/**
 * Subcatalogo de redes: los items propios (con filtros) y, debajo, el
 * buscador del catalogo institucional SIGMA para agregar items nuevos.
 *
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var array{buscar: string, idCategoria: string, estado: string, partida: string} $filtros valores actuales de los filtros ('' = sin filtrar)
 * @var array<int, string> $categorias [id => nombre], ver CategoriaService::opciones()
 * @var string[] $partidasEnUso
 * @var int $totalItems items del subcatalogo, sin filtrar
 * @var int $totalSigma items vigentes en dbo.CatalogoSigma
 * @var string $textoBusqueda texto buscado en SIGMA
 * @var backend\models\CatalogoSigma[] $resultadosSigma
 * @var bool $limiteAlcanzado true si la busqueda llego al maximo de filas (puede haber mas)
 * @var array<string, string[]> $nombresDticPorIdSigma idSigma en minusculas => nombres DTIC que ya lo usan
 */

$this->title = 'Subcatálogo de redes';
$this->params['breadcrumbs'][] = 'Subcatálogo';
$formato = Yii::$app->formatter;
?>
<div class="subcatalogo-item-index">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                <?= $formato->asInteger($totalItems) ?> ítems seleccionados de
                <?= $formato->asInteger($totalSigma) ?> en el catálogo SIGMA institucional
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('Nuevo ítem sin SIGMA', ['create'], ['class' => 'btn btn-outline']) ?>
            <?= Html::a('+ Buscar en SIGMA y agregar', '#buscar-sigma', ['class' => 'btn btn-primary']) ?>
        </div>
    </div>

    <?= Html::beginForm(['index'], 'get', ['class' => 'rd-filtros']) ?>
        <?= Html::textInput('buscar', $filtros['buscar'], [
            'class' => 'form-control',
            'placeholder' => 'Buscar ítem por nombre DTIC...',
        ]) ?>
        <?= Html::dropDownList('idCategoria', $filtros['idCategoria'], $categorias, [
            'class' => 'form-select',
            'prompt' => 'Todas las categorías',
        ]) ?>
        <?= Html::dropDownList('partida', $filtros['partida'], array_combine($partidasEnUso, $partidasEnUso), [
            'class' => 'form-select',
            'prompt' => 'Todas las partidas',
        ]) ?>
        <?= Html::dropDownList('estado', $filtros['estado'], ['activo' => 'Activo', 'inactivo' => 'Inactivo'], [
            'class' => 'form-select',
            'prompt' => 'Todos los estados',
        ]) ?>
        <?= Html::submitButton('Filtrar', ['class' => 'btn btn-outline']) ?>
        <?php if (array_filter($filtros) !== []): ?>
            <?= Html::a('Limpiar', ['index']) ?>
        <?php endif; ?>
    <?= Html::endForm() ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table'],
        'summary' => 'Mostrando {begin}–{end} de {totalCount} ítems',
        'emptyText' => 'No hay ítems que coincidan con los filtros.',
        'columns' => [
            [
                'attribute' => 'descripcion',
                'format' => 'raw',
                'value' => static fn ($model) => Html::a(Html::encode($model->descripcion), ['view', 'id' => $model->id]),
            ],
            [
                'label' => 'Categoría',
                'format' => 'raw',
                'value' => static fn ($model) => '<span class="cat-chip">' . Html::encode($model->categoria->nombre ?? '—') . '</span>',
            ],
            [
                'label' => 'Nombre SIGMA',
                'contentOptions' => ['class' => 'rd-celda-sub'],
                'value' => static fn ($model) => $model->catalogoSigma->Descripcion ?? 'Sin ítem SIGMA',
            ],
            [
                'attribute' => 'partida',
                'contentOptions' => ['class' => 'mono'],
            ],
            [
                'label' => 'Unidad',
                'value' => static fn ($model) => $model->unidad->abreviatura ?? '—',
            ],
            [
                'label' => 'Estado',
                'format' => 'raw',
                'value' => static fn ($model) => $model->activo
                    ? '<span class="badge b-activo">Activo</span>'
                    : '<span class="badge b-inactivo">Inactivo</span>',
            ],
            [
                'format' => 'raw',
                'value' => static fn ($model) => Html::a('Editar', ['update', 'id' => $model->id]),
            ],
        ],
    ]); ?>

    <h2 id="buscar-sigma">Buscar en catálogo institucional</h2>

    <div class="card rd-card-pad">
        <p class="rd-nota">
            Catálogo nacional SIGMA (<span class="mono">dbo.CatalogoSigma</span>), de solo lectura.
            Escriba una o varias palabras: se muestran los ítems vigentes que las contienen
            <strong>todas</strong>, en cualquier orden, en la descripción, la clase o la rama comercial
            (ej. <em>jack cat 6</em>). Cada palabra se busca tal como está escrita en SIGMA:
            <em>RJ45</em> no encuentra <em>RJ-45</em>.
        </p>

        <?php // Sin '#' en la ruta: en un formulario GET, Yii pasa la ruta al campo oculto "r" y el ancla quedaria pegada a ella (404). ?>
        <?= Html::beginForm(['index'], 'get', ['class' => 'rd-filtros mb-0']) ?>
            <?= Html::textInput('q', $textoBusqueda, [
                'class' => 'form-control',
                'placeholder' => 'Ej. jack cat 6, switch poe, cable utp...',
                // Tras buscar, el foco lleva la pantalla hasta aqui (lo que hacia el ancla).
                'autofocus' => $textoBusqueda !== '',
            ]) ?>
            <?= Html::submitButton('Buscar en SIGMA', ['class' => 'btn btn-primary']) ?>
        <?= Html::endForm() ?>
    </div>

    <?php if ($textoBusqueda !== ''): ?>
        <?php if ($resultadosSigma === []): ?>
            <div class="card rd-vacio mt-3">
                No se encontraron resultados en el catálogo institucional para "<?= Html::encode($textoBusqueda) ?>".
            </div>
        <?php else: ?>
            <?php if ($limiteAlcanzado): ?>
                <div class="alert alert-warning mt-3">
                    Se muestran solo los primeros <?= count($resultadosSigma) ?> resultados y puede haber más.
                    Agregue otra palabra para afinar la búsqueda.
                </div>
            <?php endif; ?>
            <div class="card mt-3">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Clase</th>
                            <th>Rama comercial</th>
                            <th>Partida</th>
                            <th class="rd-num">Precio ref.</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosSigma as $itemSigma): ?>
                            <?php // strtolower(): el mapa viene con las claves en minusculas (ver SubcatalogoItemService::nombresDticPorIdSigma()). ?>
                            <?php $usadoPor = $nombresDticPorIdSigma[strtolower((string) $itemSigma->IdSigma)] ?? []; ?>
                            <tr>
                                <td><?= Html::encode($itemSigma->Descripcion) ?></td>
                                <td><?= Html::encode($itemSigma->Clase) ?></td>
                                <td><?= Html::encode($itemSigma->RamaComercial) ?></td>
                                <td class="mono"><?= Html::encode($itemSigma->IdGasto) ?></td>
                                <td class="rd-num mono"><?= (float) $itemSigma->PrecioReferencial > 0 ? $formato->asDecimal($itemSigma->PrecioReferencial, 2) : '—' ?></td>
                                <td>
                                    <?= Html::a('Agregar', [
                                        'create',
                                        'idSigma' => $itemSigma->IdSigma,
                                        // Para volver a estos mismos resultados despues de guardar.
                                        'q' => $textoBusqueda,
                                    ],['class' => 'btn btn-outline btn-sm']) ?>
                                    <?php if ($usadoPor !== []): ?>
                                        <div class="rd-celda-sub">Ya usado por: <?= Html::encode(implode(', ', $usadoPor)) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
