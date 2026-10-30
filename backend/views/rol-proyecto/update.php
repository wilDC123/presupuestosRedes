<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\RolProyecto $model
 */

$this->title = 'Editar rol: ' . $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = ['label' => 'Roles del proyecto', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="rol-proyecto-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
