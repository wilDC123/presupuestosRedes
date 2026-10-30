<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Personal: las personas que se ponen en el equipo de un proyecto, con en
 * cuantos proyectos participa cada una (izquierda), y formulario rapido para
 * agregar una (derecha).
 *
 * @var yii\web\View $this
 * @var array<int, array{persona: backend\models\Personal, proyectos: int}> $filas Ver PersonalService::listarConConteo()
 * @var backend\models\Personal $nuevo modelo vacio para el formulario rapido
 */

$this->title = 'Personal';
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = 'Personal';
?>
<div class="personal-index">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="rd-cols">

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Personas registradas</div>
                <div class="kicker"><?= count($filas) ?> personas</div>
            </div>

            <?php if ($filas === []): ?>
                <div class="rd-vacio">Todavía no hay personas registradas.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Proyectos</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $fila): ?>
                            <?php $persona = $fila['persona']; ?>
                            <tr>
                                <td class="rd-celda-titulo"><?= Html::encode($persona->nombre) ?></td>
                                <td class="mono"><?= $fila['proyectos'] ?></td>
                                <td>
                                    <?= $persona->activo
                                        ? '<span class="badge b-activo">Activo</span>'
                                        : '<span class="badge b-inactivo">Inactivo</span>' ?>
                                </td>
                                <td>
                                    <div class="rd-acciones">
                                        <?= Html::a('Editar', ['update', 'id' => $persona->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                        <?= Html::a('Eliminar', ['delete', 'id' => $persona->id], [
                                            'class' => 'btn btn-danger btn-sm',
                                            'data' => [
                                                'confirm' => '¿Eliminar a "' . $persona->nombre . '"?',
                                                'method' => 'post',
                                            ],
                                        ]) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card rd-col-1 rd-card-pad">
            <div class="rd-card-titulo mb-3">Nueva persona</div>

            <?php // Envia a personal/create, la misma accion del formulario completo. ?>
            <?php $form = ActiveForm::begin(['action' => ['create']]); ?>

            <?= $form->field($nuevo, 'nombre')->textInput(['maxlength' => 200, 'placeholder' => 'Ej. Ing. Paola Rojas']) ?>

            <?= Html::submitButton('Guardar persona', ['class' => 'btn btn-primary']) ?>

            <?php ActiveForm::end(); ?>

            <p class="rd-nota mt-3 mb-0">
                Es el desplegable "Persona" del equipo del proyecto; el rol (proyectista, coordinador…) se elige en
                cada proyecto. Solo es una lista de nombres: no son usuarios del sistema. Una persona que está en
                proyectos no se elimina: se desactiva desde "Editar".
            </p>
        </div>

    </div>

</div>
