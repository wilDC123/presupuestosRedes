<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Recibe las variables de ProyectoController::datosFormulario() y las pasa
 * tal cual a _form.php, donde estan documentadas.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $model
 */

$this->title = 'Editar proyecto';
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->codigo ?: '#' . $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="proyecto-update">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
        <div class="rd-acciones">
            <?= Html::a('Cancelar', ['view', 'id' => $model->id], ['class' => 'btn btn-outline']) ?>
            <?= Html::submitButton('Guardar cambios', ['class' => 'btn btn-primary', 'form' => 'proyecto-form']) ?>
        </div>
    </div>

    <?= $this->render('_form', [
        'model' => $model,
        'tipos' => $tipos,
        'facultades' => $facultades,
        'edificios' => $edificios,
        'bloques' => $bloques,
        'proyectistas' => $proyectistas,
        'arbolUbicacion' => $arbolUbicacion,
        'proyectosAprobados' => $proyectosAprobados,
    ]) ?>

</div>
