<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Cabecera de pagina de TODOS los documentos impresos (lineamiento de la
 * DTIC RD-GD-LI-001, seccion 8.8.2), copiada de los formatos corregidos v3
 * del 2026-11-09 ("FORMATOS CORREGIDOS v3 - proyecto 001 con 2 fases y orden
 * de cambio"): una tabla de 5 columnas, como la del Word:
 *
 *   +-------------+---------------------------------------+---------------+
 *   | logo DTIC   | PROCESO: <NOMBRE DEL PROCESO>         | logo RedUSFX  |
 *   | DIVISION DE |            <TITULO>                   | Redes y Tele- |
 *   | TECNOLOGIAS +-------------+------------+------------+---------------+
 *   | ...         | Código: ... | Versión: … | Fecha: ... | Página: X de Y|
 *   +-------------+-------------+------------+------------+---------------+
 *
 * Anchos de columna = los del Word (2137, 2766, 1386, 2319 y 2047 twips, en
 * %). Letra Carlito (las medidas de Calibri, la del Word): textos del logo
 * en 6 pt, proceso 10 pt, titulo 12 pt, fila de datos 10 pt. La fecha se
 * escribe dd-mm-aaaa, como en el Word (las vistas la pasan con "/").
 *
 * Se usa con $this->render('//pdf/_cabecera', [...]) ("//" = desde la raiz
 * de backend/views, para que la compartan vistas de distintos controladores).
 *
 * <htmlpageheader> es una etiqueta propia de mPDF: lo que va adentro NO se
 * imprime donde esta, sino arriba de CADA pagina, y {PAGENO} / {nbpg} los
 * reemplaza mPDF por la pagina actual y el total. Para activarla, la vista
 * que la incluye pone en su CSS:
 *     @page { header: html_cabecera; margin-header: 5mm; margin-top: 37mm; }
 * (margin-header = distancia del borde de la hoja a la cabecera;
 * margin-top = donde empieza el contenido, debajo de la cabecera; 37 mm =
 * el margen superior del Word).
 *
 * Estilos en linea a proposito: la cabecera no depende del CSS de la vista.
 * Los logos: backend/web/img/logo-dtic-usfx.png y logo-redusfx.png, sacados
 * de los mismos Word.
 *
 * @var yii\web\View $this
 * @var string $titulo titulo del documento (ej. "PRESUPUESTO DE INSTALACIÓN DE REDES")
 * @var string $codigo codigo del lineamiento (ej. "RD-PI-PR-015")
 * @var string|null $version version del documento (ej. "1.0", VersionDocumento::texto())
 * @var string|null $proceso nombre del proceso del documento (Proceso::nombreDe()); sale en mayusculas
 * @var string $fecha dd/mm/aaaa (o dd-mm-aaaa)
 */

$borde = 'border: 0.2mm solid #000;';
$celdaDato = $borde . ' font-size: 10pt; text-align: center; vertical-align: middle; height: 6mm; padding: 0 1mm;';
$logos = Yii::getAlias('@backend/web/img');
$proceso = trim((string) ($proceso ?? ''));
?>
<htmlpageheader name="cabecera">
<table style="width: 100%; border-collapse: collapse; font-family: carlito; color: #000;">
    <tr>
        <td rowspan="2" style="<?= $borde ?> width: 20.1%; text-align: center; vertical-align: middle; padding: 1mm;">
            <img src="<?= Html::encode($logos . '/logo-dtic-usfx.png') ?>" style="width: 18.5mm;">
            <div style="font-size: 6pt; margin-top: 0.5mm;">DIVISION DE TECNOLOGIAS DE<br>INFORMACION Y COMUNICACION<br><span style="font-size: 9pt;">U.M.R.P.S.F.X.CH</span>.</div>
        </td>
        <td colspan="3" style="<?= $borde ?> width: 60.7%; text-align: center; vertical-align: middle; height: 19.7mm; padding: 1mm 2mm;">
            <?php if ($proceso !== ''): ?>
                <div style="font-size: 10pt; font-weight: bold;">PROCESO: <?= Html::encode(mb_strtoupper($proceso, 'UTF-8')) ?></div>
            <?php endif; ?>
            <div style="font-size: 12pt; font-weight: bold; margin-top: 1mm;"><?= Html::encode($titulo) ?></div>
        </td>
        <td style="<?= $borde ?> width: 19.2%; text-align: center; vertical-align: middle; padding: 1mm;">
            <img src="<?= Html::encode($logos . '/logo-redusfx.png') ?>" style="width: 26.4mm;">
            <div style="font-size: 9pt; margin-top: 1mm;">Redes y Telecomunicaciones</div>
        </td>
    </tr>
    <tr>
        <td style="<?= $celdaDato ?> width: 26%;">Código: <?= Html::encode($codigo) ?></td>
        <td style="<?= $celdaDato ?> width: 13%;">Versión: <?= Html::encode($version ?? '1.0') ?></td>
        <td style="<?= $celdaDato ?> width: 21.7%;">Fecha: <?= Html::encode(str_replace('/', '-', $fecha)) ?></td>
        <td style="<?= $celdaDato ?> width: 19.2%;">Página: {PAGENO} de {nbpg}</td>
    </tr>
</table>
</htmlpageheader>
