<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\TipoProyecto $model
 */

$this->title = 'Editar tipo de proyecto: ' . $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Tipos de proyecto', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="tipo-proyecto-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
