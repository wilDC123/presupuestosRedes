<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * HTML fuente del FORMULARIO NET (el documento que se firma, se sella y se
 * entrega al proveedor). Se renderiza con renderPartial() y se le pasa a mPDF
 * -- ver EspecificacionController::actionFormulario().
 *
 * Sigue la estructura de los formularios del banco de especificaciones de
 * DTIC: cabecera, instrucciones, codigo NET, titulo, cantidad y dependencia, y
 * la tabla "Especificaciones tecnicas | Propuesta | Cumple | No cumple" con
 * las secciones I (requisitos), II (garantia) y III (condiciones).
 *
 * TODO lo que se imprime sale de la EMISION (sus copias congeladas) y de su
 * VERSION (que ya no se modifica): reimprimir un NET viejo da siempre el
 * mismo documento, aunque despues cambie la ficha o la configuracion.
 *
 * CSS deliberadamente simple, igual que los otros PDF: mPDF interpreta un
 * subconjunto de CSS pensado para documentos.
 *
 * @var yii\web\View $this
 * @var backend\models\EspecificacionEmision $emision
 * @var string|null $logo ruta del logo en el disco (null = sin logo)
 */

$version = $emision->version;
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
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body {
        font-family: sans-serif;
        font-size: 10px;
        color: #000;
    }
    table.cabecera {
        width: 100%;
        border-collapse: collapse;
    }
    table.cabecera td {
        vertical-align: middle;
    }
    .institucion {
        text-align: center;
        font-size: 11px;
        font-weight: bold;
    }
    .titulo-formulario {
        text-align: center;
        font-size: 13px;
        font-weight: bold;
        margin: 8px 0 6px 0;
    }
    .instrucciones {
        text-align: justify;
        font-size: 9.5px;
        margin-bottom: 8px;
    }
    .net {
        text-align: right;
        margin-bottom: 4px;
    }
    .net span {
        border: 1px solid #000;
        padding: 3px 8px;
        font-size: 12px;
        font-weight: bold;
    }
    .bien {
        font-size: 12px;
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
    table.especificaciones th {
        background-color: #d9d9d9;
        font-size: 9.5px;
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
    /* Va al fondo de la pagina (MARCA_AL_PIE); el margen solo separa del texto
       cuando queda justo. */
    .firma {
        margin-top: 10px;
    }
</style>
</head>
<body>

<table class="cabecera">
    <tr>
        <?php if ($logo !== null): ?>
            <td style="width: 18%;"><img src="<?= Html::encode($logo) ?>" style="height: 55px;"></td>
        <?php endif; ?>
        <td class="institucion"><?= $texto($emision->textoCabecera) ?></td>
        <?php if ($logo !== null): ?>
            <td style="width: 18%;"></td>
        <?php endif; ?>
    </tr>
</table>

<div class="titulo-formulario">ESPECIFICACIONES TÉCNICAS DE LOS BIENES OFRECIDOS</div>

<?php if (!empty($emision->textoInstrucciones)): ?>
    <div class="instrucciones"><?= $texto($emision->textoInstrucciones) ?></div>
<?php endif; ?>

<div class="net"><span><?= Html::encode($emision->codigoNet()) ?></span></div>

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

<?php // "Elaborado por" y firma: al fondo de la última página (PresupuestoPdfGenerator). ?>
<?= backend\components\PresupuestoPdfGenerator::MARCA_AL_PIE ?>

<table class="cabecera firma">
    <tr>
        <?php // En blanco, para escribir el nombre a mano. $emision->elaboradoPor (el
        // usuario del sistema que emitio) solo se ve en el registro de formularios NET. ?>
        <td style="width: 60%;">Formulario elaborado por: ______________________________</td>
        <td>Firma: ______________________________</td>
    </tr>
</table>

</body>
</html>
