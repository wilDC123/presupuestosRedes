<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * HTML fuente del PDF de la lista de items (para imprimir). Se renderiza con
 * renderPartial() (sin el layout del sitio) y se le pasa a mPDF -- ver
 * ListaItemsController::actionImprimir().
 *
 * SIN columnas de precio: la lista no es el presupuesto.
 *
 * CSS deliberadamente simple, igual que el PDF del presupuesto: mPDF
 * interpreta un subconjunto de CSS pensado para documentos.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * @var array<int, array{nombre: string, items: backend\models\ProyectoItem[]}> $grupos Ver ListaItemsService::agrupar()
 */

$ubicacion = $proyecto->ubicacion();
$totalItems = array_sum(array_map(static fn ($grupo) => count($grupo['items']), $grupos));
$numero = 0;
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
        font-size: 12px;
        font-weight: bold;
        margin-top: 10px;
        border-top: 2px solid #000;
        padding-top: 6px;
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
    <div class="titulo">LISTA DE ÍTEMS DEL PROYECTO</div>
</div>

<table class="datos-generales">
    <tr>
        <td><strong>Código de proyecto:</strong> <?= Html::encode($proyecto->codigo ?: '—') ?> (Fase <?= (int) $proyecto->numeroFase ?>)</td>
        <td><strong>Estado de la lista:</strong> <?= Html::encode($proyecto->estadoListaLabel()) ?></td>
    </tr>
    <tr>
        <td><strong>Proyecto:</strong> <?= Html::encode($proyecto->nombre) ?></td>
        <td><strong>Facultad / Edificio / Bloques:</strong> <?= Html::encode($ubicacion !== '' ? $ubicacion : '—') ?></td>
    </tr>
    <tr>
        <td><strong>Fecha de impresión:</strong> <?= date('d/m/Y') ?></td>
        <td></td>
    </tr>
</table>

<?php if ($grupos === []): ?>
    <p>Esta lista todavía no tiene ítems.</p>
<?php else: ?>
    <?php foreach ($grupos as $grupo): ?>
        <div class="categoria-titulo"><?= Html::encode($grupo['nombre']) ?></div>
        <table class="items">
            <thead>
                <tr>
                    <th width="6%">N.°</th>
                    <th>Descripción</th>
                    <th width="14%">Unidad</th>
                    <th width="14%">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grupo['items'] as $item): ?>
                    <tr>
                        <td class="numero"><?= ++$numero ?></td>
                        <td>
                            <?= Html::encode($item->subcatalogoItem->descripcion ?? '') ?>
                            <?php if (!empty($item->observacion)): ?>
                                <br><span class="observacion"><?= Html::encode($item->observacion) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= Html::encode((string) $item->unidadMedida) ?></td>
                        <td class="numero"><?= Yii::$app->formatter->asDecimal($item->cantidad, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="subtotal">
                    <td colspan="4">Subtotal <?= Html::encode($grupo['nombre']) ?>: <?= count($grupo['items']) ?> ítem(s)</td>
                </tr>
            </tfoot>
        </table>
    <?php endforeach; ?>

    <div class="total-general">
        TOTAL: <?= $totalItems ?> ÍTEM(S) EN <?= count($grupos) ?> CLASIFICACIÓN(ES)
    </div>
<?php endif; ?>

<div class="firmas">
    <div class="firma-linea"></div>
    <div class="firma-etiqueta">Elaborado por: ________________________________</div>
    <div class="firma-etiqueta">Fecha: ________________________________</div>
</div>

</body>
</html>
