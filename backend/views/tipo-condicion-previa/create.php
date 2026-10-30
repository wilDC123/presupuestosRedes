<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\TipoCondicionPrevia $model
 */

$this->title = 'Nuevo tipo';
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = ['label' => 'Tipos de trabajo complementario', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tipo-condicion-previa-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
