<?php

declare(strict_types=1);

use backend\models\ProyectoMaterialDtic;
use yii\helpers\Html;

/**
 * "Otros materiales y equipamiento que proveerá la D.T.I.C." del proyecto:
 * lo que la DTIC ya tiene y aporta a la instalacion. Sin precio: no se
 * cotiza ni va al presupuesto. Hasta el 2026-10-29 estaba debajo del
 * presupuesto; desde la revision del Ing. de DTIC (paso 8) va con la lista,
 * porque el presupuesto es solo financiero. Sale en el impreso solo si tiene
 * filas.
 *
 * En pantalla (2026-10-30) se llama "Materiales que aporta la DTIC" (el
 * titulo oficial va en el ⓘ y en el impreso) y se ve AGRUPADA por subtitulo,
 * como la lista de arriba por clasificacion. Dos clases de fila:
 *   - del subcatalogo: se elige con el buscador de abajo; nombre y unidad
 *     fijos (campos ocultos), solo se escribe la cantidad. El subtitulo es la
 *     categoria del item.
 *   - a mano ("¿No está? Escribirlo a mano"): descripcion, categoria
 *     (subtitulo; "Otros" por defecto) y unidad editables.
 * Buscador, filas y grupos los maneja web/js/lista-items.js (.lista-seccion).
 *
 * Mientras la lista se edita, esta tabla esta DENTRO del formulario de la
 * lista y se guarda con el mismo boton (materiales[n][...],
 * MaterialDticService).
 *
 * @var yii\web\View $this
 * @var array<int, array<string, string>> $materiales
 * @var string[] $categorias nombres de las categorias (subtitulos para lo escrito a mano)
 * @var array<string, string> $unidades [abreviatura => "Metro (m)"]
 * @var bool $editable
 */

if (!$editable && $materiales === []) {
    return;
}

$formato = Yii::$app->formatter;

// Filas agrupadas por subtitulo, en el orden en que aparece cada uno: [subtitulo => [n => fila]].
$grupos = [];
foreach ($materiales as $n => $fila) {
    $grupos[trim((string) ($fila['grupo'] ?? '')) ?: ProyectoMaterialDtic::GRUPO_OTROS][$n] = $fila;
}

$grupoAbre = static fn (string $nombre, int $columnas): string =>
    '<tbody class="seccion-grupo" data-grupo="' . Html::encode($nombre) . '">'
    . '<tr class="cat-row"><td colspan="' . $columnas . '">' . Html::encode($nombre) . '</td></tr>';

$cantidad = static fn (string $n, string $valor): string => Html::textInput("materiales[$n][cantidad]", $valor === '' || !is_numeric($valor) ? $valor : (string) (float) $valor, [
    'class' => 'qty',
    'type' => 'number',
    'step' => 'any',
    'min' => '0',
    'aria-label' => 'Cantidad',
]);
$quitar = '<td class="rd-num"><button type="button" class="rd-quitar seccion-quitar" aria-label="Quitar material">×</button></td>';

// Fila de un item del subcatalogo: nombre y unidad fijos.
$filaItem = static function (string $n, array $fila) use ($cantidad, $quitar): string {
    $valor = static fn (string $campo): string => (string) ($fila[$campo] ?? '');

    return '<tr class="seccion-fila" data-item="' . Html::encode($valor('idSubcatalogo')) . '">'
        . '<td>'
        . Html::hiddenInput("materiales[$n][idSubcatalogo]", $valor('idSubcatalogo'), ['class' => 'material-id'])
        . Html::hiddenInput("materiales[$n][grupo]", $valor('grupo'), ['class' => 'material-grupo'])
        . Html::hiddenInput("materiales[$n][descripcion]", $valor('descripcion'), ['class' => 'material-descripcion'])
        . '<span class="material-nombre">' . Html::encode($valor('descripcion')) . '</span>'
        . '</td>'
        . '<td>'
        . Html::hiddenInput("materiales[$n][unidadMedida]", $valor('unidadMedida'), ['class' => 'material-unidad'])
        . '<span class="material-unidad-texto">' . Html::encode($valor('unidadMedida')) . '</span>'
        . '</td>'
        . '<td class="rd-num">' . $cantidad($n, $valor('cantidad')) . '</td>'
        . $quitar
        . '</tr>';
};

// Fila escrita a mano: descripcion, subtitulo (categoria) y unidad editables.
$filaManual = static function (string $n, array $fila) use ($cantidad, $quitar, $categorias, $unidades): string {
    $valor = static fn (string $campo): string => (string) ($fila[$campo] ?? '');
    $grupo = $valor('grupo') ?: ProyectoMaterialDtic::GRUPO_OTROS;
    // El subtitulo y la unidad que ya tenia la fila se conservan aunque no esten en las listas.
    $listaGrupos = array_combine($categorias, $categorias) + [ProyectoMaterialDtic::GRUPO_OTROS => ProyectoMaterialDtic::GRUPO_OTROS, $grupo => $grupo];
    $listaUnidades = $unidades;
    if ($valor('unidadMedida') !== '' && !isset($listaUnidades[$valor('unidadMedida')])) {
        $listaUnidades[$valor('unidadMedida')] = $valor('unidadMedida');
    }

    return '<tr class="seccion-fila">'
        . '<td>'
        . Html::hiddenInput("materiales[$n][idSubcatalogo]", '')
        . Html::textInput("materiales[$n][descripcion]", $valor('descripcion'), [
            'class' => 'form-control form-control-sm material-descripcion',
            'maxlength' => 300,
            'placeholder' => 'Descripción del material',
            'aria-label' => 'Descripción',
        ])
        . '<div class="d-flex align-items-center gap-2 mt-1"><span class="rd-celda-sub text-nowrap">Escrito a mano · Categoría:</span>'
        . Html::dropDownList("materiales[$n][grupo]", $grupo, $listaGrupos, [
            'class' => 'form-select form-select-sm seccion-grupo-select',
            'aria-label' => 'Categoría',
        ])
        . '</div>'
        . '</td>'
        . '<td>' . Html::dropDownList("materiales[$n][unidadMedida]", $valor('unidadMedida'), $listaUnidades, [
            'class' => 'form-select form-select-sm',
            'prompt' => '—',
            'aria-label' => 'Unidad',
        ]) . '</td>'
        . '<td class="rd-num">' . $cantidad($n, $valor('cantidad')) . '</td>'
        . $quitar
        . '</tr>';
};
?>
<div class="card mt-3 lista-seccion" id="lista-materiales-dtic" data-siguiente="<?= count($materiales) ?>">
    <div class="rd-card-head">
        <div class="rd-card-titulo">
            <?= Html::encode(ProyectoMaterialDtic::TITULO_PANTALLA) ?>
            (<span class="seccion-total"><?= count($materiales) ?></span>)
            <span class="rd-info" tabindex="0" title="<?= Html::encode('En el impreso: «' . ProyectoMaterialDtic::TITULO . '». Lo que la DTIC ya tiene y aporta a la instalación. Sin precio: no se cotiza ni va al presupuesto. Si no tiene filas, no sale en el impreso.') ?>">ⓘ</span>
        </div>
        <?php if ($editable): ?>
            <?= Html::button('+ Agregar material', ['class' => 'btn btn-outline btn-sm material-agregar']) ?>
        <?php endif; ?>
    </div>

    <?php if ($editable): ?>
        <?php // Sin filas, "materiales" igual llega (vacio) y se borran los guardados. Va ANTES de las filas:
        // PHP se queda con el ultimo campo del mismo nombre, y si fuera despues reemplazaria a materiales[n]. ?>
        <?= Html::hiddenInput('materiales', '') ?>
    <?php endif; ?>

    <table class="table mb-0">
        <thead>
            <tr>
                <th>Descripción</th>
                <th style="width: 16%;">Unidad</th>
                <th class="rd-num" style="width: 12%;">Cantidad</th>
                <?php if ($editable): ?><th style="width: 40px;"></th><?php endif; ?>
            </tr>
        </thead>
        <?php foreach ($grupos as $nombre => $filas): ?>
            <?= $grupoAbre((string) $nombre, $editable ? 4 : 3) ?>
            <?php foreach ($filas as $n => $fila): ?>
                <?php if ($editable): ?>
                    <?= ($fila['idSubcatalogo'] ?? '') !== '' ? $filaItem((string) $n, $fila) : $filaManual((string) $n, $fila) ?>
                <?php else: ?>
                    <tr>
                        <td><?= Html::encode($fila['descripcion']) ?></td>
                        <td><?= Html::encode($fila['unidadMedida']) ?></td>
                        <td class="rd-num mono"><?= $formato->asDecimal($fila['cantidad'], 2) ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        <?php endforeach; ?>
    </table>

    <?php if ($editable): ?>
        <div class="rd-vacio seccion-vacia<?= $materiales === [] ? '' : ' d-none' ?>">
            Todavía no hay. Búsquelo en el subcatálogo aquí abajo; si la DTIC no aporta materiales, deje la sección vacía.
        </div>
        <div class="rd-card-pad border-top d-flex align-items-center gap-3 flex-wrap">
            <div class="rd-combo flex-grow-1" style="max-width: 520px;">
                <input type="text" class="form-control material-buscar" autocomplete="off"
                       placeholder="Buscar en el subcatálogo y agregar…" aria-label="Buscar material en el subcatálogo">
                <ul class="rd-resultados-ambiente d-none material-resultados"></ul>
            </div>
            <a href="#" class="material-a-mano">¿No está? Escribirlo a mano</a>
        </div>
        <?php // Modelos que el JS copia ("__n__" = indice nuevo); el navegador no los muestra ni los envia. ?>
        <template class="material-modelo-item"><?= $filaItem('__n__', []) ?></template>
        <template class="material-modelo-manual"><?= $filaManual('__n__', []) ?></template>
    <?php endif; ?>
</div>
