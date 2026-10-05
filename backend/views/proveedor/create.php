<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\Proveedor $model
 */

$this->title = 'Nuevo proveedor';
$this->params['breadcrumbs'][] = ['label' => 'Proveedores', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Nuevo';
?>
<div class="proveedor-create">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
        <div class="rd-acciones">
            <?= Html::a('Cancelar', ['index'], ['class' => 'btn btn-outline']) ?>
            <?= Html::submitButton('Guardar proveedor', ['class' => 'btn btn-primary', 'form' => 'proveedor-form']) ?>
        </div>
    </div>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
