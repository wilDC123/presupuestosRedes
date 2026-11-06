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
 * Despues de los items, las dos secciones sin precio que hasta el 2026-10-29
 * salian al final del presupuesto, SOLO si tienen filas: "Otros materiales y
 * equipamiento que proveerá la D.T.I.C." (agrupados por subtitulo) y los
 * trabajos complementarios (agrupados por tipo).
 *
 * Desde el 2026-10-30 (paso 8b) es un documento del lineamiento RD-GD-LI-001:
 * cabecera con el CODIGO DEL PROYECTO (la lista no tiene codigo propio),
 * version y fecha, y al fondo el control de elaboracion. Version, borrador y
 * firmas salen de ListaItemsService::documento(): en edicion, la version
 * siguiente con marca de agua BORRADOR (la pone el controlador) y las firmas
 * de la configuracion de hoy; enviada o cerrada, la ultima aprobada con su
 * fecha y la COPIA de sus firmas. La "Fecha" de la cabecera es la de CREACION
 * de la version (2026-10-30, paso 9).
 *
 * CSS deliberadamente simple, igual que el PDF del presupuesto: mPDF
 * interpreta un subconjunto de CSS pensado para documentos.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * @var array<int, array{nombre: string, items: backend\models\ProyectoItem[]}> $grupos Ver ListaItemsService::agrupar()
 * @var array{version: string, borrador: bool, aprobacion: backend\models\ListaMaterialesAprobacion|null, fechaCreacion: string} $documento
 * @var backend\models\ConfiguracionPresupuesto $configuracion firmas de hoy (mientras no esta aprobada)
 * @var array<string, backend\models\ProyectoMaterialDtic[]> $materiales [subtitulo => materiales], ver MaterialDticService::agrupar()
 * @var array<string, backend\models\ProyectoCondicionPrevia[]> $trabajos [tipo => trabajos], ver CondicionPreviaService::agrupar()
 * @var string $proceso nombre del proceso para la cabecera: el codigo es el del proyecto (RD-PI-PT-NNN), que
 *             siempre lleva PI; lo calcula el controlador
 */

$totalItems = array_sum(array_map(static fn ($grupo) => count($grupo['items']), $grupos));
$numero = 0;

// Aprobada: su fecha y sus firmas copiadas; borrador (o fecha desconocida): la de hoy y la configuracion.
$aprobacion = $documento['aprobacion'];
$fechaAprobacion = $aprobacion?->fechaAprobacionFormateada() ?? '';
$firmas = $aprobacion ?? $configuracion;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* Calibri 11 (lineamiento RD-GD-LI-001, 8.8.1): Carlito tiene sus mismas medidas. */
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
    table.datos-generales {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }
    table.datos-generales td {
        padding: 3px 4px;
        vertical-align: top;
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
        font-size: 10pt;
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
        font-size: 9pt;
        color: #333;
    }
    tr.subtotal td {
        font-weight: bold;
        text-align: right;
        background-color: #f2f2f2;
    }
    .total-general {
        text-align: right;
        font-size: 11pt;
        font-weight: bold;
        margin-top: 10px;
        border-top: 2px solid #000;
        padding-top: 6px;
    }
    /* Secciones sin precio (materiales de la DTIC, trabajos complementarios). */
    .seccion-titulo {
        font-weight: bold;
        margin-top: 16px;
        margin-bottom: 4px;
    }
    td.subtitulo {
        background-color: #f2f2f2;
        font-style: italic;
        font-weight: bold;
    }
</style>
</head>
<body>

<?= $this->render('//pdf/_cabecera', [
    'titulo' => backend\models\ListaMaterialesAprobacion::TITULO,
    'codigo' => $proyecto->codigo ?: '—',
    'version' => $documento['version'],
    'proceso' => $proceso,
    // La de CREACION de esta version (la de aprobacion va abajo, en el control de elaboracion).
    'fecha' => $documento['fechaCreacion'] ?: '—',
]) ?>

<?php // Codigo, version y fecha ya van en la cabecera. Solo la linea del proyecto: sin unidad ni area
// de intervencion desde el 2026-11-05 (revision del Ing., puntos 5 y 6: estan en la ficha tecnica). ?>
<table class="datos-generales">
    <tr>
        <td><strong>Proyecto:</strong> <?= Html::encode($proyecto->nombre) ?> (Fase <?= (int) $proyecto->numeroFase ?>)</td>
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
                            <?php if ($item->especificacion !== null): ?>
                                <br><span class="observacion">
                                    Según especificación técnica <?= Html::encode($item->referenciaEspecificacion()) ?><?= $proyecto->codigo ? ' — proyecto ' . Html::encode($proyecto->codigo) : '' ?>
                                </span>
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

<?php if ($materiales !== []): ?>
    <div class="seccion-titulo"><?= Html::encode(backend\models\ProyectoMaterialDtic::TITULO) ?></div>
    <table class="items">
        <thead>
            <tr>
                <th>Descripción</th>
                <th width="14%">Unidad</th>
                <th width="14%">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($materiales as $subtitulo => $filas): ?>
                <tr><td colspan="3" class="subtitulo"><?= Html::encode($subtitulo) ?></td></tr>
                <?php foreach ($filas as $fila): ?>
                    <tr>
                        <td><?= Html::encode($fila->descripcion) ?></td>
                        <td><?= Html::encode((string) $fila->unidadMedida) ?></td>
                        <td class="numero"><?= Yii::$app->formatter->asDecimal($fila->cantidad, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($trabajos !== []): ?>
    <div class="seccion-titulo"><?= Html::encode(backend\models\ProyectoCondicionPrevia::TITULO) ?></div>
    <table class="items">
        <thead>
            <tr>
                <th>Trabajos complementarios</th>
                <th width="30%">Unidad encargada</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($trabajos as $tipo => $filas): ?>
                <tr><td colspan="2" class="subtitulo"><?= Html::encode($tipo) ?></td></tr>
                <?php foreach ($filas as $fila): ?>
                    <tr>
                        <td><?= Html::encode($fila->descripcion) ?></td>
                        <td><?= Html::encode((string) $fila->unidadEncargada) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php // Control de elaboración: al fondo de la última página (PresupuestoPdfGenerator). ?>
<?= backend\components\PresupuestoPdfGenerator::MARCA_AL_PIE ?>

<?= $this->render('//pdf/_control-elaboracion', [
    'version' => $documento['version'],
    'elaborado' => $firmas->firma(1),
    'aprobado' => $firmas->firma(2),
    'fechaAprobacion' => $fechaAprobacion,
]) ?>

</body>
</html>
