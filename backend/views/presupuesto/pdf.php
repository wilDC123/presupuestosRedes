<?php

declare(strict_types=1);

use backend\models\PresupuestoComplemento;
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
 * Una tabla por categoría, con su subtotal, y el total general al final. Solo
 * la primera tabla lleva los títulos de las columnas (N.°, Descripción...):
 * las demás tienen los mismos anchos, así que se leen igual y se gana
 * espacio. La numeración (N.°) sigue corrida entre categorías. En cada línea, el nombre
 * DTIC con su observación debajo y, si tiene especificación técnica emitida, el código NET vigente entre
 * paréntesis (sin nombre SIGMA).
 * Si el presupuesto lleva mano de obra de la DTIC (Presupuesto::$manoDeObra),
 * va en la categoría "Mano de obra" (al final si no existe), con precios en
 * 0.00 (cantidad también 0.00). Después del total (fila "TOTAL (Bs.)" en azul claro): la nota, centrada, y
 * las secciones SIN PRECIO, solo si tienen filas (Presupuesto::$complementos): "Otros materiales y
 * equipamiento que proveerá la D.T.I.C." y "Trabajos complementarios...", agrupadas por subtítulo y sin
 * sumar al total. Al fondo de la última página, el "control de elaboración" del
 * lineamiento RD-GD-LI-001 (versión, elaborado por, aprobado por, firmas y fecha de aprobación) y la
 * banda de pie verde con los contactos. Nombres, cargos y contactos salen de
 * redes.ConfiguracionPresupuesto (presupuesto/configuracion); nada de eso queda escrito aquí.
 * Un presupuesto APROBADO usa las firmas copiadas al aprobarlo (Presupuesto::firmaDeControl()).
 * Cabecera y fuente (Carlito = medidas de Calibri) también según el lineamiento. No lleva proveedor por línea, resumen por partida, anexo
 * comparativo, área de intervención ni estado: se ven en la pantalla del
 * paso Presupuesto.
 *
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $presupuesto
 * @var array $grupos Ver PresupuestoService::agruparPorCategoria()
 * @var float $totalGeneral
 * @var backend\models\ConfiguracionPresupuesto $configuracion nota, firmas y banda de pie (editables)
 * @var string $oficio "DTIC Of. Nº 598/2026" (formato de la configuracion de la carta,
 *             CartaService::formatearOficio()); vacio si el presupuesto aun no tiene oficio
 */

$proyecto = $presupuesto->proyecto;

$area = $proyecto->areaIntervencionTexto();
$numero = 0;

// La fila de mano de obra de la DTIC va dentro de la categoria "Mano de
// obra" si el presupuesto ya tiene una (mano de obra contratada); si no, en
// una tabla "Mano de obra" propia, al final y con subtotal 0.00.
$grupoManoDeObra = null;

if ($presupuesto->manoDeObra !== null) {
    foreach (array_keys($grupos) as $nombreCategoria) {
        if (mb_strtoupper(trim((string) $nombreCategoria), 'UTF-8') === 'MANO DE OBRA') {
            $grupoManoDeObra = $nombreCategoria;
        }
    }

    if ($grupoManoDeObra === null) {
        $grupoManoDeObra = 'Mano de obra';
        $grupos[$grupoManoDeObra] = ['orden' => PHP_INT_MAX, 'lineas' => [], 'subtotal' => 0.0];
    }
}

// Secciones sin precio: [tipo => [subtitulo => filas]], en el orden en que se escribieron.
$complementos = [];

foreach ($presupuesto->complementos as $complemento) {
    $complementos[$complemento->tipo][$complemento->grupo][] = $complemento;
}
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
        width: 50%;
    }
    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }
    table.items th,
    table.items td {
        border: 1px solid #666;
        padding: 4px 4px;
        font-size: 10pt;
    }
    /* Mismos colores que las secciones sin precio (2026-10-23): titulos de
       columna en azul oscuro, categorias en celeste, subtotales y total en
       azul claro. */
    table.items th {
        background-color: #1f3864;
        color: #fff;
        font-weight: bold;
        text-align: center;
    }
    table.items td.numero {
        text-align: right;
    }
    .observacion {
        font-style: italic;
        font-size: 9pt;
        color: #333;
    }
    /* Las categorias van pegadas, como una sola tabla: el titulo no tiene
       margen y usa los mismos bordes que las celdas. Sin borde arriba (ya
       esta el de la tabla anterior), salvo el de la primera categoria. */
    .categoria-titulo {
        background-color: #ddebf7;
        font-style: italic;
        font-weight: bold;
        padding: 4px;
        border: 1px solid #666;
        border-top: none;
    }
    .categoria-titulo.primera {
        border-top: 1px solid #666;
    }
    tr.subtotal td {
        font-weight: bold;
        text-align: right;
        background-color: #eef3fa;
    }
    /* Fila de total pegada a la ultima categoria, con los mismos bordes. */
    table.total-general {
        width: 100%;
        border-collapse: collapse;
    }
    table.total-general td {
        border: 1px solid #666;
        border-top: none;
        background-color: #dbe5f1;
        font-size: 11pt;
        font-weight: bold;
        text-align: right;
        padding: 5px 4px;
    }
    .nota {
        margin-top: 6px;
        font-size: 8pt;
        text-align: center;
    }
    /* Secciones sin precio (materiales de la DTIC, trabajos complementarios). */
    .complementos-titulo {
        font-weight: bold;
        margin-top: 16px;
        margin-bottom: 4px;
    }
    table.complementos {
        width: 100%;
        border-collapse: collapse;
        page-break-inside: auto;
    }
    table.complementos th,
    table.complementos td {
        border: 1px solid #666;
        padding: 4px;
        font-size: 10pt;
    }
    table.complementos th {
        background-color: #1f3864;
        color: #fff;
        font-weight: bold;
        text-align: center;
    }
    table.complementos td.subtitulo {
        background-color: #ddebf7;
        font-style: italic;
        font-weight: bold;
    }
    table.complementos td.numero {
        text-align: right;
    }
    table.pie {
        width: 100%;
        border-collapse: collapse;
        margin-top: 30px;
        page-break-inside: avoid;
    }
    /* Pie de pagina en tamaño 9 (lineamiento RD-GD-LI-001, 8.8.1). */
    table.pie td {
        background-color: #e2efda;
        border: 1px solid #a9c99a;
        text-align: center;
        vertical-align: top;
        padding: 6px;
        font-size: 9pt;
    }
    .pie-titulo {
        font-weight: bold;
        margin-bottom: 2px;
    }
</style>
</head>
<body>

<?= $this->render('//pdf/_cabecera', [
    'titulo' => 'PRESUPUESTO DE INSTALACIÓN DE REDES',
    'codigo' => $presupuesto->numeroPresupuesto ?: '—',
    'version' => $presupuesto::VERSION_DOCUMENTO,
    'proceso' => backend\models\CorrelativoDocumento::NOMBRE_PROCESO,
    'fecha' => $presupuesto->fechaFormateada() ?: '—',
]) ?>

<?php // Código, versión y fecha ya van en la cabecera. ?>
<table class="datos-generales">
    <tr>
        <td><strong>Código de proyecto:</strong> <?= Html::encode($proyecto->codigo ?: '—') ?> (Fase <?= (int) $proyecto->numeroFase ?>)</td>
        <td><strong><?= Html::encode($oficio !== '' ? $oficio : 'Oficio DTIC: —') ?></strong></td>
    </tr>
    <tr>
        <td><strong>Proyecto:</strong> <?= Html::encode($proyecto->nombre ?? '—') ?></td>
        <td><strong>Unidad solicitante:</strong> <?= Html::encode($proyecto->unidadSolicitante ?: '—') ?></td>
    </tr>
    <?php // A todo el ancho: varias areas pueden ocupar mas de una linea. ?>
    <tr>
        <td colspan="2"><strong>Área de intervención:</strong> <?= Html::encode($area !== '' ? $area : '—') ?></td>
    </tr>
</table>

<?php if (empty($grupos)): ?>
    <p>Este presupuesto todavía no tiene líneas cargadas.</p>
<?php else: ?>
    <?php foreach ($grupos as $nombreCategoria => $grupo): ?>
        <div class="categoria-titulo<?= $nombreCategoria === array_key_first($grupos) ? ' primera' : '' ?>"><?= Html::encode($nombreCategoria) ?></div>
        <table class="items">
            <?php // Anchos fijos en TODAS las tablas: asi las columnas coinciden
            // con las de la primera, la unica que lleva los titulos. ?>
            <?php if ($nombreCategoria === array_key_first($grupos)): ?>
                <thead>
                    <tr>
                        <th>N.°</th>
                        <th>Descripción</th>
                        <th>Partida</th>
                        <th>Unidad</th>
                        <th>Cantidad</th>
                        <th>Precio unitario</th>
                        <th>Precio total</th>
                    </tr>
                </thead>
            <?php endif; ?>
            <colgroup>
                <col width="5%"><col width="45%"><col width="8%"><col width="8%"><col width="10%"><col width="12%"><col width="12%">
            </colgroup>
            <tbody>
                <?php foreach ($grupo['lineas'] as $linea): ?>
                    <tr>
                        <td class="numero" width="5%"><?= ++$numero ?></td>
                        <td width="45%">
                            <?= Html::encode($linea->subcatalogoItem->descripcion ?? '') ?>
                            <?php
                            // Entre parentesis, en la misma linea: el NET de la emision
                            // VIGENTE (sin emision, o anulada, no sale) y el modelo sugerido.
                            $referencia = array_filter([
                                $linea->proyectoItem?->emisionVigente?->codigo(),
                                $linea->proyectoItem?->modeloSugerido(),
                            ]);
                            ?>
                            <?php if ($referencia !== []): ?>
                                (<?= Html::encode(implode(' – ', $referencia)) ?>)
                            <?php endif; ?>
                            <?php if (!empty($linea->observacion)): ?>
                                <br><span class="observacion"><?= Html::encode($linea->observacion) ?></span>
                            <?php endif; ?>
                        </td>
                        <td width="8%"><?= Html::encode($linea->partida ?? '') ?></td>
                        <td width="8%"><?= Html::encode($linea->unidadMedida ?? '') ?></td>
                        <td class="numero" width="10%"><?= Yii::$app->formatter->asDecimal($linea->cantidad, 2) ?></td>
                        <td class="numero" width="12%"><?= Yii::$app->formatter->asDecimal($linea->precioUnitario, 2) ?></td>
                        <td class="numero" width="12%"><?= Yii::$app->formatter->asDecimal($linea->precioTotal, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($nombreCategoria === $grupoManoDeObra): ?>
                    <tr>
                        <td class="numero" width="5%"><?= ++$numero ?></td>
                        <td width="45%">
                            MANO DE OBRA
                            <br><span class="observacion"><?= Html::encode($presupuesto->manoDeObra) ?></span>
                        </td>
                        <td width="8%"></td>
                        <td width="8%"><?= Html::encode($presupuesto::UNIDAD_MANO_DE_OBRA) ?></td>
                        <td class="numero" width="10%"><?= Yii::$app->formatter->asDecimal($presupuesto::CANTIDAD_MANO_DE_OBRA, 2) ?></td>
                        <td class="numero" width="12%"><?= Yii::$app->formatter->asDecimal(0, 2) ?></td>
                        <td class="numero" width="12%"><?= Yii::$app->formatter->asDecimal(0, 2) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="subtotal">
                    <td colspan="6">Subtotal <?= Html::encode($nombreCategoria) ?>:</td>
                    <td class="numero"><?= Yii::$app->formatter->asDecimal($grupo['subtotal'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endforeach; ?>

    <table class="total-general">
        <tr>
            <td width="88%">TOTAL (Bs.)</td>
            <td width="12%"><?= Yii::$app->formatter->asDecimal($totalGeneral, 2) ?></td>
        </tr>
    </table>
<?php endif; ?>

<?php if (!empty($configuracion->textoNota)): ?>
    <div class="nota"><?= nl2br(Html::encode($configuracion->textoNota)) ?></div>
<?php endif; ?>

<?php if (!empty($complementos[PresupuestoComplemento::TIPO_MATERIAL])): ?>
    <div class="complementos-titulo"><?= Html::encode(PresupuestoComplemento::TIPOS[PresupuestoComplemento::TIPO_MATERIAL]) ?></div>
    <table class="complementos">
        <thead>
            <tr>
                <th width="50%">Descripción *</th>
                <th width="12%">Cantidad</th>
                <th width="10%">Unidad</th>
                <th width="28%">Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($complementos[PresupuestoComplemento::TIPO_MATERIAL] as $subtitulo => $filas): ?>
                <tr><td colspan="4" class="subtitulo"><?= Html::encode($subtitulo) ?></td></tr>
                <?php foreach ($filas as $fila): ?>
                    <tr>
                        <td><?= Html::encode($fila->descripcion) ?></td>
                        <td class="numero"><?= Yii::$app->formatter->asDecimal($fila->cantidad, 2) ?></td>
                        <td><?= Html::encode((string) $fila->unidadMedida) ?></td>
                        <td><?= Html::encode((string) $fila->observacion) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($complementos[PresupuestoComplemento::TIPO_TRABAJO])): ?>
    <div class="complementos-titulo"><?= Html::encode(PresupuestoComplemento::TIPOS[PresupuestoComplemento::TIPO_TRABAJO]) ?></div>
    <table class="complementos">
        <thead>
            <tr>
                <th width="45%">Trabajos complementarios</th>
                <th width="25%">Unidad encargada</th>
                <th width="30%">Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php // Los dos subtitulos fijos, en su orden, aunque se hayan escrito al reves. ?>
            <?php foreach (PresupuestoComplemento::GRUPOS_TRABAJO as $subtitulo): ?>
                <?php if (!empty($complementos[PresupuestoComplemento::TIPO_TRABAJO][$subtitulo])): ?>
                    <tr><td colspan="3" class="subtitulo"><?= Html::encode($subtitulo) ?></td></tr>
                    <?php foreach ($complementos[PresupuestoComplemento::TIPO_TRABAJO][$subtitulo] as $fila): ?>
                        <tr>
                            <td><?= Html::encode($fila->descripcion) ?></td>
                            <td><?= Html::encode((string) $fila->unidadEncargada) ?></td>
                            <td><?= Html::encode((string) $fila->observacion) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php // Control de elaboración y banda de pie: al fondo de la última página (PresupuestoPdfGenerator). ?>
<?= backend\components\PresupuestoPdfGenerator::MARCA_AL_PIE ?>

<?php // Elaborado por (firma 1) y aprobado por (firma 2, con su V°B°); la fecha queda vacia hasta que se apruebe. ?>
<?= $this->render('//pdf/_control-elaboracion', [
    'version' => $presupuesto::VERSION_DOCUMENTO,
    // Aprobado: la copia congelada al aprobar; si no, la configuracion de hoy.
    'elaborado' => $presupuesto->firmaDeControl(1, $configuracion),
    'aprobado' => $presupuesto->firmaDeControl(2, $configuracion),
    'fechaAprobacion' => $presupuesto->fechaAprobacionFormateada(),
]) ?>

<?php $columnasPie = $configuracion->columnasPie(); ?>
<?php if ($columnasPie !== []): ?>
    <table class="pie">
        <tr>
            <?php foreach ($columnasPie as $columna): ?>
                <td width="<?= 100 / count($columnasPie) ?>%">
                    <?php if ($columna['titulo'] !== ''): ?>
                        <div class="pie-titulo"><?= Html::encode($columna['titulo']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($columna['lineas'] as $linea): ?>
                        <div><?= Html::encode($linea) ?></div>
                    <?php endforeach; ?>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>
<?php endif; ?>

</body>
</html>
