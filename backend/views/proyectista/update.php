<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\Proyectista $model
 */

$this->title = 'Editar proyectista: ' . $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = ['label' => 'Proyectistas', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="proyectista-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
