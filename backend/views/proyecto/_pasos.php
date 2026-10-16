<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Barra de pasos del proyecto (en que paso va y a donde lleva cada uno).
 * Vista parcial compartida por proyecto/view.php y elaborar-presupuesto/index.php.
 *
 * @var yii\web\View $this
 * @var array<int, array{titulo: string, estado: string, url: array|null}> $pasos Ver ProyectoService::pasos()
 * @var string|null $actual titulo del paso de la pantalla actual (se resalta)
 */

$actual ??= null;
?>
<div class="rd-pasos">
    <?php foreach ($pasos as $numero => $paso): ?>
        <?php
        $contenido = '<span class="rd-paso-num">' . ($paso['estado'] === 'hecho' ? '✓' : $numero + 1) . '</span>'
            . '<span class="rd-paso-titulo">' . Html::encode($paso['titulo']) . '</span>';
        $clase = 'rd-paso rd-paso-' . ($paso['titulo'] === $actual ? 'actual' : $paso['estado']);
        ?>
        <?= $paso['url'] === null
            ? Html::tag('span', $contenido, ['class' => $clase . ' rd-paso-sin-pantalla', 'title' => 'Esta pantalla todavía no está disponible'])
            : Html::a($contenido, $paso['url'], ['class' => $clase]) ?>
    <?php endforeach; ?>
</div>
