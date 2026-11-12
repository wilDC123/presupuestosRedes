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

// "Agregar fase" abre este mismo formulario ya preparado como la fase siguiente.
$this->title = $model->idProyectoAnterior !== null
    ? 'Agregar fase ' . (int) $model->numeroFase . ' al proyecto ' . $model->numeroTexto()
    : 'Nuevo proyecto';
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->idProyectoAnterior !== null ? 'Agregar fase' : 'Nuevo';
?>
<div class="proyecto-create">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
        <div class="rd-acciones">
            <?= Html::a('Cancelar', ['index'], ['class' => 'btn btn-outline']) ?>
            <?= Html::submitButton('Guardar y continuar →', ['class' => 'btn btn-primary', 'form' => 'proyecto-form']) ?>
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
