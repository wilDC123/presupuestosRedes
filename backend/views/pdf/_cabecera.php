<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Cabecera de pagina de los documentos impresos: recuadro con el escudo de
 * la USFX, los titulos, una fila de datos y el logo de la DTIC.
 *
 * Dos formas, segun lo que se le pase:
 *  - con $codigo: la del lineamiento de la DTIC RD-GD-LI-001 (seccion
 *    8.8.2), para los documentos codificados (presupuesto, especificacion
 *    tecnica): proceso + titulo, y la fila Codigo | Version | Fecha | Pagina,
 *  - sin $codigo: la de la solicitud de cotizacion (que no es un documento
 *    del lineamiento): fila N.° | facultad | fecha | pagina.
 *
 * Se usa con $this->render('//pdf/_cabecera', [...]) ("//" = desde la raiz
 * de backend/views, para que la compartan vistas de distintos controladores).
 *
 * <htmlpageheader> es una etiqueta propia de mPDF: lo que va adentro NO se
 * imprime donde esta, sino arriba de CADA pagina, y {PAGENO} / {nbpg} los
 * reemplaza mPDF por la pagina actual y el total. Para activarla, la vista
 * que la incluye pone en su CSS:
 *     @page { header: html_cabecera; margin-header: 5mm; margin-top: 36mm; }
 * (margin-header = distancia del borde de la hoja a la cabecera;
 * margin-top = donde empieza el contenido, debajo de la cabecera).
 *
 * Estilos en linea a proposito: la cabecera no depende del CSS de la vista.
 * Solo negro y blanco; el color lo ponen los logos. Fuente Carlito (las
 * medidas de Calibri, que pide el lineamiento; ver backend/fonts/carlito).
 *
 * @var yii\web\View $this
 * @var string $titulo titulo del documento (ej. "PRESUPUESTO DE INSTALACIÓN DE REDES")
 * @var string $fecha dd/mm/aaaa
 * @var string|null $codigo codigo del lineamiento (ej. "RD-PI-PR-015"); activa la cabecera del lineamiento
 * @var string|null $version version del documento (ej. "01")
 * @var string|null $proceso proceso al que pertenece el documento
 * @var string|null $numero sin $codigo: ya con su etiqueta (ej. "N.° RD-PI-PT-004 / SC-01")
 * @var string|null $facultad sin $codigo: la unidad solicitante del proyecto
 * @var string|null $institucion primera linea (sale en mayusculas)
 * @var string|null $unidad segunda linea
 */

$lineamiento = isset($codigo);
$institucion = $institucion ?? 'Universidad Mayor, Real y Pontificia de San Francisco Xavier de Chuquisaca';
$unidad = $unidad ?? 'Redes y Telecomunicaciones – D.T.I.C.';
$borde = 'border: 0.6mm solid #000;';
$celdaDato = $borde . ' font-size: 9pt; text-align: center; vertical-align: middle; height: 6mm; padding: 0 2px;';
$logos = Yii::getAlias('@backend/web/img');

// Fila de datos: [texto, ancho %]. Los cuatro anchos suman el 60% de la
// columna del centro (los logos ocupan el resto, con rowspan).
$datos = $lineamiento
    ? [
        ['<strong>Código:</strong> ' . Html::encode($codigo), 19],
        ['<strong>Versión:</strong> ' . Html::encode($version ?? '01'), 11],
        ['<strong>Fecha:</strong> ' . Html::encode($fecha), 15],
        ['<strong>Página:</strong> {PAGENO} de {nbpg}', 15],
    ]
    : [
        ['<strong>' . Html::encode($numero ?? '') . '</strong>', 13],
        ['<strong>' . Html::encode(($facultad ?? '') ?: '—') . '</strong>', 24],
        ['<strong>' . Html::encode($fecha) . '</strong>', 11],
        ['<strong>pág. {PAGENO} de {nbpg}</strong>', 12],
    ];
?>
<htmlpageheader name="cabecera">
<table style="width: 100%; border-collapse: collapse; font-family: carlito; color: #000;">
    <tr>
        <td rowspan="2" style="<?= $borde ?> width: 18%; text-align: center; vertical-align: middle; padding: 1mm;">
            <img src="<?= Html::encode($logos . '/logo-usfx.png') ?>" style="height: 21mm;">
        </td>
        <td colspan="4" style="<?= $borde ?> width: 60%; text-align: center; vertical-align: middle; height: 17mm; padding: 1mm 2mm;">
            <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase;"><?= Html::encode($institucion) ?></div>
            <div style="font-size: 11pt; font-weight: bold; margin-top: 1mm;"><?= Html::encode($unidad) ?></div>
            <?php if ($lineamiento && !empty($proceso)): ?>
                <div style="font-size: 8pt; margin-top: 1mm;">Proceso: <?= Html::encode($proceso) ?></div>
            <?php endif; ?>
            <div style="font-size: <?= $lineamiento ? '12pt' : '9pt' ?>; font-weight: bold; margin-top: 1mm;"><?= Html::encode($titulo) ?></div>
        </td>
        <td rowspan="2" style="<?= $borde ?> width: 22%; text-align: center; vertical-align: middle; padding: 1mm;">
            <img src="<?= Html::encode($logos . '/logo-dtic.png') ?>" style="height: 14mm;">
        </td>
    </tr>
    <tr>
        <?php foreach ($datos as [$texto, $ancho]): ?>
            <td style="<?= $celdaDato ?> width: <?= $ancho ?>%; white-space: nowrap;"><?= $texto ?></td>
        <?php endforeach; ?>
    </tr>
</table>
</htmlpageheader>
