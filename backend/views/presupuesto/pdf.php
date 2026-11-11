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
 * UNA sola tabla (2026-11-11; antes una por categoría): cada categoría es una
 * banda gris sin líneas, la primera ARRIBA de los títulos de columnas (N.°,
 * Descripción...), cada una con su subtotal, y el TOTAL como última fila. La numeración (N.°) sigue corrida entre categorías. En cada línea, el nombre
 * DTIC con su observación debajo y, entre paréntesis, el modelo sugerido (sin nombre SIGMA; sin el código
 * de la especificación ni el oficio de la DTIC desde el 2026-11-11).
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
 * comparativo, unidad, área de intervención ni estado (la unidad y el área van en la ficha técnica
 * del proyecto desde el 2026-11-05).
 *
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $presupuesto
 * @var array $grupos Ver PresupuestoService::agruparPorCategoria(), con la mano de obra ya
 *            ubicada por PresupuestoService::ubicarManoDeObra() (clave 'manoDeObra' de cada grupo)
 * @var float $totalGeneral
 * @var backend\models\ConfiguracionPresupuesto $configuracion nota y firmas (editables)
 * @var string $proceso nombre del proceso para la cabecera (Proceso::nombreDe($presupuesto->codigoProceso()),
 *             lo calcula el controlador)
 */

$proyecto = $presupuesto->proyecto;

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
    /* Escala de grises, como la lista de materiales y el formulario de
       especificacion (2026-11-03; antes azules): titulos de columna y total
       en gris medio, categorias en gris, subtotales en gris claro. */
    table.items th {
        background-color: #e0e0e0;
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
    /* Fila de categoria dentro de la tabla: banda gris SIN lineas de celda
       (2026-11-11, como la lista de materiales); las de las filas vecinas se quedan. */
    table.items td.categoria {
        background-color: #d0d0d0;
        font-style: italic;
        font-weight: bold;
        border: none;
    }
    tr.subtotal td {
        font-weight: bold;
        text-align: right;
        background-color: #f2f2f2;
    }
    /* Ultima fila de la tabla: el total. */
    table.items tr.total-general td {
        background-color: #e0e0e0;
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
    'proceso' => $proceso,
    'fecha' => $presupuesto->fechaFormateada() ?: '—',
]) ?>

<?php // Código, versión y fecha ya van en la cabecera. El proyecto, en su única
// línea (Proyecto::lineaDocumento(): "PROYECTO: 001 – NOMBRE (FASE n)", sin
// negrilla; la fase solo si hay varias). Sin unidad ni área de intervención
// desde el 2026-11-05 (revisión del Ing., puntos 5 y 6: están en la ficha
// técnica del proyecto). ?>
<table class="datos-generales">
    <tr>
        <?php // Sin el oficio de la DTIC desde el 2026-11-11 (va en la carta de entrega). ?>
        <td><?= Html::encode($proyecto->lineaDocumento()) ?></td>
    </tr>
</table>

<?php if (empty($grupos)): ?>
    <p>Este presupuesto todavía no tiene líneas cargadas.</p>
<?php else: ?>
    <?php // UNA sola tabla (2026-11-11, como la lista de materiales): cada categoria es una banda gris sin
    // lineas; la PRIMERA va arriba de los titulos de columnas y las demas entre las lineas. Cada categoria
    // conserva su subtotal (es dinero) y el TOTAL es la ultima fila. Los titulos no van en <thead>, asi que
    // no se repiten en cada pagina. ?>
    <table class="items">
        <colgroup>
            <col width="5%"><col width="45%"><col width="8%"><col width="8%"><col width="10%"><col width="12%"><col width="12%">
        </colgroup>
        <tbody>
            <?php foreach ($grupos as $nombreCategoria => $grupo): ?>
                <tr><td colspan="7" class="categoria"><?= Html::encode($nombreCategoria) ?></td></tr>
                <?php if ($nombreCategoria === array_key_first($grupos)): ?>
                    <tr>
                        <th>N.°</th>
                        <th>Descripción</th>
                        <th>Partida</th>
                        <th>Unidad</th>
                        <th>Cantidad</th>
                        <th>Precio unitario</th>
                        <th>Precio total</th>
                    </tr>
                <?php endif; ?>
                <?php foreach ($grupo['lineas'] as $linea): ?>
                    <tr>
                        <td class="numero" width="5%"><?= ++$numero ?></td>
                        <td width="45%">
                            <?= Html::encode($linea->subcatalogoItem->descripcion ?? '') ?>
                            <?php
                            // Entre parentesis, en la misma linea: el modelo sugerido. Sin el
                            // codigo de la especificacion (RD-PI-ET-NNN) desde el 2026-11-11.
                            $referencia = array_filter([
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
                <tr class="subtotal">
                    <td colspan="6">Subtotal <?= Html::encode($nombreCategoria) ?>:</td>
                    <td class="numero"><?= Yii::$app->formatter->asDecimal($grupo['subtotal'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-general">
                <td colspan="6">TOTAL (Bs.)</td>
                <td class="numero"><?= Yii::$app->formatter->asDecimal($totalGeneral, 2) ?></td>
            </tr>
        </tbody>
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
