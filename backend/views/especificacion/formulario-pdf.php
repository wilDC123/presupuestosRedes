<?php

declare(strict_types=1);

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
 * (condiciones). Al final, el "control de elaboracion" del lineamiento (8.12):
 * version, elaborado por, aprobado por, firmas y como fecha de aprobacion la
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

// Una fila de la tabla: la especificacion en la primera columna y las tres
// columnas que llenan el proveedor y el convocante, vacias.
$fila = static fn (string $contenido): string =>
    '<tr><td class="espec">' . $contenido . '</td><td></td><td></td><td></td></tr>';
$seccion = static fn (string $titulo): string =>
    '<tr><td class="seccion">' . Html::encode($titulo) . '</td><td class="seccion"></td><td class="seccion"></td><td class="seccion"></td></tr>';
$texto = static fn (?string $valor): string => nl2br(Html::encode((string) $valor));

// Las dos primeras lineas de la cabecera: la copia congelada del texto de
// cabecera de la emision ("Universidad..." / "D.T.I.C."); sin texto, las de siempre.
$lineasCabecera = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $emision->textoCabecera)), static fn (string $l) => $l !== ''));
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
    .instrucciones {
        text-align: justify;
        font-size: 10pt;
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
        font-size: 10pt;
    }
    table.especificaciones th {
        background-color: #d9d9d9;
        font-size: 9pt;
        text-align: center;
        vertical-align: middle;
    }
    td.espec {
        width: 55%;
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
    'proceso' => backend\models\Proceso::nombreDe($emision->proceso ?: backend\models\CorrelativoDocumento::PROCESO),
    'fecha' => $fecha->format('d/m/Y'),
    'institucion' => $lineasCabecera[0] ?? null,
    'unidad' => $lineasCabecera[1] ?? null,
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
            '<strong>Especifique marca y modelo a ofertar:</strong> El proveedor debe describir la marca y modelo del bien ofrecido.'
            . (!empty($version->modeloSugerido)
                ? '<br>Se sugiere: ' . Html::encode($version->modeloSugerido) . ' o similar/superior.'
                : '')
        ) ?>

        <?= $fila('<strong>URL:</strong> Enlace a la página web o folletería oficial del fabricante que contemple todas las especificaciones técnicas solicitadas (obligatorio).') ?>

        <?php foreach ($version->caracteristicas as $caracteristica): ?>
            <?= $fila($caracteristica->nombre !== null
                ? '<strong>' . Html::encode($caracteristica->nombre) . ':</strong> ' . $texto($caracteristica->valor)
                : $texto($caracteristica->valor)) ?>
        <?php endforeach; ?>

        <?php if (!empty($emision->garantia)): ?>
            <?= $seccion('II. GARANTÍA TÉCNICA') ?>
            <?= $fila($texto($emision->garantia)) ?>
        <?php endif; ?>

        <?= $seccion((!empty($emision->garantia) ? 'III' : 'II') . '. CONDICIONES COMPLEMENTARIAS') ?>
        <?= $fila(implode('<br>', array_filter([
            $emision->validezOfertaDias !== null ? '<strong>Validez de la oferta:</strong> ' . (int) $emision->validezOfertaDias . ' días como mínimo.' : null,
            $emision->tiempoEntregaDias !== null ? '<strong>Tiempo de entrega:</strong> ' . (int) $emision->tiempoEntregaDias . ' días.' : null,
            !empty($emision->lugarEntrega) ? '<strong>Lugar de entrega:</strong> ' . Html::encode($emision->lugarEntrega) . '.' : null,
        ]))) ?>
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
