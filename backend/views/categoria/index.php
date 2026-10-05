<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Categorias: listado con el numero de items de cada una (izquierda) y
 * formulario rapido para crear una nueva (derecha).
 *
 * @var yii\web\View $this
 * @var array<int, array{categoria: backend\models\Categoria, items: int}> $filas Ver CategoriaService::listarConConteo()
 * @var backend\models\Categoria $nueva modelo vacio para el formulario rapido
 */

$this->title = 'Categorías de ítems';
$this->params['breadcrumbs'][] = 'Categorías';
?>
<div class="categoria-index">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="rd-cols">

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Categorías registradas</div>
                <div class="kicker"><?= count($filas) ?> categorías</div>
            </div>

            <?php if ($filas === []): ?>
                <div class="rd-vacio">Todavía no hay categorías.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th>Ítems del subcatálogo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $fila): ?>
                            <?php $categoria = $fila['categoria']; ?>
                            <tr>
                                <td>
                                    <div class="rd-celda-doble">
                                        <div class="cat-icon"><?= Html::encode((string) $categoria->orden) ?></div>
                                        <div class="rd-celda-titulo"><?= Html::encode($categoria->nombre) ?></div>
                                    </div>
                                </td>
                                <td class="mono"><?= $fila['items'] ?></td>
                                <td>
                                    <div class="rd-acciones">
                                        <?= Html::a('Ver ítems', ['/subcatalogo-item/index', 'idCategoria' => $categoria->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                        <?= Html::a('Editar', ['update', 'id' => $categoria->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                        <?= Html::a('Eliminar', ['delete', 'id' => $categoria->id], [
                                            'class' => 'btn btn-danger btn-sm',
                                            'data' => [
                                                'confirm' => '¿Eliminar la categoría "' . $categoria->nombre . '"?',
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
            <div class="rd-card-titulo mb-3">Nueva categoría</div>

            <?php // Envia a categoria/create, la misma accion del formulario completo. ?>
            <?php $form = ActiveForm::begin(['action' => ['create']]); ?>

            <?= $form->field($nueva, 'nombre')->textInput([
                'maxlength' => 100,
                'placeholder' => 'Ej. Equipos de energía (UPS)',
            ]) ?>

            <?= Html::submitButton('Guardar categoría', ['class' => 'btn btn-primary']) ?>

            <?php ActiveForm::end(); ?>

            <p class="rd-nota mt-3 mb-0">
                Cada ítem del subcatálogo pertenece a una sola categoría. Se usa para agrupar y
                filtrar el subcatálogo de redes. La categoría nueva se agrega al final; el orden
                se puede cambiar con "Editar".
            </p>
        </div>

    </div>

</div>
