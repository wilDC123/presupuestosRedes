<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\web\JqueryAsset;
use yii\widgets\ActiveForm;

/**
 * Formulario de plantilla (crear y editar): datos, clasificaciones incluidas
 * e items. Todo se envia junto en un solo POST:
 *   Plantilla[...]    datos de la plantilla
 *   categorias[]      clasificaciones marcadas
 *   items[n][...]     una fila por item
 *
 * web/js/plantilla-form.js agrega y quita filas de items y filtra el
 * subcatalogo. La fila "modelo" oculta (<template>) es la que el JS copia
 * para cada item nuevo.
 *
 * @var yii\web\View $this
 * @var backend\models\Plantilla $model
 * @var backend\models\PlantillaItem[] $items items a mostrar (guardados, o los enviados si hubo error)
 * @var int[] $idsCategoria clasificaciones marcadas
 * @var array<int, string> $categorias [id => nombre]
 * @var array<string, string> $tipos [codigo => nombre]
 * @var backend\models\SubcatalogoItem[] $subcatalogo items activos del subcatalogo
 */

$this->registerJsFile('@web/js/plantilla-form.js', ['depends' => [JqueryAsset::class]]);

// Nombre DTIC de cada item, para pintar las filas ya existentes.
$nombres = [];

foreach ($subcatalogo as $itemSubcatalogo) {
    $nombres[$itemSubcatalogo->id] = $itemSubcatalogo->descripcion;
}

/**
 * Dibuja una fila de item. $n es su indice dentro de items[n][...]; el JS
 * usa "__n__" como marcador y lo reemplaza por un numero nuevo.
 */
$fila = static function (string $n, string $idSubcatalogo, string $nombre, string $idCategoria, string $cantidad, string $observacion) use ($categorias): string {
    return '<tr class="plantilla-item">'
        . '<td>' . Html::hiddenInput("items[$n][idSubcatalogo]", $idSubcatalogo)
        . '<span class="plantilla-item-nombre">' . Html::encode($nombre) . '</span></td>'
        . '<td>' . Html::dropDownList("items[$n][idCategoria]", $idCategoria, $categorias, ['class' => 'form-select plantilla-item-categoria']) . '</td>'
        . '<td>' . Html::textInput("items[$n][cantidadSugerida]", $cantidad, ['class' => 'qty', 'type' => 'number', 'step' => '0.01', 'min' => '0']) . '</td>'
        . '<td>' . Html::textInput("items[$n][observacion]", $observacion, ['class' => 'form-control', 'maxlength' => 300, 'placeholder' => 'Opcional: de 1 metro, según especificación...']) . '</td>'
        . '<td><button type="button" class="rd-quitar" aria-label="Quitar ítem">×</button></td>'
        . '</tr>';
};
?>
<?php $form = ActiveForm::begin(['id' => 'plantilla-form']); ?>

<?php if ($model->hasErrors('categorias') || $model->hasErrors('items')): ?>
    <div class="alert alert-danger">
        <?php foreach (array_merge($model->getErrors('categorias'), $model->getErrors('items')) as $error): ?>
            <div><?= Html::encode($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card rd-card-pad mb-3">
    <div class="rd-fila-2">
        <?= $form->field($model, 'nombre')->textInput(['maxlength' => 200, 'placeholder' => 'Ej. Videovigilancia — básico']) ?>

        <?= $form->field($model, 'tipoProyecto')->dropDownList($tipos, ['prompt' => '(cualquier tipo de proyecto)', 'class' => 'form-select'])
            ->hint('Al cargar plantilla en un proyecto de este tipo, aparece primero.') ?>
    </div>

    <?= $form->field($model, 'descripcion')->textInput(['maxlength' => 500, 'placeholder' => 'Para qué tipo de trabajo sirve esta plantilla']) ?>

    <div class="form-group">
        <label>Clasificaciones incluidas</label>
        <div class="rd-casillas" id="plantilla-categorias">
            <?php foreach ($categorias as $id => $nombre): ?>
                <label class="rd-casilla">
                    <?= Html::checkbox('categorias[]', in_array($id, $idsCategoria, true), ['value' => $id]) ?>
                    <?= Html::encode($nombre) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="hint">Cada ítem de la plantilla va en una de las clasificaciones marcadas. Al agregar un ítem se marca sola la suya.</div>
    </div>

    <?= $form->field($model, 'activo')->checkbox()
        ->hint('Una plantilla inactiva no se ofrece al cargar plantilla en proyectos nuevos.') ?>
</div>

<div class="rd-cols">

    <div class="card rd-col-1">
        <div class="rd-card-pad">
            <div class="rd-card-titulo mb-2">Subcatálogo de redes</div>
            <input type="text" class="form-control" id="plantilla-buscar" placeholder="Buscar ítem...">
            <div class="kicker mt-2"><?= count($subcatalogo) ?> ítems activos en el subcatálogo</div>
        </div>
        <div class="rd-lista-scroll" id="plantilla-subcatalogo">
            <?php if ($subcatalogo === []): ?>
                <div class="rd-vacio">No hay ítems activos en el subcatálogo.</div>
            <?php endif; ?>
            <?php foreach ($subcatalogo as $itemSubcatalogo): ?>
                <div class="row-item"
                     data-id="<?= $itemSubcatalogo->id ?>"
                     data-nombre="<?= Html::encode($itemSubcatalogo->descripcion) ?>"
                     data-categoria="<?= $itemSubcatalogo->idCategoria ?>">
                    <div>
                        <div class="rd-celda-titulo"><?= Html::encode($itemSubcatalogo->descripcion) ?></div>
                        <div class="rd-celda-sub">
                            <?= Html::encode($categorias[$itemSubcatalogo->idCategoria] ?? '') ?>
                            <?= $itemSubcatalogo->unidadDefecto ? ' · Unidad: ' . Html::encode($itemSubcatalogo->unidadDefecto) : '' ?>
                        </div>
                    </div>
                    <button type="button" class="addbtn" aria-label="Agregar a la plantilla">+</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card rd-col-2">
        <div class="rd-card-head">
            <div class="rd-card-titulo">Ítems de la plantilla</div>
            <div class="kicker"><span id="plantilla-total"><?= count($items) ?></span> ítems</div>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Ítem</th>
                    <th>Clasificación</th>
                    <th>Cant. sugerida</th>
                    <th>Observación</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="plantilla-items" data-siguiente="<?= count($items) ?>">
                <?php foreach (array_values($items) as $n => $item): ?>
                    <?= $fila(
                        (string) $n,
                        (string) $item->idSubcatalogo,
                        $nombres[(int) $item->idSubcatalogo] ?? ($item->subcatalogoItem->descripcion ?? 'Ítem no disponible'),
                        (string) $item->idCategoria,
                        (string) $item->cantidadSugerida,
                        (string) $item->observacion
                    ) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="rd-vacio<?= $items === [] ? '' : ' d-none' ?>" id="plantilla-vacia">
            Agregue ítems con el botón + del subcatálogo.
        </div>
    </div>

</div>

<?php // Fila modelo: el navegador no la muestra ni la envia; el JS la copia para cada item nuevo. ?>
<template id="plantilla-fila-modelo"><?= $fila('__n__', '', '', '', '', '') ?></template>

<?php ActiveForm::end(); ?>
