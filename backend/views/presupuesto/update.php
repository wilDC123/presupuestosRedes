<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $model
 * @var array<int, string> $proyectos
 */

$this->title = 'Editar presupuesto #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['/proyecto/index']];
$this->params['breadcrumbs'][] = ['label' => $model->proyecto->codigo ?: '#' . $model->idProyecto, 'url' => ['/proyecto/view', 'id' => $model->idProyecto]];
$this->params['breadcrumbs'][] = ['label' => 'Presupuesto', 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="presupuesto-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'proyectos' => $proyectos,
    ]) ?>

</div>
