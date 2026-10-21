<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\ComputoMetrico $model
 * @var array<string, string> $unidades ver UnidadMedidaService::opcionesPorAbreviatura()
 * @var array<int, string> $proyectos ver ProyectoService::opciones()
 */

$this->title = 'Editar cómputo métrico #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Cómputos métricos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => '#' . $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="computo-metrico-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'unidades' => $unidades,
        'proyectos' => $proyectos,
    ]) ?>

</div>
