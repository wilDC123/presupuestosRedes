<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\SubcatalogoItem $model
 */

$this->title = 'Nuevo ítem de subcatálogo';
$this->params['breadcrumbs'][] = ['label' => 'Subcatálogo de ítems', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="subcatalogo-item-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
