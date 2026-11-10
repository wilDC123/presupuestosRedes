<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Franja "FASES" de la vista del proyecto (2026-11-10), justo encima de la
 * barra de pasos: una pastilla por fase con el estado y el total de su
 * presupuesto, sus ordenes de cambio despues (separadas por "›") y, al final,
 * "Agregar fase N →". Asi las fases se ven arriba, sin bajar hasta el
 * recuadro "Fases del proyecto". Solo se muestra con varias fases o con
 * ordenes de cambio (ProyectoService::franjaFases()).
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $model el que se esta viendo
 * @var array<int, array<int, array{id: int, texto: string, presupuesto: ?backend\models\Presupuesto, monto: ?float, actual: bool}>> $grupos
 * @var bool $puedeAgregarFase
 */

$formato = Yii::$app->formatter;
?>
<nav class="rd-franja-fases" aria-label="Fases del proyecto">
    <span class="kicker">Fases</span>
    <?php foreach ($grupos as $grupo): ?>
        <span class="rd-franja-grupo">
            <?php foreach ($grupo as $i => $fila): ?>
                <?php if ($i > 0): ?>
                    <span class="rd-franja-sep" aria-hidden="true">›</span>
                <?php endif; ?>
                <?php
                $presupuesto = $fila['presupuesto'];
                $contenido = Html::encode($fila['texto'])
                    . ($presupuesto === null
                        ? ' <span class="badge b-inactivo">Sin presupuesto</span>'
                        : ' <span class="badge ' . Html::encode($presupuesto->estadoBadgeClass()) . '">' . Html::encode($presupuesto->estadoLabel()) . '</span>'
                            . ' <span class="mono">Bs. ' . $formato->asDecimal($fila['monto'] ?? 0, 2) . '</span>');
                ?>
                <?php if ($fila['actual']): ?>
                    <span class="rd-franja-pastilla es-actual" aria-current="page"><?= $contenido ?> <span class="rd-franja-viendo">· está viendo</span></span>
                <?php else: ?>
                    <?= Html::a($contenido, ['view', 'id' => $fila['id']], ['class' => 'rd-franja-pastilla']) ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </span>
    <?php endforeach; ?>
    <?php if ($puedeAgregarFase): ?>
        <?= Html::a('Agregar fase ' . ((int) $model->numeroFase + 1) . ' →', ['create', 'idAnterior' => $model->id], [
            'class' => 'rd-franja-pastilla rd-franja-agregar',
            'title' => 'La fase siguiente del mismo proyecto (mismo número), con su propia área, equipo y carpeta económica',
        ]) ?>
    <?php endif; ?>
</nav>
