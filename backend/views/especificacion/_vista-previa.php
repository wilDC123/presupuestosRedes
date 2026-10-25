<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Fragmento (sin layout) con la version actual de una ficha, para la ventana
 * "Ver detalle" de la lista de items. Ver EspecificacionController::actionVistaPrevia().
 * Version reducida de view.php: solo lo que sirve para decidir si es la
 * ficha correcta.
 *
 * @var yii\web\View $this
 * @var backend\models\Especificacion $model
 * @var backend\models\EspecificacionVersion|null $version la version actual (null: ficha sin contenido todavia)
 * @var string|null $garantiaPorDefecto Ver ConfiguracionEspecificacion
 */

$urlAdjunto = $version?->urlAdjunto();
?>
<div class="esp-vista-previa">
    <div class="rd-celda-titulo"><?= Html::encode($model->nombre()) ?></div>
    <div class="rd-celda-sub mb-2">
        <?= Html::encode($model->categoria->nombre ?? 'Sin categoría') ?>
        <?= $version !== null ? ' · ' . Html::encode($version->etiqueta()) . ' (actual)' : '' ?>
        <?= $model->activo ? '' : ' · <span class="badge b-inactivo">Inactiva</span>' ?>
    </div>

    <?php if ($version === null): ?>
        <div class="rd-vacio">Esta ficha todavía no tiene contenido técnico.</div>
    <?php else: ?>
        <table class="table mb-2">
            <tbody>
                <tr>
                    <th style="width: 30%">Modelo sugerido</th>
                    <td><?= Html::encode($version->modeloSugerido ?: '—') ?></td>
                </tr>
                <tr>
                    <th>Garantía</th>
                    <td>
                        <?= $version->garantia !== null
                            ? Html::encode($version->garantia)
                            : '<span class="rd-nota">Por defecto: ' . Html::encode($garantiaPorDefecto ?: '—') . '</span>' ?>
                    </td>
                </tr>
                <?php if ($urlAdjunto !== null): ?>
                    <tr>
                        <th>Documento adjunto</th>
                        <td><?= Html::a('Ver documento', $urlAdjunto, ['target' => '_blank', 'rel' => 'noopener']) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php $caracteristicas = $version->caracteristicas; ?>
        <?php if ($caracteristicas === []): ?>
            <div class="rd-vacio">Sin características cargadas.</div>
        <?php else: ?>
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th style="width: 30%">Característica</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($caracteristicas as $caracteristica): ?>
                        <tr>
                            <?php if ($caracteristica->nombre === null): ?>
                                <td colspan="2"><?= nl2br(Html::encode($caracteristica->valor)) ?></td>
                            <?php else: ?>
                                <td class="rd-celda-titulo"><?= Html::encode($caracteristica->nombre) ?></td>
                                <td><?= nl2br(Html::encode($caracteristica->valor)) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <div class="mt-2">
        <?= Html::a('Abrir la ficha completa en otra pestaña', ['view', 'id' => $model->id], ['target' => '_blank', 'rel' => 'noopener']) ?>
    </div>
</div>
