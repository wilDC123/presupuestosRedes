<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Procesos: listado con cuantos proyectos usan cada uno (izquierda) y
 * formulario rapido para crear uno nuevo (derecha).
 *
 * @var yii\web\View $this
 * @var array<int, array{proceso: backend\models\Proceso, proyectos: int}> $filas Ver ProcesoService::listarConConteo()
 * @var backend\models\Proceso $nuevo modelo vacio para el formulario rapido
 */

$this->title = 'Procesos';
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = 'Procesos';
?>
<div class="proceso-index">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">Tabla de codificación de procesos del lineamiento RD-GD-LI-001 (8.5).</div>
        </div>
    </div>

    <div class="rd-cols">

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Procesos registrados</div>
                <div class="kicker"><?= count($filas) ?> procesos</div>
            </div>

            <?php if ($filas === []): ?>
                <div class="rd-vacio">Todavía no hay procesos.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Proceso</th>
                            <th>Proyectos</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $fila): ?>
                            <?php $proceso = $fila['proceso']; ?>
                            <tr>
                                <td class="mono"><strong><?= Html::encode($proceso->codigo) ?></strong></td>
                                <td class="rd-celda-titulo"><?= Html::encode($proceso->nombre) ?></td>
                                <td class="mono"><?= $fila['proyectos'] ?></td>
                                <td>
                                    <?= $proceso->activo
                                        ? '<span class="badge b-activo">Activo</span>'
                                        : '<span class="badge b-inactivo">Inactivo</span>' ?>
                                </td>
                                <td>
                                    <div class="rd-acciones">
                                        <?= Html::a('Editar', ['update', 'id' => $proceso->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                        <?= Html::a('Eliminar', ['delete', 'id' => $proceso->id], [
                                            'class' => 'btn btn-danger btn-sm',
                                            'data' => [
                                                'confirm' => '¿Eliminar el proceso "' . $proceso->etiqueta() . '"?',
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
            <div class="rd-card-titulo mb-3">Nuevo proceso</div>

            <?php // Envia a proceso/create, la misma accion del formulario completo. ?>
            <?php $form = ActiveForm::begin(['action' => ['create']]); ?>

            <?= $form->field($nuevo, 'codigo')->textInput(['maxlength' => 2, 'placeholder' => 'Ej. PI', 'style' => 'text-transform: uppercase;']) ?>
            <?= $form->field($nuevo, 'nombre')->textInput(['maxlength' => 200, 'placeholder' => 'Ej. Despliegue y soporte redes']) ?>

            <?= Html::submitButton('Guardar proceso', ['class' => 'btn btn-primary']) ?>

            <?php ActiveForm::end(); ?>

            <p class="rd-nota mt-3 mb-0">
                Es el desplegable "Proceso" del proyecto: dice qué proceso lo lleva adelante.
                Un proceso en uso no se elimina (se desactiva desde "Editar") y su código ya no se cambia.
            </p>
        </div>

    </div>

</div>
