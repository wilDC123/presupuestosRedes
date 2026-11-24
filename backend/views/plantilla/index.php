<?php

declare(strict_types=1);

use backend\assets\ComunAsset;
use yii\helpers\Html;

/**
 * Administracion de plantillas: tabla con buscador, estado y tipo de
 * proyecto, y una fila de detalle por plantilla con sus items agrupados.
 *
 * @var yii\web\View $this
 * @var array<int, array{plantilla: backend\models\Plantilla, totalItems: int, grupos: array<string, backend\models\PlantillaItem[]>, usadaEn: int}> $filas Ver PlantillaService::listar()
 * @var array<int, string> $tipos [idTipoProyecto => nombre], ver TipoProyectoService::opciones()
 */

$this->title = 'Plantillas de ítems';
$this->params['breadcrumbs'][] = 'Plantillas';

$totalActivas = count(array_filter($filas, static fn (array $fila) => $fila['plantilla']->activo));
$totalInactivas = count($filas) - $totalActivas;

$this->registerJsFile('@web/js/plantilla-index.js', ['depends' => [ComunAsset::class]]);
?>
<div class="plantilla-index">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                Listas de ítems que se suelen usar en un tipo de proyecto. Al cargar una plantilla en un
                proyecto sus ítems se copian: cambiarla después no altera los proyectos existentes.
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('+ Nueva plantilla', ['create'], ['class' => 'btn btn-primary']) ?>
        </div>
    </div>

    <?php if ($filas === []): ?>
        <div class="card rd-vacio">Todavía no hay plantillas. Un proyecto también puede armar su lista sin plantilla.</div>
    <?php else: ?>
        <div class="rd-filtros">
            <?= Html::textInput('plantillaBuscar', '', [
                'id' => 'plantilla-buscar',
                'class' => 'form-control',
                'placeholder' => 'Buscar por nombre de plantilla o de ítem...',
            ]) ?>
            <div class="rd-segmentado" id="plantilla-segmentado">
                <button type="button" class="rd-segmento" data-estado="1" aria-pressed="true">Activas (<?= $totalActivas ?>)</button>
                <button type="button" class="rd-segmento" data-estado="0" aria-pressed="false">Inactivas (<?= $totalInactivas ?>)</button>
                <button type="button" class="rd-segmento" data-estado="" aria-pressed="false">Todas (<?= count($filas) ?>)</button>
            </div>
            <?= Html::dropDownList('plantillaTipo', '', $tipos, [
                'id' => 'plantilla-tipo',
                'class' => 'form-select',
                'prompt' => 'Todos los tipos de proyecto',
            ]) ?>
        </div>

        <div class="rd-pagehead-sub mb-2" id="plantilla-contador">Mostrando <?= count($filas) ?> plantillas</div>

        <div class="card">
            <div class="table-responsive">
            <table class="table" id="plantilla-tabla">
                <thead>
                    <tr>
                        <th>Plantilla</th>
                        <th>Contenido por clasificación</th>
                        <th class="rd-num">Ítems</th>
                        <th>Usada en</th>
                        <th>Estado</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $fila): ?>
                        <?php
                        $plantilla = $fila['plantilla'];
                        $idDetalle = 'plantilla-detalle-' . $plantilla->id;

                        $nombresItems = [];
                        foreach ($fila['grupos'] as $items) {
                            foreach ($items as $item) {
                                $nombresItems[] = $item->subcatalogoItem->descripcion ?? '';
                            }
                        }
                        ?>
                        <tr class="rd-fila-plantilla"
                            data-nombre="<?= Html::encode(trim($plantilla->nombre . ' ' . implode(' ', $nombresItems))) ?>"
                            data-activo="<?= $plantilla->activo ? '1' : '0' ?>"
                            data-tipo="<?= Html::encode((string) ($plantilla->idTipoProyecto ?? '')) ?>">
                            <td>
                                <div class="rd-celda-titulo"><?= Html::encode($plantilla->nombre) ?></div>
                                <?php if ($plantilla->tipoProyecto !== null): ?>
                                    <div class="rd-celda-sub"><?= Html::encode($plantilla->tipoProyecto->nombre) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php foreach ($fila['grupos'] as $clasificacion => $items): ?>
                                    <span class="cat-chip"><?= Html::encode($clasificacion) ?> (<?= count($items) ?>)</span>
                                <?php endforeach; ?>
                            </td>
                            <td class="rd-num"><?= $fila['totalItems'] ?></td>
                            <td><?= $fila['usadaEn'] > 0 ? $fila['usadaEn'] . ' proyecto(s)' : '—' ?></td>
                            <td><?= $plantilla->activo
                                ? '<span class="badge b-activo">Activa</span>'
                                : '<span class="badge b-inactivo">Inactiva</span>' ?></td>
                            <td>
                                <?php if ($fila['totalItems'] > 0): ?>
                                    <button type="button" class="btn btn-outline btn-sm rd-ver-items"
                                            data-detalle="<?= $idDetalle ?>"
                                            data-total="<?= $fila['totalItems'] ?>"
                                            aria-expanded="false">
                                        Ver los <?= $fila['totalItems'] ?> ítems
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="rd-acciones">
                                <?= Html::a('Editar', ['update', 'id' => $plantilla->id], ['class' => 'btn btn-outline btn-sm']) ?>
                                <?= Html::a($plantilla->activo ? 'Desactivar' : 'Activar', ['cambiar-activo', 'id' => $plantilla->id], [
                                    'class' => 'btn btn-outline btn-sm',
                                    'data' => ['method' => 'post'],
                                ]) ?>
                                <?= Html::a('Eliminar', ['delete', 'id' => $plantilla->id], [
                                    'class' => 'btn btn-danger btn-sm',
                                    'data' => [
                                        'confirm' => '¿Eliminar la plantilla "' . $plantilla->nombre . '"?',
                                        'method' => 'post',
                                    ],
                                ]) ?>
                            </td>
                        </tr>
                        <?php if ($fila['totalItems'] > 0): ?>
                            <tr class="rd-fila-detalle d-none" id="<?= $idDetalle ?>">
                                <td colspan="7">
                                    <div class="rd-pila">
                                        <?php foreach ($fila['grupos'] as $clasificacion => $items): ?>
                                            <div class="rd-dato">
                                                <strong><?= Html::encode($clasificacion) ?>:</strong>
                                                <?= Html::encode(implode(', ', array_map(
                                                    static fn ($item) => ($item->subcatalogoItem->descripcion ?? '?')
                                                        . ($item->cantidadSugerida !== null ? ' (' . $item->cantidadSugerida . ')' : ''),
                                                    $items
                                                ))) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    <?php endif; ?>

</div>
