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
 * 0.00 (cantidad también 0.00). Después del total (fila "TOTAL (Bs.)" en azul claro): la nota, centrada.
 * Desde el 2026-10-29 el presupuesto es SOLO FINANCIERO: lo que provee la DTIC y las condiciones previas
 * (antes "Otros materiales…" y "Trabajos complementarios…" aquí) van en la lista de materiales. Al fondo de la última página, el "control de elaboración" del
 * lineamiento RD-GD-LI-001 (versión, elaborado por, aprobado por, firmas y fecha de aprobación). SIN
 * pie de página desde el 2026-10-30 (revisión del Ing. de DTIC: "si es sistema de gestión documental no
 * tiene pie de página"; los contactos ya están en el membrete de la carta). Nombres y cargos salen de
 * redes.ConfiguracionPresupuesto (presupuesto/configuracion); nada de eso queda escrito aquí.
 * La "Fecha" de la cabecera es la de CREACIÓN del presupuesto (Presupuesto::fechaFormateada()) y la de
 * aprobación va abajo, en el control de elaboración.
 * Un presupuesto APROBADO usa las firmas copiadas al aprobarlo (Presupuesto::firmaDeControl()).
 * Cabecera y fuente (Carlito = medidas de Calibri) también según el lineamiento. No lleva proveedor por línea, resumen por partida, anexo
 * comparativo, área de intervención ni estado: se ven en la pantalla del
 * paso Presupuesto.
 *
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $presupuesto
 * @var array $grupos Ver PresupuestoService::agruparPorCategoria(), con la mano de obra ya
 *            ubicada por PresupuestoService::ubicarManoDeObra() (clave 'manoDeObra' de cada grupo)
 * @var float $totalGeneral
 * @var backend\models\ConfiguracionPresupuesto $configuracion nota y firmas (editables)
 * @var string $oficio "DTIC Of. Nº 598/2026" (formato de la configuracion de la carta,
 *             CartaService::formatearOficio(); año de la carta o de creacion); vacio si aun no tiene oficio
 */

$proyecto = $presupuesto->proyecto;

$area = $proyecto->areaIntervencionTexto();
$numero = 0;
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
</style>
</head>
<body>

<?= $this->render('//pdf/_cabecera', [
    'titulo' => 'PRESUPUESTO DE INSTALACIÓN DE REDES',
    'codigo' => $presupuesto->numeroPresupuesto ?: '—',
    'version' => $presupuesto::VERSION_DOCUMENTO,
    'proceso' => backend\models\Proceso::nombreDe($presupuesto->codigoProceso()),
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
        <td><strong>Unidad / dependencia:</strong> <?= Html::encode($proyecto->unidadDependencia ?: '—') ?></td>
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
                <?php if ($grupo['manoDeObra']): ?>
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

<?php // Control de elaboración: al fondo de la última página (PresupuestoPdfGenerator). ?>
<?= backend\components\PresupuestoPdfGenerator::MARCA_AL_PIE ?>

<?php // Elaborado por (firma 1) y aprobado por (firma 2, con su V°B°); la fecha queda vacia hasta que se apruebe. ?>
<?= $this->render('//pdf/_control-elaboracion', [
    'version' => $presupuesto::VERSION_DOCUMENTO,
    // Aprobado: la copia congelada al aprobar; si no, la configuracion de hoy.
    'elaborado' => $presupuesto->firmaDeControl(1, $configuracion),
    'aprobado' => $presupuesto->firmaDeControl(2, $configuracion),
    'fechaAprobacion' => $presupuesto->fechaAprobacionFormateada(),
]) ?>

</body>
</html>
