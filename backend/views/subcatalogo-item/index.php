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
 * @var string[] $idsSigmaExistentes
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
        Busca por descripción, rama comercial o clase.
    </p>

    <?php $form = ActiveForm::begin([
        'method' => 'get',
        'action' => ['index'],
    ]); ?>

    <div class="form-group">
        <?= Html::label('Texto a buscar', 'q') ?>
        <?= Html::textInput('q', $textoBusqueda, ['class' => 'form-control', 'placeholder' => 'Ej. camara, switch, cable...']) ?>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Buscar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

    <?php if ($textoBusqueda !== ''): ?>
        <?php if ($resultadosSigma === []): ?>
            <p>No se encontraron resultados en el catálogo institucional para "<?= Html::encode($textoBusqueda) ?>".</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Clase</th>
                        <th>Rama comercial</th>
                        <th>Precio referencial</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultadosSigma as $itemSigma): ?>
                        <?php $yaExiste = in_array($itemSigma->IdSigma, $idsSigmaExistentes, true); ?>
                        <tr>
                            <td><?= Html::encode($itemSigma->Descripcion) ?></td>
                            <td><?= Html::encode($itemSigma->Clase) ?></td>
                            <td><?= Html::encode($itemSigma->RamaComercial) ?></td>
                            <td><?= Html::encode($itemSigma->PrecioReferencial) ?></td>
                            <td>
                                <?php if ($yaExiste): ?>
                                    Ya está en tu subcatálogo
                                <?php else: ?>
                                    <?= Html::a('Agregar a mi subcatálogo', [
                                        'create',
                                        'idSigma' => $itemSigma->IdSigma,
                                        'descripcion' => $itemSigma->Descripcion,
                                    ], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

</div>
