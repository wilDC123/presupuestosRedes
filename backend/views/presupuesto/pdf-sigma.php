<?php

declare(strict_types=1);

use backend\models\CatalogoSigma;
use yii\helpers\Html;

/**
 * HTML fuente del reporte de apoyo "Relación con el catálogo SIGMA": cada
 * línea del presupuesto junto al ítem de dbo.CatalogoSigma al que apunta su
 * ítem del subcatálogo, para saber qué ítem del catálogo es cada uno. Se
 * renderiza con renderPartial() desde PresupuestoController::actionPdfSigma()
 * y sale en A4 horizontal.
 *
 * Compara lo guardado en la línea (partida y precio, CONGELADOS al crearla)
 * con lo que dice HOY el catálogo (IdGasto y PrecioReferencial): una partida
 * distinta se marca, porque puede ser un cambio del catálogo o un error. No
 * es el documento oficial: no lleva firmas ni la mano de obra.
 *
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $presupuesto
 * @var array $grupos Ver PresupuestoService::agruparPorCategoria()
 */

$proyecto = $presupuesto->proyecto;
$formato = Yii::$app->formatter;
$numero = 0;
$conSigma = 0;
$partidasDistintas = 0;

foreach ($grupos as $grupo) {
    foreach ($grupo['lineas'] as $linea) {
        $sigma = $linea->subcatalogoItem->catalogoSigma ?? null;
        if ($sigma !== null) {
            $conSigma++;
            if (trim((string) $sigma->IdGasto) !== trim((string) $linea->partida)) {
                $partidasDistintas++;
            }
        }
    }
}

$totalLineas = array_sum(array_map(static fn (array $grupo) => count($grupo['lineas']), $grupos));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body {
        font-family: sans-serif;
        font-size: 10px;
        color: #000;
    }
    .encabezado {
        text-align: center;
        margin-bottom: 10px;
    }
    .encabezado .institucion {
        font-size: 11px;
        font-weight: bold;
    }
    .encabezado .unidad {
        font-size: 10px;
    }
    .encabezado .titulo {
        font-size: 13px;
        font-weight: bold;
        text-decoration: underline;
        margin-top: 6px;
    }
    table.datos-generales {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    table.datos-generales td {
        padding: 2px 4px;
        vertical-align: top;
    }
    table.items {
        width: 100%;
        border-collapse: collapse;
    }
    table.items th,
    table.items td {
        border: 1px solid #666;
        padding: 3px;
        font-size: 8.5px;
        vertical-align: top;
    }
    table.items th {
        background-color: #e0e0e0;
        text-align: left;
    }
    table.items th.grupo {
        text-align: center;
    }
    table.items td.numero {
        text-align: right;
    }
    tr.categoria td {
        background-color: #d0d0d0;
        font-weight: bold;
    }
    .sub {
        font-size: 7.5px;
        color: #444;
    }
    .id-sigma {
        font-family: monospace;
        font-size: 7px;
        color: #444;
    }
    .marca {
        font-weight: bold;
        background-color: #fde2c4;
    }
    .sin-sigma {
        font-style: italic;
        color: #666;
    }
    .resumen {
        margin-top: 8px;
        font-size: 9px;
    }
    .leyenda {
        margin-top: 4px;
        font-size: 8px;
        color: #333;
    }
</style>
</head>
<body>

<div class="encabezado">
    <div class="institucion">Universidad Mayor, Real y Pontificia de San Francisco Xavier de Chuquisaca</div>
    <div class="unidad">D.T.I.C. - Unidad de Redes y Telecomunicaciones</div>
    <div class="titulo">RELACIÓN DEL PRESUPUESTO CON EL CATÁLOGO SIGMA</div>
</div>

<table class="datos-generales">
    <tr>
        <td><strong>N.° de presupuesto:</strong> <?= Html::encode($presupuesto->numeroPresupuesto ?: '—') ?></td>
        <td><strong>Código de proyecto:</strong> <?= Html::encode($proyecto->codigo ?: '—') ?> (Fase <?= (int) $proyecto->numeroFase ?>)</td>
        <td><strong>Proyecto:</strong> <?= Html::encode($proyecto->nombre ?? '—') ?></td>
        <td><strong>Emitido:</strong> <?= date('d/m/Y') ?></td>
    </tr>
</table>

<?php if ($totalLineas === 0): ?>
    <p>Este presupuesto todavía no tiene líneas cargadas.</p>
<?php else: ?>
    <table class="items">
        <thead>
            <tr>
                <th rowspan="2" width="3%">N.°</th>
                <th rowspan="2" width="16%">Ítem DTIC</th>
                <th colspan="2" class="grupo">Ítem del catálogo SIGMA</th>
                <th colspan="2" class="grupo">Partida</th>
                <th rowspan="2" width="5%">Unidad</th>
                <th rowspan="2" width="6%">Cantidad</th>
                <th colspan="2" class="grupo">Precio unitario</th>
                <th rowspan="2" width="6%">Estado en SIGMA</th>
            </tr>
            <tr>
                <th width="28%">Clase / descripción / Id SIGMA</th>
                <th width="7%">Especificación</th>
                <th width="6%">Presupuesto</th>
                <th width="6%">SIGMA hoy</th>
                <th width="7%">Presupuesto</th>
                <th width="7%">Referencial SIGMA</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupos as $nombreCategoria => $grupo): ?>
                <tr class="categoria"><td colspan="11"><?= Html::encode($nombreCategoria) ?></td></tr>
                <?php foreach ($grupo['lineas'] as $linea): ?>
                    <?php
                    $item = $linea->subcatalogoItem;
                    $sigma = $item->catalogoSigma ?? null;
                    $partidaLinea = trim((string) $linea->partida);
                    $partidaSigma = $sigma !== null ? trim((string) $sigma->IdGasto) : '';
                    $precioReferencial = $sigma !== null ? (float) $sigma->PrecioReferencial : 0.0;
                    ?>
                    <tr>
                        <td class="numero"><?= ++$numero ?></td>
                        <td>
                            <?= Html::encode($item->descripcion ?? '') ?>
                            <?php if (!empty($linea->observacion)): ?>
                                <br><span class="sub"><?= Html::encode($linea->observacion) ?></span>
                            <?php endif; ?>
                        </td>
                        <?php if ($sigma === null): ?>
                            <td colspan="2" class="sin-sigma">
                                Sin ítem SIGMA<?= !empty($item->idSigma) ? ' (el Id ' . Html::encode($item->idSigma) . ' ya no está en el catálogo)' : '' ?>
                            </td>
                        <?php else: ?>
                            <td>
                                <span class="sub"><?= Html::encode(trim((string) $sigma->Clase)) ?></span>
                                <br><?= Html::encode(trim((string) $sigma->Descripcion)) ?>
                                <br><span class="id-sigma"><?= Html::encode(strtoupper((string) $sigma->IdSigma)) ?></span>
                            </td>
                            <td><?= Html::encode(trim((string) $sigma->Especificacion) ?: '—') ?></td>
                        <?php endif; ?>
                        <td><?= Html::encode($partidaLinea ?: '—') ?></td>
                        <td class="<?= $sigma !== null && $partidaSigma !== $partidaLinea ? 'marca' : '' ?>">
                            <?= $sigma === null ? '—' : Html::encode($partidaSigma ?: '—') . ($partidaSigma !== $partidaLinea ? ' *' : '') ?>
                        </td>
                        <td><?= Html::encode($linea->unidadMedida ?? '') ?></td>
                        <td class="numero"><?= $formato->asDecimal($linea->cantidad, 2) ?></td>
                        <td class="numero"><?= $formato->asDecimal($linea->precioUnitario, 2) ?></td>
                        <td class="numero"><?= $precioReferencial > 0 ? $formato->asDecimal($precioReferencial, 2) : '—' ?></td>
                        <td>
                            <?php if ($sigma === null): ?>
                                —
                            <?php elseif ($sigma->CodigoEstado === CatalogoSigma::ESTADO_VIGENTE): ?>
                                Vigente
                            <?php else: ?>
                                <span class="marca">No vigente</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="resumen">
        <strong><?= $totalLineas ?></strong> línea(s): <strong><?= $conSigma ?></strong> con ítem SIGMA,
        <strong><?= $totalLineas - $conSigma ?></strong> sin ítem SIGMA,
        <strong><?= $partidasDistintas ?></strong> con partida distinta a la del catálogo.
    </div>
    <div class="leyenda">
        "Presupuesto": partida y precio guardados en la línea al elegir el precio (no cambian).
        "SIGMA hoy" y "Referencial SIGMA": lo que dice hoy el catálogo SIGMA.
        * La partida del catálogo es distinta a la del presupuesto: revisar si el catálogo cambió o si el ítem
        del subcatálogo tiene otra partida. Varios ítems DTIC pueden apuntar al mismo ítem SIGMA.
    </div>
<?php endif; ?>

</body>
</html>
