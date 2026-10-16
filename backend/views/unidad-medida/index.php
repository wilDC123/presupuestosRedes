<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Unidades de medida: listado con el numero de items del subcatalogo que
 * usa cada una (izquierda) y formulario rapido para crear una nueva (derecha).
 *
 * @var yii\web\View $this
 * @var array<int, array{unidad: backend\models\UnidadMedida, items: int}> $filas Ver UnidadMedidaService::listarConConteo()
 * @var backend\models\UnidadMedida $nueva modelo vacio para el formulario rapido
 */

$this->title = 'Unidades de medida';
$this->params['breadcrumbs'][] = 'Unidades de medida';
?>
<div class="unidad-medida-index">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="rd-cols">

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Unidades registradas</div>
                <div class="kicker"><?= count($filas) ?> unidades</div>
            </div>

            <?php if ($filas === []): ?>
                <div class="rd-vacio">Todavía no hay unidades de medida.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Unidad</th>
                            <th>Abreviatura</th>
                            <th>Ítems del subcatálogo</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $fila): ?>
                            <?php $unidad = $fila['unidad']; ?>
                            <tr>
                                <td class="rd-celda-titulo"><?= Html::encode($unidad->nombre) ?></td>
                                <td class="mono"><?= Html::encode($unidad->abreviatura) ?></td>
                                <td class="mono"><?= $fila['items'] ?></td>
                                <td>
                                    <?= $unidad->activo
                                        ? '<span class="badge b-activo">Activa</span>'
                                        : '<span class="badge b-inactivo">Inactiva</span>' ?>
                                </td>
                                <td>
                                    <div class="rd-acciones">
                                        <?= Html::a('Editar', ['update', 'id' => $unidad->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                        <?= Html::a('Eliminar', ['delete', 'id' => $unidad->id], [
                                            'class' => 'btn btn-danger btn-sm',
                                            'data' => [
                                                'confirm' => '¿Eliminar la unidad "' . $unidad->nombre . '"?',
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
            <div class="rd-card-titulo mb-3">Nueva unidad</div>

            <?php // Envia a unidad-medida/create, la misma accion del formulario completo. ?>
            <?php $form = ActiveForm::begin(['action' => ['create']]); ?>

            <?= $form->field($nueva, 'nombre')->textInput(['maxlength' => 50, 'placeholder' => 'Ej. Metro']) ?>

            <?= $form->field($nueva, 'abreviatura')->textInput(['maxlength' => 10, 'placeholder' => 'Ej. m']) ?>

            <?= Html::submitButton('Guardar unidad', ['class' => 'btn btn-primary']) ?>

            <?php ActiveForm::end(); ?>

            <p class="rd-nota mt-3 mb-0">
                La abreviatura es la que se muestra en la lista del proyecto, el presupuesto y el PDF.
                Una unidad en uso no se elimina: se desactiva desde "Editar".
            </p>
        </div>

    </div>

</div>
