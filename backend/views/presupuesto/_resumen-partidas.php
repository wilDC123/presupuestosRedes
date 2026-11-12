<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Tabla "Resumen por partida presupuestaria" de un presupuesto. Vista parcial
 * compartida por elaborar-presupuesto/index.php (y su respuesta AJAX).
 *
 * @var yii\web\View $this
 * @var array<int, array{partida: string, lineas: int, subtotal: float}> $resumenPartidas Ver PresupuestoService::resumenPorPartida()
 */
?>
<h2>Resumen por partida presupuestaria</h2>

<?php if ($resumenPartidas === []): ?>
    <p class="text-muted">Este presupuesto todavía no tiene líneas.</p>
<?php else: ?>
    <table class="table rd-max-520">
        <thead>
            <tr>
                <th>Partida</th>
                <th class="text-end">N.° de líneas</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resumenPartidas as $fila): ?>
                <tr>
                    <td><?= Html::encode($fila['partida']) ?></td>
                    <td class="text-end"><?= $fila['lineas'] ?></td>
                    <td class="text-end"><?= Yii::$app->formatter->asDecimal($fila['subtotal'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th>Total</th>
                <th class="text-end"><?= array_sum(array_column($resumenPartidas, 'lineas')) ?></th>
                <th class="text-end"><?= Yii::$app->formatter->asDecimal(array_sum(array_column($resumenPartidas, 'subtotal')), 2) ?></th>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>
