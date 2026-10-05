<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Administracion de plantillas: una tarjeta por plantilla.
 *
 * @var yii\web\View $this
 * @var array<int, array{plantilla: backend\models\Plantilla, totalItems: int, grupos: array<string, backend\models\PlantillaItem[]>}> $filas Ver PlantillaService::listar()
 * @var array<string, string> $tipos [codigo => nombre] de tipos de proyecto
 */

$this->title = 'Plantillas de ítems';
$this->params['breadcrumbs'][] = 'Plantillas';
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
        <div class="rd-tarjetas">
            <?php foreach ($filas as $fila): ?>
                <?php $plantilla = $fila['plantilla']; ?>
                <div class="card rd-card-pad">
                    <div class="rd-card-head-plano">
                        <div class="rd-card-titulo"><?= Html::encode($plantilla->nombre) ?></div>
                        <?= $plantilla->activo
                            ? '<span class="badge b-activo">Activa</span>'
                            : '<span class="badge b-inactivo">Inactiva</span>' ?>
                    </div>

                    <div class="kicker mb-2">
                        <?= $fila['totalItems'] ?> ítems · <?= count($fila['grupos']) ?> clasificaciones
                        <?= isset($tipos[$plantilla->tipoProyecto]) ? ' · ' . Html::encode($tipos[$plantilla->tipoProyecto]) : '' ?>
                    </div>

                    <?php if (!empty($plantilla->descripcion)): ?>
                        <p class="rd-nota"><?= Html::encode($plantilla->descripcion) ?></p>
                    <?php endif; ?>

                    <?php foreach ($fila['grupos'] as $clasificacion => $items): ?>
                        <div class="rd-dato">
                            <strong><?= Html::encode($clasificacion) ?>:</strong>
                            <?= Html::encode(implode(', ', array_map(
                                static fn ($item) => $item->subcatalogoItem->descripcion ?? '?',
                                $items
                            ))) ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="rd-acciones mt-3">
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
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
