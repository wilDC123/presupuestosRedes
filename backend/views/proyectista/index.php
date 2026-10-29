<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Proyectistas: listado con cuantos proyectos tiene asignados cada uno
 * (izquierda) y formulario rapido para agregar uno (derecha).
 *
 * @var yii\web\View $this
 * @var array<int, array{proyectista: backend\models\Proyectista, proyectos: int}> $filas Ver ProyectistaService::listarConConteo()
 * @var backend\models\Proyectista $nuevo modelo vacio para el formulario rapido
 */

$this->title = 'Proyectistas';
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = 'Proyectistas';
?>
<div class="proyectista-index">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="rd-cols">

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Proyectistas registrados</div>
                <div class="kicker"><?= count($filas) ?> proyectistas</div>
            </div>

            <?php if ($filas === []): ?>
                <div class="rd-vacio">Todavía no hay proyectistas.</div>
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
                            <?php $proyectista = $fila['proyectista']; ?>
                            <tr>
                                <td class="rd-celda-titulo"><?= Html::encode($proyectista->nombre) ?></td>
                                <td class="mono"><?= $fila['proyectos'] ?></td>
                                <td>
                                    <?= $proyectista->activo
                                        ? '<span class="badge b-activo">Activo</span>'
                                        : '<span class="badge b-inactivo">Inactivo</span>' ?>
                                </td>
                                <td>
                                    <div class="rd-acciones">
                                        <?= Html::a('Editar', ['update', 'id' => $proyectista->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                        <?= Html::a('Eliminar', ['delete', 'id' => $proyectista->id], [
                                            'class' => 'btn btn-danger btn-sm',
                                            'data' => [
                                                'confirm' => '¿Eliminar a "' . $proyectista->nombre . '"?',
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
            <div class="rd-card-titulo mb-3">Nuevo proyectista</div>

            <?php // Envia a proyectista/create, la misma accion del formulario completo. ?>
            <?php $form = ActiveForm::begin(['action' => ['create']]); ?>

            <?= $form->field($nuevo, 'nombre')->textInput(['maxlength' => 200, 'placeholder' => 'Ej. Ing. Paola Rojas']) ?>

            <?= Html::submitButton('Guardar proyectista', ['class' => 'btn btn-primary']) ?>

            <?php ActiveForm::end(); ?>

            <p class="rd-nota mt-3 mb-0">
                Es el desplegable "Proyectistas asignados" del proyecto. Solo es una lista de nombres:
                no son usuarios del sistema. Uno asignado a proyectos no se elimina: se desactiva desde "Editar".
            </p>
        </div>

    </div>

</div>
