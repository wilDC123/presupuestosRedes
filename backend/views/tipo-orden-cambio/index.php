<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Tipos de orden de cambio: listado con en cuantas ordenes se usa cada
 * uno (izquierda) y formulario rapido para agregar uno (derecha).
 *
 * @var yii\web\View $this
 * @var array<int, array{tipo: backend\models\TipoOrdenCambio, ordenes: int}> $filas Ver TipoOrdenCambioService::listarConConteo()
 * @var backend\models\TipoOrdenCambio $nuevo modelo vacio para el formulario rapido
 */

$this->title = 'Tipos de orden de cambio';
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tipo-orden-cambio-index">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="rd-cols">

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Tipos registrados</div>
                <div class="kicker"><?= count($filas) ?> tipos</div>
            </div>

            <?php if ($filas === []): ?>
                <div class="rd-vacio">Todavía no hay tipos.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Órdenes</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $fila): ?>
                            <?php $tipo = $fila['tipo']; ?>
                            <tr>
                                <td class="rd-celda-titulo"><?= Html::encode($tipo->nombre) ?></td>
                                <td class="mono"><?= $fila['ordenes'] ?></td>
                                <td>
                                    <?= $tipo->activo
                                        ? '<span class="badge b-activo">Activo</span>'
                                        : '<span class="badge b-inactivo">Inactivo</span>' ?>
                                </td>
                                <td>
                                    <div class="rd-acciones">
                                        <?= Html::a('Editar', ['update', 'id' => $tipo->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                        <?= Html::a('Eliminar', ['delete', 'id' => $tipo->id], [
                                            'class' => 'btn btn-danger btn-sm',
                                            'data' => [
                                                'confirm' => '¿Eliminar el tipo "' . $tipo->nombre . '"?',
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
            <div class="rd-card-titulo mb-3">Nuevo tipo</div>

            <?php // Envia a tipo-orden-cambio/create, la misma accion del formulario completo. ?>
            <?php $form = ActiveForm::begin(['action' => ['create']]); ?>

            <?= $form->field($nuevo, 'nombre')->textInput(['maxlength' => 150, 'placeholder' => 'Ej. Cambios en el alcance de los proyectos']) ?>

            <?= Html::submitButton('Guardar tipo', ['class' => 'btn btn-primary']) ?>

            <?php ActiveForm::end(); ?>

            <p class="rd-nota mt-3 mb-0">
                Es el desplegable "Tipo de cambio" de una orden de cambio. Se cargó con la lista 7.1.1 del formato
                RD-GD-FR-006 ("Tipos de órdenes de cambio a considerar"). Un tipo que usan órdenes de cambio no se
                elimina: se desactiva desde "Editar".
            </p>
        </div>

    </div>

</div>
