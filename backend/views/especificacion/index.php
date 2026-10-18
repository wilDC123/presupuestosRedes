<?php

declare(strict_types=1);

use backend\services\EspecificacionService;
use yii\helpers\Html;

/**
 * Libreria de especificaciones tecnicas: las fichas se cargan una vez, se
 * clasifican por categoria y despues se enlazan a los items de la lista de
 * cada proyecto. Los filtros viajan por GET (?categoria=, ?buscar=, ?activo=).
 *
 * @var yii\web\View $this
 * @var array<int, array{especificacion: backend\models\Especificacion, usos: int}> $filas Ver EspecificacionService::listar()
 * @var array{categoria: string, buscar: string, activo: string} $filtros
 * @var array<int, string> $categorias Ver CategoriaService::opciones()
 */

$this->title = 'Librería de especificaciones técnicas';
$this->params['breadcrumbs'][] = 'Librería de especificaciones';

$hayFiltros = $filtros['categoria'] !== '' || $filtros['buscar'] !== '' || $filtros['activo'] !== '';
?>
<div class="especificacion-index">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                Fichas reutilizables entre ítems y proyectos. Se enlazan desde la lista de ítems de cada proyecto; un ítem
                sin especificación es normal.
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('Configuración del formulario NET', ['configuracion'], ['class' => 'btn btn-outline']) ?>
            <?= Html::a('+ Nueva especificación', ['create'], ['class' => 'btn btn-primary']) ?>
        </div>
    </div>

    <?= Html::beginForm(['index'], 'get', ['class' => 'rd-acciones mb-3']) ?>
        <?= Html::dropDownList('categoria', $filtros['categoria'], $categorias + [EspecificacionService::SIN_CATEGORIA => '— Sin categoría —'], [
            'class' => 'form-control',
            'prompt' => 'Todas las categorías',
            'aria-label' => 'Categoría',
        ]) ?>
        <?= Html::dropDownList('activo', $filtros['activo'], ['1' => 'Solo activas', '0' => 'Solo inactivas'], [
            'class' => 'form-control',
            'prompt' => 'Activas e inactivas',
            'aria-label' => 'Estado',
        ]) ?>
        <?= Html::textInput('buscar', $filtros['buscar'], [
            'class' => 'form-control rd-buscar',
            'placeholder' => 'Buscar por título, modelo o característica (ej. LSZH)...',
        ]) ?>
        <?= Html::submitButton('Filtrar', ['class' => 'btn btn-outline']) ?>
        <?php if ($hayFiltros): ?>
            <?= Html::a('Quitar filtros', ['index'], ['class' => 'btn btn-outline']) ?>
        <?php endif; ?>
    <?= Html::endForm() ?>

    <div class="card">
        <div class="rd-card-head">
            <div class="rd-card-titulo">Especificaciones</div>
            <div class="kicker"><?= count($filas) ?> en la librería<?= $hayFiltros ? ' (filtradas)' : '' ?></div>
        </div>

        <?php if ($filas === []): ?>
            <div class="rd-vacio">
                <?= $hayFiltros
                    ? 'Ninguna especificación coincide con los filtros.'
                    : 'La librería todavía está vacía. ' . Html::a('Agregue la primera especificación', ['create']) . '.' ?>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Especificación</th>
                        <th>Categoría</th>
                        <th>Versión</th>
                        <th>Documento</th>
                        <th>Usos</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $fila): ?>
                        <?php
                        $especificacion = $fila['especificacion'];
                        $versionActual = $especificacion->versionActual;
                        $urlDocumento = $versionActual?->urlAdjunto();
                        ?>
                        <tr>
                            <td>
                                <div class="rd-celda-titulo"><?= Html::a(Html::encode($especificacion->nombre()), ['view', 'id' => $especificacion->id]) ?></div>
                                <?php if (!empty($versionActual?->modeloSugerido)): ?>
                                    <div class="rd-celda-sub">Modelo sugerido: <?= Html::encode($versionActual->modeloSugerido) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= Html::encode($especificacion->categoria->nombre ?? 'Sin categoría') ?></td>
                            <td class="mono"><?= $versionActual !== null ? Html::encode($versionActual->etiqueta()) : '—' ?></td>
                            <td>
                                <?= $urlDocumento === null
                                    ? '—'
                                    : Html::a('Ver documento', $urlDocumento, ['target' => '_blank', 'rel' => 'noopener']) ?>
                            </td>
                            <td class="mono"><?= $fila['usos'] ?></td>
                            <td>
                                <?= $especificacion->activo
                                    ? '<span class="badge b-activo">Activa</span>'
                                    : '<span class="badge b-inactivo">Inactiva</span>' ?>
                            </td>
                            <td><?= Html::a('Editar', ['update', 'id' => $especificacion->id], ['class' => 'btn btn-outline']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
