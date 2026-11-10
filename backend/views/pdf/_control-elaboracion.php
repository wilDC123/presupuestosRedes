<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * "Control de elaboracion" del lineamiento de la DTIC RD-GD-LI-001 (8.12):
 * version, elaborado por, aprobado por, firmas y fecha de aprobacion. Lo
 * comparten el presupuesto, el formulario de especificacion tecnica y la lista
 * de materiales ($this->render('//pdf/_control-elaboracion', [...])).
 *
 * EN TABLA desde el 2026-11-09 (decidido por el desarrollador, como los Word
 * de FORMATOS CORREGIDOS v3; del 2026-10-22 a esa fecha iba sin cuadricula):
 *
 *   |          | ELABORADO POR     | APROBADO POR          |   <- gris #e0e0e0
 *   | Firma:   | (espacio, 2 cm)   |                       |
 *   | Nombre:  | Ing. ...          | V°B° Ing. ...         |
 *   | Cargo:   | RESP. ...         | RESP. ...             |
 *   | Version: 1.0 · Fecha de aprobacion: 23-10-2026      |   <- gris #f2f2f2
 *
 * Anchos del Word: 1268 | 4004 | 5272 twips. Un documento que no se aprueba
 * (la lista de materiales, $soloElaborado) lleva solo la columna "ELABORADO
 * POR" y en la ultima fila solo la version; con UNA sola firma la tabla ocupa
 * TODO el ancho de la hoja (indicacion del Ing. de DTIC, 2026-11-09): rotulos
 * 12%, la firma el resto. Una
 * firma sin nombre deja las casillas en blanco, para llenarla a mano.
 *
 * Estilos en linea para que salga igual en los tres documentos;
 * page-break-inside: mPDF no la corta entre dos paginas.
 *
 * @var yii\web\View $this
 * @var string $version '1.0' (VersionDocumento::texto())
 * @var array{prefijo: string, nombre: string, cargo: string}|null $elaborado null = en blanco, para llenarla a mano
 * @var array{prefijo: string, nombre: string, cargo: string}|null $aprobado
 * @var string $fechaAprobacion 'dd/mm/aaaa', o vacia (se deja la raya); sale dd-mm-aaaa, como el Word
 * @var bool $soloElaborado true = documento que no se aprueba (la lista de materiales, revision del
 *           2026-11-05): una sola columna, "ELABORADO POR", y abajo solo la version
 * @var bool|null $letraGrande true = todo en 11 pt (el formulario de especificacion: nada por debajo de 11,
 *           revision del 2026-11-05); si no, como el Word del presupuesto (9 y 10 pt)
 */

$soloElaborado = $soloElaborado ?? false;
$letraGrande = $letraGrande ?? false;
$firmas = $soloElaborado ? ['ELABORADO POR' => $elaborado] : ['ELABORADO POR' => $elaborado, 'APROBADO POR' => $aprobado];
$columnas = 1 + count($firmas);

$chica = $letraGrande ? '11pt' : '9pt';
$mediana = $letraGrande ? '11pt' : '10pt';
$borde = 'border: 1px solid #000; padding: 2px 5px;';
$rotulo = $borde . " font-size: $chica; font-weight: bold; vertical-align: middle;";
$celda = $borde . " font-size: $mediana; vertical-align: middle;";
// Anchos del Word; con una sola firma, todo el ancho (la columna de rotulos igual).
$anchos = $soloElaborado ? ['12%', '88%'] : ['12%', '38%', '50%'];
$valor = static fn (?array $firma, string $clave): string => $firma === null ? '' : Html::encode(trim($clave === 'nombre' ? $firma['prefijo'] . ' ' . $firma['nombre'] : $firma[$clave]));
$fecha = $fechaAprobacion !== '' ? str_replace('/', '-', $fechaAprobacion) : '____-____-________';
?>
<table style="width: 100%; border-collapse: collapse; margin-top: 20px; page-break-inside: avoid;">
    <tr>
        <td style="<?= $borde ?> width: <?= $anchos[0] ?>; background-color: #e0e0e0;"></td>
        <?php foreach (array_keys($firmas) as $k => $titulo): ?>
            <td style="<?= $borde ?> width: <?= $anchos[$k + 1] ?>; background-color: #e0e0e0; text-align: center; font-weight: bold; font-size: <?= $letraGrande ? '11pt' : '9.5pt' ?>;"><?= $titulo ?></td>
        <?php endforeach; ?>
    </tr>
    <tr>
        <td style="<?= $rotulo ?> height: 20mm;">Firma:</td>
        <?php foreach ($firmas as $firma): ?>
            <td style="<?= $celda ?>"></td>
        <?php endforeach; ?>
    </tr>
    <tr>
        <td style="<?= $rotulo ?>">Nombre:</td>
        <?php foreach ($firmas as $firma): ?>
            <td style="<?= $celda ?>"><?= $valor($firma, 'nombre') ?></td>
        <?php endforeach; ?>
    </tr>
    <tr>
        <td style="<?= $rotulo ?>">Cargo:</td>
        <?php foreach ($firmas as $firma): ?>
            <td style="<?= $celda ?> font-size: <?= $chica ?>; font-weight: bold;"><?= $valor($firma, 'cargo') ?></td>
        <?php endforeach; ?>
    </tr>
    <tr>
        <td colspan="<?= $columnas ?>" style="<?= $borde ?> background-color: #f2f2f2; text-align: center; font-size: <?= $chica ?>;">
            <?php if ($soloElaborado): ?>
                Versión: <?= Html::encode($version) ?>
            <?php else: ?>
                Versión: <?= Html::encode($version) ?> · Fecha de aprobación: <?= Html::encode($fecha) ?>
            <?php endif; ?>
        </td>
    </tr>
</table>
