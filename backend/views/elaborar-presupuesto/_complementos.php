<?php

declare(strict_types=1);

use backend\models\PresupuestoComplemento;
use yii\helpers\Html;

/**
 * Una de las secciones SIN PRECIO que van al final del PDF del presupuesto
 * (ver PresupuestoComplemento): materiales que proveerá la DTIC o trabajos
 * complementarios de otras unidades. No suman al total.
 *
 * En borrador es un formulario con una fila por material/trabajo: "+
 * Agregar fila" y "Quitar" los maneja web/js/complementos-presupuesto.js, y
 * "Guardar" envia TODAS las filas (ElaborarPresupuestoService::guardarComplementos()
 * reemplaza las anteriores). Una fila en blanco se ignora. Fuera de borrador
 * solo se muestra, y si no tiene filas no sale.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * @var backend\models\Presupuesto $presupuesto
 * @var string $tipo PresupuestoComplemento::TIPO_MATERIAL o TIPO_TRABAJO
 * @var array{filas: array, errores: string[]}|null $enviado lo escrito si no se pudo guardar
 * @var array<int, string> $opcionesSubcatalogo [id => nombre DTIC] (solo materiales)
 * @var array<int, string> $unidadesSubcatalogo [id => abreviatura] (solo materiales)
 * @var array<string, string> $unidades [abreviatura => "Metro (m)"] (solo materiales)
 */

$borrador = $presupuesto->esBorrador();
$esMaterial = $tipo === PresupuestoComplemento::TIPO_MATERIAL;
$campos = $esMaterial
    ? ['grupo', 'idSubcatalogo', 'descripcion', 'cantidad', 'unidadMedida', 'observacion']
    : ['grupo', 'descripcion', 'unidadEncargada', 'observacion'];

// Las filas guardadas, o lo que se escribio si no se pudo guardar.
if ($enviado !== null) {
    $filas = $enviado['filas'];
} else {
    $filas = [];
    foreach ($presupuesto->complementos as $complemento) {
        if ($complemento->tipo === $tipo) {
            $filas[] = $complemento->getAttributes($campos);
        }
    }
}

if (!$borrador && $filas === []) {
    return;
}

$formato = Yii::$app->formatter;
$idSeccion = 'elaborar-complementos-' . $tipo;
$idSugerencias = $idSeccion . '-sugerencias';

// Subtitulos que se sugieren al escribir (materiales) o que se eligen (trabajos).
$sugerencias = $esMaterial
    ? array_unique(array_merge([PresupuestoComplemento::GRUPO_MATERIAL_SUGERIDO], array_filter(array_map(static fn ($f) => trim((string) ($f['grupo'] ?? '')), $filas))))
    : PresupuestoComplemento::UNIDADES_ENCARGADAS_SUGERIDAS;

/**
 * Celdas de una fila del formulario. $n es el indice del nombre
 * (filas[$n][campo]); en la plantilla de "+ Agregar fila" es "__n__".
 */
$celdas = function (string $n, array $fila) use ($esMaterial, $opcionesSubcatalogo, $unidadesSubcatalogo, $unidades, $idSugerencias): string {
    $valor = static fn (string $campo): string => (string) ($fila[$campo] ?? '');
    $nombre = static fn (string $campo): string => "filas[$n][$campo]";
    $html = '';

    if ($esMaterial) {
        $html .= Html::tag('td', Html::textInput($nombre('grupo'), $valor('grupo'), [
            'class' => 'form-control form-control-sm complemento-grupo',
            'maxlength' => 150,
            'list' => $idSugerencias,
            'placeholder' => PresupuestoComplemento::GRUPO_MATERIAL_SUGERIDO,
            'aria-label' => 'Subtítulo',
        ]));
        // data-unidad: la abreviatura que se copia al elegir el item.
        $opciones = [];
        foreach (array_keys($opcionesSubcatalogo) as $id) {
            $opciones[$id] = ['data-unidad' => $unidadesSubcatalogo[$id] ?? ''];
        }
        $html .= Html::tag('td', Html::dropDownList($nombre('idSubcatalogo'), $valor('idSubcatalogo'), $opcionesSubcatalogo, [
            'class' => 'form-select form-select-sm complemento-item',
            'prompt' => '— Escribir a mano —',
            'options' => $opciones,
            'aria-label' => 'Ítem del subcatálogo',
        ]));
        $html .= Html::tag('td', Html::textInput($nombre('descripcion'), $valor('descripcion'), [
            'class' => 'form-control form-control-sm complemento-descripcion',
            'maxlength' => 300,
            'aria-label' => 'Descripción',
        ]));
        $html .= Html::tag('td', Html::textInput($nombre('cantidad'), $valor('cantidad') === '' ? '' : (string) (float) $valor('cantidad'), [
            'class' => 'qty complemento-cantidad',
            'type' => 'number',
            'step' => 'any',
            'min' => '0.01',
            'aria-label' => 'Cantidad',
        ]));
        // Si la fila tiene una unidad que ya no esta activa, se conserva.
        $listaUnidades = $unidades;
        if ($valor('unidadMedida') !== '' && !isset($listaUnidades[$valor('unidadMedida')])) {
            $listaUnidades[$valor('unidadMedida')] = $valor('unidadMedida');
        }
        $html .= Html::tag('td', Html::dropDownList($nombre('unidadMedida'), $valor('unidadMedida'), $listaUnidades, [
            'class' => 'form-select form-select-sm complemento-unidad',
            'prompt' => '—',
            'aria-label' => 'Unidad',
        ]));
    } else {
        $html .= Html::tag('td', Html::dropDownList($nombre('grupo'), $valor('grupo'), array_combine(PresupuestoComplemento::GRUPOS_TRABAJO, PresupuestoComplemento::GRUPOS_TRABAJO), [
            'class' => 'form-select form-select-sm',
            'aria-label' => 'Subtítulo',
        ]));
        $html .= Html::tag('td', Html::textInput($nombre('descripcion'), $valor('descripcion'), [
            'class' => 'form-control form-control-sm complemento-descripcion',
            'maxlength' => 300,
            'aria-label' => 'Trabajo complementario',
        ]));
        $html .= Html::tag('td', Html::textInput($nombre('unidadEncargada'), $valor('unidadEncargada'), [
            'class' => 'form-control form-control-sm',
            'maxlength' => 150,
            'list' => $idSugerencias,
            'aria-label' => 'Unidad encargada',
        ]));
    }

    $html .= Html::tag('td', Html::textInput($nombre('observacion'), $valor('observacion'), [
        'class' => 'form-control form-control-sm',
        'maxlength' => 300,
        'aria-label' => 'Observaciones',
    ]));
    $html .= Html::tag('td', Html::button('Quitar', ['class' => 'btn btn-link btn-sm p-0 complemento-quitar']));

    return $html;
};
?>
<div class="card rd-card-pad mt-3 complementos" id="<?= $idSeccion ?>">
    <h3 class="h6 mb-1"><?= Html::encode(PresupuestoComplemento::TIPOS[$tipo]) ?></h3>
    <div class="rd-nota mb-2">
        <?= $esMaterial
            ? 'Lo que la DTIC ya tiene y aporta a la instalación. Sale en el PDF después del total, sin precio: no suma al total.'
            : 'Lo que debe hacer otra unidad de la universidad (instalación eléctrica, obras civiles). Sale en el PDF después del total, sin precio ni cantidad.' ?>
        Si no tiene filas, no sale en el PDF.
    </div>

    <?php if ($enviado !== null && $enviado['errores'] !== []): ?>
        <div class="alert alert-danger">
            No se guardó nada:
            <ul class="mb-0">
                <?php foreach ($enviado['errores'] as $error): ?>
                    <li><?= Html::encode($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($borrador): ?>
        <?= Html::beginForm(['complementos', 'idProyecto' => $proyecto->id, 'tipo' => $tipo], 'post', [
            'class' => 'complementos-form',
            'data-tipo' => $tipo,
        ]) ?>
            <datalist id="<?= $idSugerencias ?>">
                <?php foreach ($sugerencias as $sugerencia): ?>
                    <option value="<?= Html::encode($sugerencia) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <table class="table mb-2">
                <thead>
                    <tr>
                        <?php if ($esMaterial): ?>
                            <th>Subtítulo</th>
                            <th>Del subcatálogo (opcional)</th>
                            <th>Descripción</th>
                            <th class="rd-num">Cantidad</th>
                            <th>Unidad</th>
                        <?php else: ?>
                            <th>Subtítulo</th>
                            <th>Trabajo complementario</th>
                            <th>Unidad encargada</th>
                        <?php endif; ?>
                        <th>Observaciones</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="complementos-filas">
                    <?php // Sin filas, una vacia para empezar a escribir (se ignora si queda en blanco). ?>
                    <?php foreach ($filas === [] ? [[]] : $filas as $n => $fila): ?>
                        <tr class="complemento-fila"><?= $celdas((string) $n, (array) $fila) ?></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <template class="complemento-plantilla"><tr class="complemento-fila"><?= $celdas('__n__', []) ?></tr></template>
            <div class="d-flex gap-2">
                <?= Html::button('+ Agregar fila', ['class' => 'btn btn-outline complemento-agregar']) ?>
                <?= Html::submitButton('Guardar', ['class' => 'btn btn-outline']) ?>
            </div>
        <?= Html::endForm() ?>
    <?php else: ?>
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Subtítulo</th>
                    <?php if ($esMaterial): ?>
                        <th>Descripción</th>
                        <th class="rd-num">Cantidad</th>
                        <th>Unidad</th>
                    <?php else: ?>
                        <th>Trabajo complementario</th>
                        <th>Unidad encargada</th>
                    <?php endif; ?>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $fila): ?>
                    <tr>
                        <td><?= Html::encode((string) $fila['grupo']) ?></td>
                        <td><?= Html::encode((string) $fila['descripcion']) ?></td>
                        <?php if ($esMaterial): ?>
                            <td class="rd-num mono"><?= $formato->asDecimal($fila['cantidad'], 2) ?></td>
                            <td><?= Html::encode((string) $fila['unidadMedida']) ?></td>
                        <?php else: ?>
                            <td><?= Html::encode((string) $fila['unidadEncargada']) ?></td>
                        <?php endif; ?>
                        <td><?= Html::encode((string) $fila['observacion']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
