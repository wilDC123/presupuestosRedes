<?php

declare(strict_types=1);

use backend\models\ProyectoCondicionPrevia;
use yii\helpers\Html;

/**
 * "Trabajos complementarios" (condiciones y requerimientos previos) del
 * proyecto: lo que deben hacer OTRAS unidades para que la DTIC pueda instalar
 * (instalacion electrica, obras civiles, apertura de ductos...). Sin precio:
 * esa unidad hace su propio presupuesto. Hasta el 2026-10-29 estaba debajo del
 * presupuesto; desde la revision del Ing. de DTIC (paso 8) va con la lista,
 * porque el presupuesto es solo financiero. Sale en el impreso solo si tiene
 * filas.
 *
 * En pantalla (2026-10-30) va con titulo corto (el oficial en el ⓘ y en el
 * impreso) y AGRUPADA por tipo, como en el impreso; cambiar el tipo de una
 * fila la pasa a su grupo. "+ Agregar trabajo", "×" y los grupos los maneja
 * web/js/lista-items.js (.lista-seccion).
 *
 * Mientras la lista se edita, esta tabla esta DENTRO del formulario de la
 * lista y se guarda con el mismo boton (condiciones[n][...],
 * CondicionPreviaService). Una fila sin descripcion ni unidad encargada se
 * ignora.
 *
 * @var yii\web\View $this
 * @var array<int, array{idTipo: string, descripcion: string, unidadEncargada: string}> $condiciones
 * @var array<int, string> $tipos [id => nombre]
 * @var string[] $sugerencias unidades encargadas para el <datalist>
 * @var bool $editable
 */

if (!$editable && $condiciones === []) {
    return;
}

$tipoPorDefecto = (string) array_key_first($tipos);

// Filas agrupadas por tipo, en el orden en que aparece cada uno: [idTipo => [n => fila]].
$grupos = [];
foreach ($condiciones as $n => $fila) {
    $grupos[(string) ($fila['idTipo'] ?? '') ?: $tipoPorDefecto][$n] = $fila;
}

$grupoAbre = static fn (string $idTipo, string $nombre, int $columnas): string =>
    '<tbody class="seccion-grupo" data-grupo="' . Html::encode($idTipo) . '">'
    . '<tr class="cat-row"><td colspan="' . $columnas . '">' . Html::encode($nombre) . '</td></tr>';

$filaEditable = static function (string $n, array $fila) use ($tipos, $tipoPorDefecto): string {
    $valor = static fn (string $campo): string => (string) ($fila[$campo] ?? '');

    return '<tr class="seccion-fila">'
        . '<td>' . Html::textInput("condiciones[$n][descripcion]", $valor('descripcion'), [
            'class' => 'form-control form-control-sm',
            'maxlength' => 300,
            'placeholder' => 'Ej. Tomacorriente estabilizado junto al rack',
            'aria-label' => 'Trabajo complementario',
        ]) . '</td>'
        . '<td>' . Html::dropDownList("condiciones[$n][idTipo]", $valor('idTipo') ?: $tipoPorDefecto, $tipos, [
            'class' => 'form-select form-select-sm seccion-grupo-select',
            'aria-label' => 'Tipo',
        ]) . '</td>'
        . '<td>' . Html::textInput("condiciones[$n][unidadEncargada]", $valor('unidadEncargada'), [
            'class' => 'form-control form-control-sm',
            'maxlength' => 150,
            'list' => 'condiciones-unidades',
            'aria-label' => 'Unidad encargada',
        ]) . '</td>'
        . '<td class="rd-num"><button type="button" class="rd-quitar seccion-quitar" aria-label="Quitar trabajo">×</button></td>'
        . '</tr>';
};
?>
<div class="card mt-3 lista-seccion" id="lista-condiciones" data-siguiente="<?= count($condiciones) ?>">
    <div class="rd-card-head">
        <div class="rd-card-titulo">
            <?= Html::encode(ProyectoCondicionPrevia::TITULO_PANTALLA) ?>
            (<span class="seccion-total"><?= count($condiciones) ?></span>)
            <span class="rd-info" tabindex="0" title="<?= Html::encode('En el impreso: «' . ProyectoCondicionPrevia::TITULO . '». Condiciones y requerimientos previos: lo que deben hacer otras unidades para que se pueda instalar. Sin precio: no va al presupuesto. Si no tiene filas, no sale en el impreso. Los tipos se agregan en Configuración → Tipos de trabajo complementario.') ?>">ⓘ</span>
        </div>
        <?php if ($editable && $tipos !== []): ?>
            <?= Html::button('+ Agregar trabajo', ['class' => 'btn btn-outline btn-sm seccion-agregar']) ?>
        <?php endif; ?>
    </div>

    <?php if ($editable): ?>
        <?php // Sin filas, "condiciones" igual llega (vacio) y se borran las guardadas. Va ANTES de las filas:
        // PHP se queda con el ultimo campo del mismo nombre, y si fuera despues reemplazaria a condiciones[n]. ?>
        <?= Html::hiddenInput('condiciones', '') ?>
    <?php endif; ?>

    <table class="table mb-0">
        <thead>
            <tr>
                <th>Trabajo complementario</th>
                <th class="rd-ancho-22">Tipo</th>
                <th class="rd-ancho-25">Unidad encargada</th>
                <?php if ($editable): ?><th class="rd-ancho-boton"></th><?php endif; ?>
            </tr>
        </thead>
        <?php foreach ($grupos as $idTipo => $filas): ?>
            <?= $grupoAbre((string) $idTipo, $tipos[(int) $idTipo] ?? '', $editable ? 4 : 3) ?>
            <?php foreach ($filas as $n => $fila): ?>
                <?php if ($editable): ?>
                    <?= $filaEditable((string) $n, $fila) ?>
                <?php else: ?>
                    <tr>
                        <td><?= Html::encode($fila['descripcion']) ?></td>
                        <td><?= Html::encode($tipos[(int) $fila['idTipo']] ?? '') ?></td>
                        <td><?= Html::encode($fila['unidadEncargada']) ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        <?php endforeach; ?>
    </table>

    <?php if ($editable): ?>
        <div class="rd-vacio seccion-vacia<?= $condiciones === [] ? '' : ' d-none' ?>">
            <?= $tipos === []
                ? 'No hay tipos de trabajo activos. Agréguelos en ' . Html::a('Tipos de trabajo complementario', ['/tipo-condicion-previa/index']) . '.'
                : 'Todavía no hay. Ej.: instalación eléctrica, apertura de ductos. Agréguelos con «+ Agregar trabajo».' ?>
        </div>
        <datalist id="condiciones-unidades">
            <?php foreach ($sugerencias as $sugerencia): ?>
                <option value="<?= Html::encode($sugerencia) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <template class="seccion-modelo"><?= $filaEditable('__n__', []) ?></template>
    <?php endif; ?>
</div>
