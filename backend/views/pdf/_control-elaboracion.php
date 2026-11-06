<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * "Control de elaboracion" del lineamiento de la DTIC RD-GD-LI-001 (8.12):
 * version, elaborado por, aprobado por, firmas y fecha de aprobacion. Lo
 * comparten el PDF del presupuesto y el formulario de especificacion tecnica
 * ($this->render('//pdf/_control-elaboracion', [...])).
 *
 * Sin cuadricula (decidido el 2026-10-22): el 8.12 pide el contenido, no una
 * tabla, y en los registros llenados (nivel D, anexos de RD-DS-FR-002) la
 * DTIC usa firmas simples. Dos firmas lado a lado (raya, nombre, cargo y
 * "ELABORADO POR" / "APROBADO POR") y debajo una linea con la version y la
 * fecha de aprobacion. Una firma sin nombre deja la raya y su rotulo, para
 * llenarla a mano.
 *
 * La <table> solo ordena las columnas: la raya es el borde inferior de la
 * celda (mPDF no dibuja bien el borde de un div dentro de una tabla).
 * Estilos en linea para que salga igual en los dos documentos;
 * page-break-inside: mPDF no la corta entre dos paginas.
 *
 * @var yii\web\View $this
 * @var string $version '1.0' (VersionDocumento::texto())
 * @var array{prefijo: string, nombre: string, cargo: string}|null $elaborado null = en blanco, para llenarla a mano
 * @var array{prefijo: string, nombre: string, cargo: string}|null $aprobado
 * @var string $fechaAprobacion 'dd/mm/aaaa', o vacia (se deja la raya)
 * @var bool $soloElaborado true = documento que no se aprueba (la lista de materiales, revision del
 *           2026-11-05): una sola firma, "ELABORADO POR", centrada, y debajo solo la version
 */

$soloElaborado = $soloElaborado ?? false;
$firmas = $soloElaborado ? [['ELABORADO POR', $elaborado]] : [['ELABORADO POR', $elaborado], ['APROBADO POR', $aprobado]];
$centro = 'text-align: center; vertical-align: top; font-size: 10pt;';
// Con una sola firma, las columnas de los costados solo la centran.
$anchos = $soloElaborado ? ['29%', '42%', '29%'] : ['42%', '16%', '42%'];
$conRaya = $soloElaborado ? [false, true, false] : [true, false, true];
?>
<table style="width: 100%; border-collapse: collapse; margin-top: 20px; page-break-inside: avoid;">
    <tr>
        <?php // Espacio para firmar y la raya debajo; las columnas sin raya solo separan. ?>
        <?php foreach ($anchos as $k => $ancho): ?>
            <td width="<?= $ancho ?>"<?= $conRaya[$k] ? ' style="height: 55px; border-bottom: 1px solid #000;"' : '' ?>></td>
        <?php endforeach; ?>
    </tr>
    <tr>
        <?php if ($soloElaborado): ?><td></td><?php endif; ?>
        <?php foreach ($firmas as $i => [$rotulo, $firma]): ?>
            <?php if ($i > 0): ?><td></td><?php endif; ?>
            <td style="<?= $centro ?> padding-top: 3px;">
                <?php if ($firma !== null): ?>
                    <div><?= Html::encode(trim($firma['prefijo'] . ' ' . $firma['nombre'])) ?></div>
                    <?php if ($firma['cargo'] !== ''): ?>
                        <div style="font-weight: bold;"><?= Html::encode($firma['cargo']) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <div style="font-size: 9pt; margin-top: 2px;"><?= $rotulo ?></div>
            </td>
        <?php endforeach; ?>
        <?php if ($soloElaborado): ?><td></td><?php endif; ?>
    </tr>
    <tr>
        <td colspan="3" style="text-align: center; font-size: 9pt; padding-top: 8px;">
            <?php if ($soloElaborado): ?>
                Versión: <?= Html::encode($version) ?>
            <?php else: ?>
                Versión: <?= Html::encode($version) ?> · Fecha de aprobación: <?= $fechaAprobacion !== '' ? Html::encode($fechaAprobacion) : '____/____/________' ?>
            <?php endif; ?>
        </td>
    </tr>
</table>
