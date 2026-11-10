<?php

declare(strict_types=1);

use backend\components\NumeracionEspecificacion;
use yii\helpers\Html;

/**
 * HTML fuente del FORMULARIO DE ESPECIFICACION TECNICA (el documento que se
 * firma, se sella y se entrega al proveedor). Se renderiza con
 * renderPartial() y se le pasa a mPDF -- ver
 * EspecificacionController::actionFormulario().
 *
 * Cabecera del lineamiento de la DTIC RD-GD-LI-001 (views/pdf/_cabecera.php):
 * codigo RD-PI-ET-001 (o NET:880/26 en los de antes), version DEL DOCUMENTO
 * (01, y 02... al reemitirlo; no la de la ficha de la libreria), fecha de
 * emision y pagina. Debajo, la estructura de los
 * formularios del banco de especificaciones de DTIC: instrucciones, titulo,
 * cantidad y dependencia, y la tabla "Especificaciones tecnicas | Propuesta |
 * Cumple | No cumple" con las secciones I (requisitos), II (garantia) y III
 * (condiciones). Cada fila lleva su numero (1.1, 1.2... 2.1... y 1.4.1 debajo
 * de un subtitulo; 2026-11-03), calculado por NumeracionEspecificacion: no se
 * guarda, sale del orden y del nivel de las filas. Al final, el "control
 * de elaboracion" del lineamiento (8.12): version, elaborado por, aprobado por, firmas y como fecha de aprobacion la
 * de emision (las firmas son la copia que guardo la emision).
 *
 * TODO lo que se imprime sale de la EMISION (sus copias congeladas) y de su
 * VERSION (que ya no se modifica): reimprimir un formulario viejo da siempre
 * el mismo contenido, aunque despues cambie la ficha o la configuracion.
 *
 * CSS deliberadamente simple, igual que los otros PDF: mPDF interpreta un
 * subconjunto de CSS pensado para documentos.
 *
 * @var yii\web\View $this
 * @var backend\models\EspecificacionEmision $emision
 * @var string $proceso nombre del proceso para la cabecera (Proceso::nombreDe($emision->codigoProceso()), lo
 *             calcula quien dibuja el formulario: asi la vista no consulta la base)
 */

$version = $emision->version;
// Version del documento (lineamiento RD-GD-LI-001, 4.2): sube al reemitir.
$numeroVersion = $emision->versionTexto();
$formato = Yii::$app->formatter;
$cantidad = (float) $emision->cantidad == (int) $emision->cantidad
    ? (string) (int) $emision->cantidad
    : $formato->asDecimal($emision->cantidad, 2);

$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fecha = new DateTimeImmutable($emision->fechaEmision);
$fechaLarga = $fecha->format('d') . ' de ' . $meses[(int) $fecha->format('n') - 1] . ' de ' . $fecha->format('Y');

// Una fila de la tabla: el numero y la especificacion en la primera columna y
// las tres columnas que llenan el proveedor y el convocante, vacias. $nivel:
// 2 o 3 (1.4.1, 1.4.1.1) llevan sangria.
$fila = static fn (string $numero, string $contenido, int $nivel = 1): string =>
    '<tr><td class="espec' . ($nivel > 1 ? ' nivel-' . $nivel : '') . '">'
    . ($numero !== '' ? '<span class="numero">' . Html::encode($numero) . '</span> ' : '')
    . $contenido . '</td><td></td><td></td><td></td></tr>';
$seccion = static fn (string $titulo): string =>
    '<tr><td class="seccion">' . Html::encode($titulo) . '</td><td class="seccion"></td><td class="seccion"></td><td class="seccion"></td></tr>';
$texto = static fn (?string $valor): string => nl2br(Html::encode((string) $valor));

// Secciones: I siempre; II la garantia, solo si tiene; la siguiente, las condiciones.
$garantia = NumeracionEspecificacion::lineas($emision->garantia);
$numeroCondiciones = $garantia !== [] ? 3 : 2;
$condiciones = array_values(array_filter([
    $emision->validezOfertaDias !== null ? '<strong>Validez de la oferta:</strong> ' . (int) $emision->validezOfertaDias . ' días como mínimo.' : null,
    $emision->tiempoEntregaDias !== null ? '<strong>Tiempo de entrega:</strong> ' . (int) $emision->tiempoEntregaDias . ' días.' : null,
    !empty($emision->lugarEntrega) ? '<strong>Lugar de entrega:</strong> ' . Html::encode($emision->lugarEntrega) . '.' : null,
]));

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
    /* Nada por debajo de 11 (revision del Ing. de DTIC del 2026-11-05: "las
       letras estan muy pequenas"; antes instrucciones y tabla en 10 y 9). */
    .instrucciones {
        text-align: justify;
        font-size: 11pt;
        margin-bottom: 8px;
    }
    .bien {
        font-size: 12pt;
        font-weight: bold;
        margin-bottom: 2px;
    }
    .dato {
        margin-bottom: 2px;
    }
    table.especificaciones {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
    }
    table.especificaciones th,
    table.especificaciones td {
        border: 1px solid #000;
        padding: 4px 5px;
        vertical-align: top;
    }
    table.especificaciones td {
        font-size: 11pt;
    }
    table.especificaciones th {
        background-color: #d9d9d9;
        font-size: 11pt;
        text-align: center;
        vertical-align: middle;
    }
    td.espec {
        width: 55%;
    }
    table.especificaciones td.nivel-2 {
        padding-left: 18px;
    }
    table.especificaciones td.nivel-3 {
        padding-left: 34px;
    }
    td.seccion {
        background-color: #eeeeee;
        font-weight: bold;
    }
    .nota-final {
        margin-top: 10px;
        font-weight: bold;
    }
</style>
</head>
<body>

<?= $this->render('//pdf/_cabecera', [
    'titulo' => 'ESPECIFICACIONES TÉCNICAS DE LOS BIENES OFRECIDOS',
    'codigo' => $emision->codigo(),
    'version' => $numeroVersion,
    'proceso' => $proceso,
    'fecha' => $fecha->format('d/m/Y'),
]) ?>

<?php if (!empty($emision->textoInstrucciones)): ?>
    <div class="instrucciones"><?= $texto($emision->textoInstrucciones) ?></div>
<?php endif; ?>

<div class="bien"><?= Html::encode($emision->titulo) ?></div>
<div class="dato"><strong>Cantidad:</strong> <?= Html::encode(trim($cantidad . ' ' . $emision->unidad)) ?></div>
<?php if (!empty($emision->dependencia)): ?>
    <div class="dato"><strong>Dependencia:</strong> <?= Html::encode($emision->dependencia) ?></div>
<?php endif; ?>

<table class="especificaciones">
    <thead>
        <tr>
            <th rowspan="2">ESPECIFICACIONES TÉCNICAS</th>
            <th rowspan="2" style="width: 25%;">PROPUESTA</th>
            <th colspan="2" style="width: 20%;">(ESTA COLUMNA SERÁ LLENADA POR EL CONVOCANTE)</th>
        </tr>
        <tr>
            <th>CUMPLE</th>
            <th>NO CUMPLE</th>
        </tr>
    </thead>
    <tbody>
        <?= $seccion('I. REQUISITOS NECESARIOS') ?>

        <?= $fila(
            '1.1',
            '<strong>Especifique marca y modelo a ofertar:</strong> El proveedor debe describir la marca y modelo del bien ofrecido.'
            . (!empty($version->modeloSugerido)
                ? '<br>Se sugiere: ' . Html::encode($version->modeloSugerido) . ' o similar/superior.'
                : '')
        ) ?>

        <?= $fila('1.2', '<strong>URL:</strong> Enlace a la página web o folletería oficial del fabricante que contemple todas las especificaciones técnicas solicitadas (obligatorio).') ?>

        <?php foreach ($version->caracteristicasNumeradas() as $caracteristica): ?>
            <?php if ($caracteristica['esSubtitulo']): ?>
                <?= $fila($caracteristica['numero'], '<strong>' . Html::encode($caracteristica['valor']) . '</strong>', $caracteristica['nivel']) ?>
            <?php else: ?>
                <?= $fila($caracteristica['numero'], $caracteristica['nombre'] !== null
                    ? '<strong>' . Html::encode($caracteristica['nombre']) . ':</strong> ' . $texto($caracteristica['valor'])
                    : $texto($caracteristica['valor']), $caracteristica['nivel']) ?>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($garantia !== []): ?>
            <?= $seccion('II. GARANTÍA TÉCNICA') ?>
            <?php foreach ($garantia as $n => $linea): ?>
                <?= $fila('2.' . ($n + 1), $texto($linea)) ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?= $seccion(NumeracionEspecificacion::romano($numeroCondiciones) . '. CONDICIONES COMPLEMENTARIAS') ?>
        <?php if ($condiciones === []): ?>
            <?= $fila('', '') ?>
        <?php endif; ?>
        <?php foreach ($condiciones as $n => $condicion): ?>
            <?= $fila($numeroCondiciones . '.' . ($n + 1), $condicion) ?>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (!empty($emision->textoNotaFinal)): ?>
    <div class="nota-final"><?= $texto($emision->textoNotaFinal) ?></div>
<?php endif; ?>

<div class="dato" style="margin-top: 6px;">Fecha de emisión: <?= Html::encode($fechaLarga) ?> · Formulario válido de <?= (int) $emision->vigenciaDias ?> días desde su emisión.</div>

<?php // Control de elaboración: al fondo de la última página (PresupuestoPdfGenerator). ?>
<?= backend\components\PresupuestoPdfGenerator::MARCA_AL_PIE ?>

<?php // Copia de las firmas al emitir: las emitidas antes las tienen vacías y salen en blanco. ?>
<?= $this->render('//pdf/_control-elaboracion', [
    'version' => $numeroVersion,
    'elaborado' => $emision->firma(1),
    'aprobado' => $emision->firma(2),
    'fechaAprobacion' => $fecha->format('d/m/Y'),
]) ?>

</body>
</html>
