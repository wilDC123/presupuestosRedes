<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\TipoOrdenCambio $model
 */

$this->title = 'Editar tipo: ' . $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = ['label' => 'Tipos de orden de cambio', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="tipo-orden-cambio-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
