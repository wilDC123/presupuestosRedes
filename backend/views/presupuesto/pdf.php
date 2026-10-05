<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * HTML fuente del PDF consolidado del presupuesto. Se renderiza vía
 * renderPartial() (sin el layout del sitio) y el HTML resultante se le pasa
 * a mPDF -- ver PresupuestoController::actionPdf() y
 * backend\components\PresupuestoPdfGenerator.
 *
 * CSS deliberadamente simple: mPDF interpreta un subconjunto de CSS pensado
 * para documentos, no un layout de aplicación web.
 *
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $presupuesto
 * @var array $grupos Ver PresupuestoService::agruparPorCategoria()
 * @var float $totalGeneral
 * @var array<int, array{partida: string, lineas: int, subtotal: float}> $resumenPartidas Ver PresupuestoService::resumenPorPartida()
 */

$proyecto = $presupuesto->proyecto;

$ubicacion = $proyecto->ubicacion();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body {
        font-family: sans-serif;
        font-size: 11px;
        color: #000;
    }
    .encabezado {
        text-align: center;
        margin-bottom: 14px;
    }
    .encabezado .institucion {
        font-size: 11px;
        font-weight: bold;
    }
    .encabezado .unidad {
        font-size: 10px;
        margin-bottom: 6px;
    }
    .encabezado .titulo {
        font-size: 14px;
        font-weight: bold;
        text-decoration: underline;
        margin-top: 6px;
    }
    table.datos-generales {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }
    table.datos-generales td {
        padding: 3px 4px;
        vertical-align: top;
        width: 50%;
    }
    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4px;
    }
    table.items th,
    table.items td {
        border: 1px solid #666;
        padding: 4px;
        font-size: 10px;
    }
    table.items th {
        background-color: #e0e0e0;
        text-align: left;
    }
    table.items td.numero {
        text-align: right;
    }
    .categoria-titulo {
        background-color: #d0d0d0;
        font-weight: bold;
        padding: 4px;
        margin-top: 10px;
    }
    .observacion {
        font-style: italic;
        font-size: 9px;
        color: #333;
    }
    tr.subtotal td {
        font-weight: bold;
        text-align: right;
        background-color: #f2f2f2;
    }
    .total-general {
        text-align: right;
        font-size: 13px;
        font-weight: bold;
        margin-top: 10px;
        border-top: 2px solid #000;
        padding-top: 6px;
    }
    table.resumen-partidas {
        width: 60%;
    }
    .firmas {
        margin-top: 60px;
    }
    .firma-linea {
        margin-top: 40px;
        border-top: 1px solid #000;
        width: 260px;
    }
    .firma-etiqueta {
        margin-top: 4px;
    }
</style>
</head>
<body>

<div class="encabezado">
    <div class="institucion">Universidad Mayor, Real y Pontificia de San Francisco Xavier de Chuquisaca</div>
    <div class="unidad">D.T.I.C. - Unidad de Redes y Telecomunicaciones</div>
    <div class="titulo">PRESUPUESTO DE INSTALACIÓN DE REDES</div>
</div>

<table class="datos-generales">
    <tr>
        <td><strong>N.° de presupuesto:</strong> <?= Html::encode($presupuesto->numeroPresupuesto ?? '—') ?></td>
        <td><strong>Código de proyecto:</strong> <?= Html::encode($proyecto->codigo ?: '—') ?> (Fase <?= (int) $proyecto->numeroFase ?>)</td>
    </tr>
    <tr>
        <td><strong>Oficio DTIC:</strong> <?= Html::encode($presupuesto->oficioDtic ?? '—') ?></td>
        <td><strong>Área de intervención:</strong> <?= Html::encode($presupuesto->areaIntervencion ?? '—') ?></td>
    </tr>
    <tr>
        <td><strong>Proyecto:</strong> <?= Html::encode($proyecto->nombre ?? '—') ?></td>
        <td><strong>Facultad / Edificio / Bloques:</strong> <?= Html::encode($ubicacion !== '' ? $ubicacion : '—') ?></td>
    </tr>
    <tr>
        <td><strong>Fecha:</strong> <?= Html::encode($presupuesto->fechaFormateada() ?: '—') ?></td>
        <td><strong>Estado:</strong> <?= Html::encode($presupuesto->estadoLabel()) ?></td>
    </tr>
</table>

<?php if (empty($grupos)): ?>
    <p>Este presupuesto todavía no tiene líneas cargadas.</p>
<?php else: ?>
    <?php foreach ($grupos as $nombreCategoria => $grupo): ?>
        <div class="categoria-titulo"><?= Html::encode($nombreCategoria) ?></div>
        <table class="items">
            <thead>
                <tr>
                    <th>Ítem</th>
                    <th>Partida</th>
                    <th>Unidad</th>
                    <th>Cantidad</th>
                    <th>Precio unitario</th>
                    <th>Precio total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grupo['lineas'] as $linea): ?>
                    <tr>
                        <td>
                            <?= Html::encode($linea->subcatalogoItem->descripcion ?? '') ?>
                            <?php if (!empty($linea->observacion)): ?>
                                <br><span class="observacion"><?= Html::encode($linea->observacion) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= Html::encode($linea->partida ?? '') ?></td>
                        <td><?= Html::encode($linea->unidadMedida ?? '') ?></td>
                        <td class="numero"><?= Yii::$app->formatter->asDecimal($linea->cantidad, 2) ?></td>
                        <td class="numero"><?= Yii::$app->formatter->asDecimal($linea->precioUnitario, 2) ?></td>
                        <td class="numero"><?= Yii::$app->formatter->asDecimal($linea->precioTotal, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="subtotal">
                    <td colspan="5">Subtotal <?= Html::encode($nombreCategoria) ?>:</td>
                    <td class="numero"><?= Yii::$app->formatter->asDecimal($grupo['subtotal'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endforeach; ?>

    <div class="total-general">
        TOTAL GENERAL: <?= Yii::$app->formatter->asDecimal($totalGeneral, 2) ?>
    </div>

    <div class="categoria-titulo">Resumen por partida presupuestaria</div>
    <table class="items resumen-partidas">
        <thead>
            <tr>
                <th>Partida</th>
                <th>N.° de líneas</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resumenPartidas as $fila): ?>
                <tr>
                    <td><?= Html::encode($fila['partida']) ?></td>
                    <td class="numero"><?= $fila['lineas'] ?></td>
                    <td class="numero"><?= Yii::$app->formatter->asDecimal($fila['subtotal'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="subtotal">
                <td colspan="2">Total:</td>
                <td class="numero"><?= Yii::$app->formatter->asDecimal(array_sum(array_column($resumenPartidas, 'subtotal')), 2) ?></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

<div class="firmas">
    <div class="firma-linea"></div>
    <div class="firma-etiqueta">Elaborado por: ________________________________</div>
    <div class="firma-etiqueta">Fecha: ________________________________</div>
</div>

</body>
</html>
