<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * HTML fuente del documento de una solicitud de cotizacion. Se renderiza con
 * renderPartial() (sin el layout del sitio) y se le pasa a mPDF -- ver
 * SolicitudCotizacionController::actionImprimir().
 *
 * Las columnas de precio van EN BLANCO: las llena el proveedor.
 *
 * CSS deliberadamente simple, igual que los otros PDF: mPDF interpreta un
 * subconjunto de CSS pensado para documentos.
 *
 * @var yii\web\View $this
 * @var backend\models\SolicitudCotizacion $solicitud
 * @var array<int, string> $especificaciones [idProyectoItem => codigo NET] Ver SolicitudCotizacionService::especificacionesPorItem()
 * @var backend\models\ConfiguracionSolicitudCotizacion $configuracion textos fijos editables por el encargado
 */

$proyecto = $solicitud->proyecto;
$ubicacion = $proyecto->ubicacion();
$cabecera = $configuracion->lineasCabecera();
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
        margin-bottom: 10px;
    }
    table.datos-generales td {
        padding: 3px 4px;
        vertical-align: top;
        width: 50%;
    }
    .texto {
        margin-bottom: 10px;
        line-height: 1.5;
    }
    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4px;
    }
    table.items th,
    table.items td {
        border: 1px solid #666;
        padding: 5px 4px;
        font-size: 10px;
    }
    table.items th {
        background-color: #e0e0e0;
        text-align: left;
    }
    table.items td.numero {
        text-align: right;
    }
    .observacion {
        font-style: italic;
        font-size: 9px;
        color: #333;
    }
    tr.total td {
        font-weight: bold;
        text-align: right;
        background-color: #f2f2f2;
    }
    table.proveedor {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
    }
    table.proveedor td {
        padding: 7px 4px;
        width: 50%;
    }
    .nota-final {
        margin-top: 12px;
        font-weight: bold;
    }
    .firmas {
        margin-top: 50px;
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
    <?php foreach ($cabecera as $i => $linea): ?>
        <div class="<?= $i === 0 ? 'institucion' : 'unidad' ?>"><?= Html::encode($linea) ?></div>
    <?php endforeach; ?>
    <div class="titulo">SOLICITUD DE COTIZACIÓN</div>
</div>

<table class="datos-generales">
    <tr>
        <td><strong>Solicitud N.°:</strong> <?= Html::encode($solicitud->codigo()) ?></td>
        <td><strong>Fecha:</strong> <?= date('d/m/Y') ?></td>
    </tr>
    <tr>
        <td><strong>Rubro:</strong> <?= Html::encode($solicitud->nombre) ?></td>
        <td><strong>Proyecto:</strong> <?= Html::encode($proyecto->nombre) ?></td>
    </tr>
    <tr>
        <td colspan="2"><strong>Lugar de la instalación:</strong> <?= Html::encode($ubicacion !== '' ? $ubicacion : '—') ?></td>
    </tr>
</table>

<div class="texto">
    <?= nl2br(Html::encode($configuracion->textoInstrucciones)) ?>
    <?php if (!empty($solicitud->nota)): ?>
        <br><strong>Nota:</strong> <?= nl2br(Html::encode($solicitud->nota)) ?>
    <?php endif; ?>
</div>

<?php if ($solicitud->items === []): ?>
    <p>Esta solicitud todavía no tiene ítems.</p>
<?php else: ?>
    <table class="items">
        <thead>
            <tr>
                <th width="5%">N.°</th>
                <th>Descripción</th>
                <th width="10%">Unidad</th>
                <th width="10%">Cantidad</th>
                <th width="17%">Marca / modelo ofertado</th>
                <th width="12%">Precio unitario</th>
                <th width="12%">Precio total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($solicitud->items as $fila): ?>
                <?php
                $item = $fila->proyectoItem;
                $especificacion = $especificaciones[(int) $fila->idProyectoItem] ?? null;
                ?>
                <tr>
                    <td class="numero"><?= ++$numero ?></td>
                    <td>
                        <?= Html::encode($item->subcatalogoItem->descripcion ?? '') ?>
                        <?php if (!empty($fila->marcaModeloReferencia)): ?>
                            (<?= Html::encode($fila->marcaModeloReferencia) ?>)
                        <?php endif; ?>
                        <?php if (!empty($item->observacion)): ?>
                            <br><span class="observacion"><?= Html::encode($item->observacion) ?></span>
                        <?php endif; ?>
                        <?php if ($especificacion !== null): ?>
                            <br><span class="observacion">
                                Según especificación técnica <?= Html::encode($especificacion) ?><?= $solicitud->proyecto->codigo ? ' — proyecto ' . Html::encode($solicitud->proyecto->codigo) : '' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= Html::encode((string) ($item->unidadMedida ?? '')) ?></td>
                    <td class="numero"><?= Yii::$app->formatter->asDecimal($item->cantidad ?? 0, 2) ?></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="6">TOTAL Bs.:</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

<table class="proveedor">
    <tr>
        <td><strong>Proveedor:</strong> ______________________________</td>
        <td><strong>NIT:</strong> ______________________________</td>
    </tr>
    <tr>
        <td><strong>Validez de la oferta:</strong> __________ días</td>
        <td><strong>Tiempo de entrega:</strong> ______________________________</td>
    </tr>
</table>

<?php if (!empty($configuracion->textoNotaFinal)): ?>
    <div class="texto nota-final"><?= nl2br(Html::encode($configuracion->textoNotaFinal)) ?></div>
<?php endif; ?>

<div class="firmas">
    <div class="firma-linea"></div>
    <div class="firma-etiqueta">Firma y sello del proveedor</div>
    <div class="firma-etiqueta">Fecha: ________________________________</div>
</div>

</body>
</html>
