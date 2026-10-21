<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var backend\models\SubcatalogoItem $model
 * @var string $textoBusqueda ultima busqueda en SIGMA ('' si no se llego desde ella)
 */

$this->title = $model->descripcion;
$this->params['breadcrumbs'][] = ['label' => 'Subcatálogo de ítems', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="subcatalogo-item-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Está seguro de eliminar este ítem?',
                'method' => 'post',
            ],
        ]) ?>
        <?php // ms-4 (Bootstrap 5: margin-left) separa este grupo de las acciones sobre el item. ?>
        <?php // Con busqueda previa vuelve a sus resultados; sin ella, al buscador vacio. '#' esta bien aqui: es un enlace, no un formulario GET. ?>
        <?= Html::a(
            $textoBusqueda !== '' ? '+ Agregar otro desde SIGMA ("' . Html::encode($textoBusqueda) . '")' : '+ Agregar otro desde SIGMA',
            ['index', 'q' => $textoBusqueda !== '' ? $textoBusqueda : null, '#' => 'buscar-sigma'],
            ['class' => 'btn btn-outline ms-4']
        ) ?>
        <?= Html::a('Nuevo ítem sin SIGMA', ['create'], ['class' => 'btn btn-outline']) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'idSigma',
            [
                'attribute' => 'categoria.nombre',
                'label' => 'Categoría',
            ],
            'descripcion',
            [
                'label' => 'Nombre SIGMA',
                'value' => $model->catalogoSigma->Descripcion ?? null,
            ],
            'partida',
            [
                'label' => 'Unidad de medida',
                'value' => $model->unidad?->etiqueta(),
            ],
            [
                'attribute' => 'activo',
                'value' => $model->activo ? 'Sí' : 'No',
            ],
            'fechaRegistro',
        ],
    ]) ?>

</div>
