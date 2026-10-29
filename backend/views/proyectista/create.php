<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\Proyectista $model
 */

$this->title = 'Nuevo proyectista';
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = ['label' => 'Proyectistas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="proyectista-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
