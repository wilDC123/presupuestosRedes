<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\SubcatalogoItem $model
 * @var array<int, string> $categorias
 * @var array<int, string> $unidades
 * @var backend\models\CatalogoSigma|null $sigma
 * @var string|null $partidaSigma
 * @var string[] $usadoPor
 */

$this->title = 'Editar ítem: ' . $model->descripcion;
$this->params['breadcrumbs'][] = ['label' => 'Subcatálogo de ítems', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->descripcion, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="subcatalogo-item-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'categorias' => $categorias,
        'unidades' => $unidades,
        'sigma' => $sigma,
        'partidaSigma' => $partidaSigma,
        'usadoPor' => $usadoPor,
    ]) ?>

</div>
