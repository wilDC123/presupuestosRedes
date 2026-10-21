<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\web\JqueryAsset;

/**
 * Paso "Plantilla" del proyecto: elegir una plantilla (sus items se copian a
 * la lista del proyecto) u omitir y empezar en blanco.
 *
 * Es un solo formulario POST con dos botones; el campo "accion" dice cual se
 * pulso. Cada tarjeta es un <label> con un radio adentro, asi que funciona
 * aunque el JavaScript no cargue; web/js/cargar-plantilla.js solo resalta la
 * tarjeta elegida y cambia la vista previa.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $model
 * @var array $pasos barra de pasos del proyecto, ver ProyectoService::pasos()
 * @var array<int, array{plantilla: backend\models\Plantilla, totalItems: int, grupos: array<string, backend\models\PlantillaItem[]>}> $plantillas activas, las del tipo del proyecto primero
 * @var int $totalItems items que la lista del proyecto ya tiene
 */

$this->registerJsFile('@web/js/cargar-plantilla.js', ['depends' => [JqueryAsset::class]]);

$this->title = 'Cargar plantilla de ítems';
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->codigo ?: '#' . $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Plantilla';

$primera = $plantillas[0]['plantilla']->id ?? null;
?>
<div class="proyecto-plantilla">

    <?= Html::beginForm(['plantilla', 'id' => $model->id], 'post', ['id' => 'cargar-plantilla-form']) ?>

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
        <div class="rd-acciones">
            <?= Html::submitButton('Omitir, empezar en blanco', ['class' => 'btn btn-outline', 'name' => 'accion', 'value' => 'omitir']) ?>
            <?php if ($plantillas !== []): ?>
                <?= Html::submitButton('Usar plantilla seleccionada →', ['class' => 'btn btn-primary', 'name' => 'accion', 'value' => 'usar']) ?>
            <?php endif; ?>
        </div>
    </div>

    <?= $this->render('/proyecto/_pasos', ['pasos' => $pasos, 'actual' => 'Plantilla']) ?>

    <p class="rd-nota">
        Elija una plantilla con los ítems que suele usar en este tipo de proyecto. Sus ítems se copian a la
        lista del proyecto; después podrá mantener, eliminar o agregar ítems libremente antes de definir cantidades.
    </p>

    <?php if ($totalItems > 0): ?>
        <div class="notice mb-3">
            La lista de este proyecto ya tiene <?= $totalItems ?> ítems. Si usa una plantilla, solo se agregan
            los ítems que la lista todavía no tiene; no se borra ni se duplica nada.
        </div>
    <?php endif; ?>

    <?php if ($plantillas === []): ?>
        <div class="card rd-vacio">
            No hay plantillas activas. Puede empezar en blanco, o crear una en
            <?= Html::a('Plantillas', ['/plantilla/index']) ?>.
        </div>
    <?php else: ?>
        <div class="rd-tarjetas mb-3">
            <?php foreach ($plantillas as $fila): ?>
                <?php
                $plantilla = $fila['plantilla'];
                $nombresItems = [];

                foreach ($fila['grupos'] as $items) {
                    foreach ($items as $item) {
                        $nombresItems[] = $item->subcatalogoItem->descripcion ?? '?';
                    }
                }
                ?>
                <label class="tpl<?= $plantilla->id === $primera ? ' selected' : '' ?>">
                    <?= Html::radio('idPlantilla', $plantilla->id === $primera, ['value' => $plantilla->id, 'class' => 'tpl-radio']) ?>
                    <span class="rd-card-titulo"><?= Html::encode($plantilla->nombre) ?></span>
                    <span class="kicker"><?= $fila['totalItems'] ?> ítems</span>
                    <span class="rd-nota">
                        <?= Html::encode(implode(', ', array_slice($nombresItems, 0, 6)) . (count($nombresItems) > 6 ? '...' : '')) ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <?php foreach ($plantillas as $fila): ?>
            <?php $plantilla = $fila['plantilla']; ?>
            <div class="card tpl-vista<?= $plantilla->id === $primera ? '' : ' d-none' ?>" data-plantilla="<?= $plantilla->id ?>">
                <div class="rd-card-head">
                    <div class="rd-card-titulo">Vista previa: <?= Html::encode($plantilla->nombre) ?></div>
                    <div class="kicker"><?= $fila['totalItems'] ?> ítems en <?= count($fila['grupos']) ?> clasificaciones</div>
                </div>
                <?php if ($fila['totalItems'] === 0): ?>
                    <div class="rd-vacio">Esta plantilla todavía no tiene ítems.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ítem</th>
                                <th>Unidad</th>
                                <th class="rd-num">Cant. sugerida</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fila['grupos'] as $clasificacion => $items): ?>
                                <tr class="cat-row"><td colspan="3"><?= Html::encode($clasificacion) ?></td></tr>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <?= Html::encode($item->subcatalogoItem->descripcion ?? '?') ?>
                                            <?php if (!empty($item->observacion)): ?>
                                                <div class="rd-celda-sub"><?= Html::encode($item->observacion) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= Html::encode($item->subcatalogoItem?->abreviaturaUnidad() ?? '') ?></td>
                                        <td class="rd-num mono"><?= $item->cantidadSugerida === null ? '—' : Yii::$app->formatter->asDecimal($item->cantidadSugerida, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?= Html::endForm() ?>

</div>
