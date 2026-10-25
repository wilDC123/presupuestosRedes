<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\UnidadMedida $model
 */

$this->title = 'Editar unidad: ' . $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Unidades de medida', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="unidad-medida-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
