<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\UnidadMedida $model
 */

$this->title = 'Nueva unidad de medida';
$this->params['breadcrumbs'][] = ['label' => 'Unidades de medida', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="unidad-medida-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
