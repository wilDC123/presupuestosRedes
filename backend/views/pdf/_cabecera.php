<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Cabecera de pagina de los documentos impresos (presupuesto y solicitud de
 * cotizacion): recuadro con el escudo de la USFX, los titulos, una fila de
 * datos (numero, facultad, fecha, pagina) y el logo de la DTIC.
 *
 * Se usa con $this->render('//pdf/_cabecera', [...]) ("//" = desde la raiz
 * de backend/views, para que la compartan vistas de distintos controladores).
 *
 * <htmlpageheader> es una etiqueta propia de mPDF: lo que va adentro NO se
 * imprime donde esta, sino arriba de CADA pagina, y {PAGENO} / {nbpg} los
 * reemplaza mPDF por la pagina actual y el total. Para activarla, la vista
 * que la incluye pone en su CSS:
 *     @page { header: html_cabecera; margin-header: 5mm; margin-top: 32mm; }
 * (margin-header = distancia del borde de la hoja a la cabecera;
 * margin-top = donde empieza el contenido, debajo de la cabecera).
 *
 * Estilos en linea a proposito: la cabecera no depende del CSS de la vista.
 * Solo negro y blanco; el color lo ponen los logos.
 *
 * @var yii\web\View $this
 * @var string $titulo tercera linea (ej. "PRESUPUESTO DE INSTALACIÓN DE REDES")
 * @var string $numero ya con su etiqueta (ej. "N.° 15/26")
 * @var string|null $facultad
 * @var string $fecha dd/mm/aaaa
 * @var string|null $institucion primera linea (sale en mayusculas)
 * @var string|null $unidad segunda linea
 */

$institucion = $institucion ?? 'Universidad Mayor, Real y Pontificia de San Francisco Xavier de Chuquisaca';
$unidad = $unidad ?? 'Redes y Telecomunicaciones – D.T.I.C.';
$borde = 'border: 0.6mm solid #000;';
$celdaDato = $borde . ' font-size: 8pt; font-weight: bold; text-align: center; vertical-align: middle; height: 6mm; padding: 0 2px;';
$logos = Yii::getAlias('@backend/web/img');
?>
<htmlpageheader name="cabecera">
<table style="width: 100%; border-collapse: collapse; font-family: sans-serif; color: #000;">
    <tr>
        <td rowspan="2" style="<?= $borde ?> width: 18%; text-align: center; vertical-align: middle; padding: 1mm;">
            <img src="<?= Html::encode($logos . '/logo-usfx.png') ?>" style="height: 21mm;">
        </td>
        <td colspan="4" style="<?= $borde ?> width: 60%; text-align: center; vertical-align: middle; height: 17mm; padding: 1mm 2mm; font-weight: bold;">
            <div style="font-size: 7.5pt; text-transform: uppercase;"><?= Html::encode($institucion) ?></div>
            <div style="font-family: dejavusanscondensed; font-size: 11pt; margin-top: 1.5mm;"><?= Html::encode($unidad) ?></div>
            <div style="font-size: 8pt; margin-top: 1.5mm;"><?= Html::encode($titulo) ?></div>
        </td>
        <td rowspan="2" style="<?= $borde ?> width: 22%; text-align: center; vertical-align: middle; padding: 1mm;">
            <img src="<?= Html::encode($logos . '/logo-dtic.png') ?>" style="height: 14mm;">
        </td>
    </tr>
    <tr>
        <td style="<?= $celdaDato ?> width: 13%;"><?= Html::encode($numero) ?></td>
        <td style="<?= $celdaDato ?> width: 24%;"><?= Html::encode($facultad ?: '—') ?></td>
        <td style="<?= $celdaDato ?> width: 11%;"><?= Html::encode($fecha) ?></td>
        <td style="<?= $celdaDato ?> width: 12%; white-space: nowrap;">pág. {PAGENO} de {nbpg}</td>
    </tr>
</table>
</htmlpageheader>
