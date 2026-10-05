<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\SubcatalogoItem $model
 * @var array<int, string> $categorias
 * @var backend\models\CatalogoSigma|null $sigma
 * @var string|null $partidaSigma
 * @var string[] $usadoPor
 */

$this->title = 'Nuevo ítem de subcatálogo';
$this->params['breadcrumbs'][] = ['label' => 'Subcatálogo de ítems', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="subcatalogo-item-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'categorias' => $categorias,
        'sigma' => $sigma,
        'partidaSigma' => $partidaSigma,
        'usadoPor' => $usadoPor,
    ]) ?>

</div>
