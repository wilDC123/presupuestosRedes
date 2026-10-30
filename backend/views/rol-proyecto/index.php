<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Roles del proyecto: listado con en cuantos proyectos se usa cada uno
 * (izquierda) y formulario rapido para agregar uno (derecha).
 *
 * @var yii\web\View $this
 * @var array<int, array{rol: backend\models\RolProyecto, proyectos: int}> $filas Ver RolProyectoService::listarConConteo()
 * @var backend\models\RolProyecto $nuevo modelo vacio para el formulario rapido
 */

$this->title = 'Roles del proyecto';
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rol-proyecto-index">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="rd-cols">

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Roles registrados</div>
                <div class="kicker"><?= count($filas) ?> roles</div>
            </div>

            <?php if ($filas === []): ?>
                <div class="rd-vacio">Todavía no hay roles.</div>
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
                            <?php $rol = $fila['rol']; ?>
                            <tr>
                                <td class="rd-celda-titulo"><?= Html::encode($rol->nombre) ?></td>
                                <td class="mono"><?= $fila['proyectos'] ?></td>
                                <td>
                                    <?= $rol->activo
                                        ? '<span class="badge b-activo">Activo</span>'
                                        : '<span class="badge b-inactivo">Inactivo</span>' ?>
                                </td>
                                <td>
                                    <div class="rd-acciones">
                                        <?= Html::a('Editar', ['update', 'id' => $rol->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                        <?= Html::a('Eliminar', ['delete', 'id' => $rol->id], [
                                            'class' => 'btn btn-danger btn-sm',
                                            'data' => [
                                                'confirm' => '¿Eliminar el rol "' . $rol->nombre . '"?',
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
            <div class="rd-card-titulo mb-3">Nuevo rol</div>

            <?php // Envia a rol-proyecto/create, la misma accion del formulario completo. ?>
            <?php $form = ActiveForm::begin(['action' => ['create']]); ?>

            <?= $form->field($nuevo, 'nombre')->textInput(['maxlength' => 100, 'placeholder' => 'Ej. Coordinador de infraestructura']) ?>

            <?= Html::submitButton('Guardar rol', ['class' => 'btn btn-primary']) ?>

            <?php ActiveForm::end(); ?>

            <p class="rd-nota mt-3 mb-0">
                Es el desplegable "Rol" del equipo del proyecto: con qué rol participa cada persona. No da permisos
                en el sistema. Un rol que usan proyectos no se elimina: se desactiva desde "Editar".
            </p>
        </div>

    </div>

</div>
