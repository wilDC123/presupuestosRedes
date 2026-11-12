<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Recibe las variables de ProyectoController::datosFormulario() y las pasa
 * tal cual a _form.php, donde estan documentadas.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $model
 * @var bool $datosComunesFijos ver ProyectoService::datosComunesFijos()
 * @var string|null $avisoInfraestructura si UsfxGEO no responde (ver _form.php)
 */

$this->title = 'Editar proyecto';
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->identificador(), 'url' => ['view', 'id' => $model->id]];
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
        'procesos' => $procesos,
        'sugerenciasUnidad' => $sugerenciasUnidad,
        'edificios' => $edificios,
        'areas' => $areas,
        'personal' => $personal,
        'roles' => $roles,
        'datosComunesFijos' => $datosComunesFijos,
        'avisoInfraestructura' => $avisoInfraestructura,
    ]) ?>

</div>
