<?php

declare(strict_types=1);

use backend\components\Monto;
use yii\helpers\Html;

/**
 * HTML fuente de la "SOLICITUD DE PROFORMA DE COTIZACIÓN". Se renderiza con
 * renderPartial() (sin el layout del sitio) y se le pasa a mPDF -- ver
 * SolicitudCotizacionController::actionImprimir().
 *
 * Desde el 2026-10-30 (revision del Ing. de DTIC del 2026-10-29, paso 10) es
 * una lista SENCILLA para el proveedor: N.°, Descripcion, Unidad y Cantidad
 * de lo que se necesita. Sin columnas de precio ni marca ofertada, sin
 * NIT, validez ni tiempo de entrega: eso lo trae la proforma que devuelve el
 * proveedor. Desde el 2026-11-09, como el Word de los formatos corregidos v3,
 * lleva tambien la firma "ELABORADO POR" de la DTIC (antes ninguna), en la
 * tabla de todos los documentos a todo el ancho (views/pdf/_control-elaboracion.php):
 * la firma 1 de "Configuración del PDF" del presupuesto, la de HOY (la
 * solicitud no guarda copia, como sus textos). Desde el 2026-11-10 esa firma va
 * al FONDO de la ultima pagina (MARCA_AL_PIE, como la lista y el presupuesto)
 * y ya no lleva el espacio del proveedor (firma y sello, nombre / empresa,
 * fecha), que tenia desde el 2026-10-30. Ese mismo dia lleva la cabecera
 * de todos los documentos (views/pdf/_cabecera.php) con su codigo
 * RD-<proceso>-SC-NNN y version 1.0, como en los formatos corregidos v3;
 * antes, una cabecera propia con "N.° 001 / SC-01" y la unidad.
 *
 * El rubro, el nombre del proyecto, la unidad y el area de intervencion son
 * datos internos de la DTIC y no salen en el documento. La especificacion tecnica
 * SI: cada item cita el codigo ET de su emision vigente, y los formularios
 * van al final del mismo PDF si se imprime "con especificaciones"
 * (SolicitudCotizacionService::generarPdf()).
 *
 * CSS deliberadamente simple, igual que los otros PDF: mPDF interpreta un
 * subconjunto de CSS pensado para documentos.
 *
 * @var yii\web\View $this
 * @var backend\models\SolicitudCotizacion $solicitud
 * @var backend\models\ConfiguracionSolicitudCotizacion $configuracion textos fijos editables por el encargado
 */

$numero = 0;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* Carlito: las medidas de Calibri, la letra del lineamiento RD-GD-LI-001. */
    body {
        font-family: carlito;
        font-size: 11pt;
        color: #000;
    }
    /* Cabecera repetida en cada pagina: ver views/pdf/_cabecera.php. */
    @page {
        header: html_cabecera;
        margin-header: 5mm;
        margin-top: 37mm;
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
        padding: 4px 4px;
        font-size: 10pt;
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
        font-size: 9pt;
        color: #333;
    }
    .nota-final {
        margin-top: 12px;
        font-weight: bold;
    }
</style>
</head>
<body>

<?= $this->render('//pdf/_cabecera', [
    'titulo' => 'SOLICITUD DE PROFORMA DE COTIZACIÓN',
    // Una solicitud sin codigo (no deberia haber: la migracion se los dio a todas), su referencia de antes.
    'codigo' => $solicitud->codigoDocumento ?? $solicitud->codigo(),
    'version' => $solicitud::VERSION_DOCUMENTO,
    'proceso' => backend\models\Proceso::nombreDe($solicitud->codigoProceso()),
    // La fecha de envio; sin ella, la del dia en que se imprime.
    'fecha' => $solicitud->fechaDelDocumento(),
]) ?>

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
                <th width="6%">N.°</th>
                <th>Descripción</th>
                <th width="12%">Unidad</th>
                <th width="12%">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            <?php $unidades = backend\models\UnidadMedida::nombresPorAbreviatura(); ?>
            <?php foreach ($solicitud->items as $fila): ?>
                <?php $item = $fila->proyectoItem; ?>
                <tr>
                    <td class="numero"><?= ++$numero ?></td>
                    <td>
                        <?= Html::encode($item->subcatalogoItem->descripcion ?? '') ?>
                        <?php // Solo el NET de la emision VIGENTE; sin emision (o anulada) no sale nada. ?>
                        <?php $codigoNet = $item->emisionVigente?->codigo(); ?>
                        <?php if ($codigoNet !== null): ?>
                            (<?= Html::encode($codigoNet) ?>)
                        <?php endif; ?>
                        <?php if (!empty($fila->marcaModeloReferencia)): ?>
                            (<?= Html::encode($fila->marcaModeloReferencia) ?>)
                        <?php endif; ?>
                        <?php if (!empty($item->observacion)): ?>
                            <br><span class="observacion"><?= Html::encode($item->observacion) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php // El nombre de la unidad ("Metro", no "m"), para que el proveedor entienda; si no esta en el catalogo, la abreviatura. ?>
                    <td><?= Html::encode($unidades[(string) $item->unidadMedida] ?? (string) ($item->unidadMedida ?? '')) ?></td>
                    <td class="numero"><?= Monto::cantidad($item->cantidad ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($configuracion->textoNotaFinal)): ?>
    <div class="texto nota-final"><?= nl2br(Html::encode($configuracion->textoNotaFinal)) ?></div>
<?php endif; ?>

<?php // Elaborado por la DTIC: una sola firma, a todo el ancho, al FONDO de la última página (PresupuestoPdfGenerator). ?>
<?= backend\components\PresupuestoPdfGenerator::MARCA_AL_PIE ?>

<?= $this->render('//pdf/_control-elaboracion', [
    'version' => $solicitud::VERSION_DOCUMENTO,
    'elaborado' => backend\models\ConfiguracionPresupuesto::obtener()->firma(1),
    'aprobado' => null,
    'fechaAprobacion' => '',
    'soloElaborado' => true,
]) ?>

</body>
</html>
