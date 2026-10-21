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
 * Una tabla por categoría, con su subtotal, y el total general al final. Solo
 * la primera tabla lleva los títulos de las columnas (N.°, Descripción...):
 * las demás tienen los mismos anchos, así que se leen igual y se gana
 * espacio. La numeración (N.°) sigue corrida entre categorías. En cada línea, el nombre
 * DTIC con su observación debajo y, si tiene especificación técnica emitida, el código NET vigente entre
 * paréntesis (sin nombre SIGMA).
 * Si el presupuesto lleva mano de obra de la DTIC (Presupuesto::$manoDeObra),
 * va en la categoría "Mano de obra" (al final si no existe), con precios en
 * 0.00. Después del total: nota; al fondo de la última página, dos firmas y banda de pie verde con los
 * contactos, todo desde redes.ConfiguracionPresupuesto (presupuesto/
 * configuracion); nada de eso queda escrito aquí. No lleva proveedor por línea, resumen por partida, anexo
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

$ubicacion = $proyecto->ubicacion();
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
    /* Cabecera repetida en cada pagina: ver views/pdf/_cabecera.php. */
    @page {
        header: html_cabecera;
        margin-header: 5mm;
        margin-top: 32mm;
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
    /* Las categorias van pegadas, como una sola tabla: el titulo no tiene
       margen y usa los mismos bordes que las celdas. Sin borde arriba (ya
       esta el de la tabla anterior), salvo el de la primera categoria. */
    .categoria-titulo {
        background-color: #d0d0d0;
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
    .nota {
        margin-top: 10px;
        font-size: 10px;
    }
    /* page-break-inside: mPDF no corta estas tablas entre dos paginas. */
    table.firmas {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        page-break-inside: avoid;
    }
    table.firmas td {
        text-align: center;
        vertical-align: top;
        padding: 4px 0 0 0;
    }
    /* Espacio para firmar y la raya debajo. */
    table.firmas td.firma-raya {
        height: 60px;
        border-bottom: 1px solid #000;
    }
    .firma-nombre {
        font-size: 10px;
    }
    .firma-cargo {
        font-size: 9px;
        font-weight: bold;
    }
    table.pie {
        width: 100%;
        border-collapse: collapse;
        margin-top: 30px;
        page-break-inside: avoid;
    }
    table.pie td {
        background-color: #e2efda;
        border: 1px solid #a9c99a;
        text-align: center;
        vertical-align: top;
        padding: 6px;
        font-size: 9px;
    }
    .pie-titulo {
        font-weight: bold;
        font-size: 10px;
        margin-bottom: 2px;
    }
    .firmas-mano {
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

<?= $this->render('//pdf/_cabecera', [
    'titulo' => 'PRESUPUESTO DE INSTALACIÓN DE REDES',
    'numero' => 'N.° ' . ($presupuesto->numeroPresupuesto ?: '—'),
    'facultad' => $proyecto->facultad,
    'fecha' => $presupuesto->fechaFormateada() ?: '—',
]) ?>

<?php // N.°, facultad y fecha ya van en la cabecera. ?>
<table class="datos-generales">
    <tr>
        <td><strong>Código de proyecto:</strong> <?= Html::encode($proyecto->codigo ?: '—') ?> (Fase <?= (int) $proyecto->numeroFase ?>)</td>
        <td><strong><?= Html::encode($oficio !== '' ? $oficio : 'Oficio DTIC: —') ?></strong></td>
    </tr>
    <tr>
        <td><strong>Proyecto:</strong> <?= Html::encode($proyecto->nombre ?? '—') ?></td>
        <td><strong>Facultad / Edificio / Bloques:</strong> <?= Html::encode($ubicacion !== '' ? $ubicacion : '—') ?></td>
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
                                $linea->proyectoItem?->emisionVigente?->codigoNet(),
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
                        <td class="numero" width="10%"><?= Yii::$app->formatter->asDecimal(1, 2) ?></td>
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

    <div class="total-general">
        TOTAL GENERAL Bs.: <?= Yii::$app->formatter->asDecimal($totalGeneral, 2) ?>
    </div>
<?php endif; ?>

<?php if (!empty($configuracion->textoNota)): ?>
    <div class="nota"><?= nl2br(Html::encode($configuracion->textoNota)) ?></div>
<?php endif; ?>

<?php // Firmas y banda de pie: al fondo de la última página (PresupuestoPdfGenerator). ?>
<?= backend\components\PresupuestoPdfGenerator::MARCA_AL_PIE ?>

<?php
$firmas = $configuracion->firmas();
// Columnas de la tabla de firmas: null = espacio en blanco. La raya de cada
// firma es el borde inferior de su celda (mPDF no dibuja bordes de un div
// vacio dentro de una celda); los espacios separan las rayas.
$columnasFirma = count($firmas) === 1 ? [null, $firmas[0], null] : [$firmas[0] ?? null, null, $firmas[1] ?? null];
$anchos = count($firmas) === 1 ? [25, 50, 25] : [45, 10, 45];
?>
<?php if ($firmas !== []): ?>
    <table class="firmas">
        <tr>
            <?php foreach ($columnasFirma as $i => $firma): ?>
                <td width="<?= $anchos[$i] ?>%" class="<?= $firma !== null ? 'firma-raya' : '' ?>"></td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($columnasFirma as $firma): ?>
                <td>
                    <?php if ($firma !== null): ?>
                        <div class="firma-nombre"><?= Html::encode(trim($firma['prefijo'] . ' ' . $firma['nombre'])) ?></div>
                        <div class="firma-cargo"><?= Html::encode($firma['cargo']) ?></div>
                    <?php endif; ?>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>
<?php else: ?>
    <?php // Sin firmantes configurados: la firma de siempre, para llenar a mano. ?>
    <div class="firmas-mano">
        <div class="firma-linea"></div>
        <div class="firma-etiqueta">Elaborado por: ________________________________</div>
        <div class="firma-etiqueta">Fecha: ________________________________</div>
    </div>
<?php endif; ?>

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
