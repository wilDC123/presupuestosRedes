<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * "Antes de enviar": items sin precio (bloquean el envio) y lineas con
 * cotizacion vencida (solo aviso). La pantalla la vuelve a pedir por AJAX
 * despues de cada precio guardado.
 *
 * @var yii\web\View $this
 * @var array{sinPrecio: string[], vencidas: int} $control Ver ElaborarPresupuestoService::control()
 * @var backend\models\Presupuesto $presupuesto
 */
?>
<div class="rd-card-titulo mb-2">Antes de enviar a revisión</div>

<?php if ($control['sinPrecio'] === []): ?>
    <div class="rd-dato text-success">✓ Todos los ítems tienen precio.</div>
<?php else: ?>
    <div class="rd-dato text-danger">
        ✗ <?= count($control['sinPrecio']) ?> ítem(s) sin precio. Escriba un precio manual:
    </div>
    <ul class="rd-nota mb-2">
        <?php foreach ($control['sinPrecio'] as $nombre): ?>
            <li><?= Html::encode($nombre) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($control['vencidas'] > 0): ?>
    <div class="rd-dato text-warning">
        ⚠ <?= $control['vencidas'] ?> línea(s) con precio de una cotización vencida (no bloquea el envío).
    </div>
<?php else: ?>
    <div class="rd-dato text-success">✓ Ninguna cotización vencida.</div>
<?php endif; ?>
