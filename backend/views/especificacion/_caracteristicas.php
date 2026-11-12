<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Tabla de caracteristicas de una version CON la numeracion del formulario de
 * especificacion (1.3, 1.4, 1.4.1...), para la ficha de la libreria (view.php)
 * y la ventana "Ver detalle" (_vista-previa.php). Los numeros salen de
 * EspecificacionVersion::caracteristicasNumeradas(), lo mismo que usa el PDF:
 * lo que se ve aqui es lo que se imprime.
 *
 * @var yii\web\View $this
 * @var backend\models\EspecificacionVersion $version
 * @var string $clase clases de la tabla
 */

$filas = $version->caracteristicasNumeradas();
?>
<?php if ($filas === []): ?>
    <div class="rd-vacio">Sin características cargadas.</div>
<?php else: ?>
    <table class="<?= Html::encode($clase) ?>">
        <thead>
            <tr>
                <th class="rd-ancho-numero">N.°</th>
                <th class="rd-ancho-30">Característica</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="mono esp-numero">1.1</td>
                <td colspan="2" class="rd-nota">Especifique marca y modelo a ofertar (la agrega el formulario)</td>
            </tr>
            <tr>
                <td class="mono esp-numero">1.2</td>
                <td colspan="2" class="rd-nota">URL del fabricante (la agrega el formulario)</td>
            </tr>
            <?php foreach ($filas as $fila): ?>
                <?php $debajo = $fila['nivel'] > 1 ? ' esp-nivel-' . $fila['nivel'] : ''; ?>
                <tr class="<?= $fila['esSubtitulo'] ? 'esp-subtitulo-fila' : '' ?>">
                    <td class="mono esp-numero"><?= Html::encode($fila['numero']) ?></td>
                    <?php if ($fila['nombre'] === null): ?>
                        <td colspan="2" class="<?= trim($debajo) ?>"><?= nl2br(Html::encode($fila['valor'])) ?></td>
                    <?php else: ?>
                        <td class="rd-celda-titulo<?= $debajo ?>"><?= Html::encode($fila['nombre']) ?></td>
                        <td><?= nl2br(Html::encode($fila['valor'])) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
