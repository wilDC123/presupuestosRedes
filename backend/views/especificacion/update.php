<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\Especificacion $model
 * @var array<int, string> $categorias Ver CategoriaService::opciones()
 * @var array<int, string> $items Ver SubcatalogoItemService::opciones()
 */

$this->title = 'Editar especificación: ' . $model->nombre();
$this->params['breadcrumbs'][] = ['label' => 'Librería de especificaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->nombre(), 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="especificacion-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'categorias' => $categorias,
        'items' => $items,
    ]) ?>

</div>
