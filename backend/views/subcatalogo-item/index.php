<?php

declare(strict_types=1);

use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var string $textoBusqueda
 * @var backend\models\CatalogoSigma[] $resultadosSigma
 * @var bool $limiteAlcanzado true si la busqueda llego al maximo de filas (puede haber mas)
 * @var string $partida partida por la que se esta filtrando ('' = todas)
 * @var string[] $partidasEnUso
 * @var array<string, string[]> $nombresDticPorIdSigma idSigma en minusculas => nombres DTIC que ya lo usan
 */

$this->title = 'Subcatálogo de ítems';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="subcatalogo-item-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <h2>Mi subcatálogo</h2>

    <p>
        <?= Html::a('Nuevo ítem', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= Html::beginForm(['index'], 'get', ['class' => 'row g-2 align-items-end mb-3']) ?>
        <div class="col-auto">
            <?= Html::label('Filtrar por partida', 'partida', ['class' => 'form-label']) ?>
            <?= Html::dropDownList('partida', $partida, array_combine($partidasEnUso, $partidasEnUso), [
                'id' => 'partida',
                'class' => 'form-select',
                'prompt' => 'Todas',
            ]) ?>
        </div>
        <div class="col-auto">
            <?= Html::submitButton('Filtrar', ['class' => 'btn btn-outline-primary']) ?>
        </div>
    <?= Html::endForm() ?>

    <?php Pjax::begin(); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => \yii\grid\SerialColumn::class],
            'descripcion',
            [
                'attribute' => 'categoria.nombre',
                'label' => 'Categoría',
            ],
            [
                'label' => 'Nombre SIGMA',
                'value' => static fn ($model) => $model->catalogoSigma->Descripcion ?? null,
            ],
            'partida',
            'unidadDefecto',
            [
                'attribute' => 'activo',
                'value' => static fn ($model) => $model->activo ? 'Sí' : 'No',
                'filter' => false,
            ],
            ['class' => ActionColumn::class],
        ],
    ]); ?>
    <?php Pjax::end(); ?>

    <hr>

    <h2>Buscar en catálogo institucional</h2>

    <p>
        Catálogo nacional SIGMA (<code>dbo.CatalogoSigma</code>), de solo lectura.
        Escriba una o varias palabras: se muestran los ítems vigentes que las contienen
        <strong>todas</strong>, en cualquier orden, en la descripción, la clase o la rama comercial
        (ej. <em>jack cat 6</em>). Cada palabra se busca tal como está escrita en SIGMA:
        <em>RJ45</em> no encuentra <em>RJ-45</em>.
    </p>

    <?php $form = ActiveForm::begin([
        'method' => 'get',
        'action' => ['index'],
    ]); ?>

    <div class="form-group">
        <?= Html::label('Texto a buscar', 'q') ?>
        <?= Html::textInput('q', $textoBusqueda, ['class' => 'form-control', 'placeholder' => 'Ej. jack cat 6, switch poe, cable utp...']) ?>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Buscar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

    <?php if ($textoBusqueda !== ''): ?>
        <?php if ($resultadosSigma === []): ?>
            <p>No se encontraron resultados en el catálogo institucional para "<?= Html::encode($textoBusqueda) ?>".</p>
        <?php else: ?>
            <?php if ($limiteAlcanzado): ?>
                <div class="alert alert-warning">
                    Se muestran solo los primeros <?= count($resultadosSigma) ?> resultados y puede haber más.
                    Agregue otra palabra para afinar la búsqueda.
                </div>
            <?php endif; ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Clase</th>
                        <th>Rama comercial</th>
                        <th>Partida</th>
                        <th>Precio referencial</th>
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
                            <td><?= Html::encode($itemSigma->IdGasto) ?></td>
                            <td><?= Html::encode($itemSigma->PrecioReferencial) ?></td>
                            <td>
                                <?= Html::a('Agregar a mi subcatálogo', [
                                    'create',
                                    'idSigma' => $itemSigma->IdSigma,
                                ], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                <?php if ($usadoPor !== []): ?>
                                    <div class="small text-muted">Ya usado por: <?= Html::encode(implode(', ', $usadoPor)) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

</div>
