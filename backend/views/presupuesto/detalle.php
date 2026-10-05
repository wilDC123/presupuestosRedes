<?php

declare(strict_types=1);

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Pantalla principal de trabajo de un Presupuesto: lineas agrupadas por
 * categoria del item, con subtotal por grupo y total general; los items de
 * la lista del proyecto que faltan presupuestar; y el formulario
 * "Presupuestar item".
 *
 * El presupuesto nace de la lista de items: no se elige un item cualquiera
 * del subcatalogo, sino uno de la lista (boton "Presupuestar").
 *
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $presupuesto
 * @var backend\models\PresupuestoDetalle $nuevaLinea con su item de la lista ya cargado, o vacia
 * @var backend\models\ProyectoItem[] $pendientes items de la lista sin linea en el presupuesto
 * @var array<int, string> $cotizaciones cotizaciones del item que se esta presupuestando
 * @var array $grupos Ver PresupuestoService::agruparPorCategoria()
 * @var array $resumenPartidas Ver PresupuestoService::resumenPorPartida()
 */

$this->title = 'Líneas del presupuesto #' . $presupuesto->id;
$this->params['breadcrumbs'][] = ['label' => 'Presupuestos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => '#' . $presupuesto->id, 'url' => ['view', 'id' => $presupuesto->id]];
$this->params['breadcrumbs'][] = 'Líneas';

$totalGeneral = array_sum(ArrayHelper::getColumn($grupos, 'subtotal'));
?>
<div class="presupuesto-detalle">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        Proyecto: <strong><?= Html::encode($presupuesto->proyecto->nombre ?? '') ?></strong>
        &mdash;
        Estado: <span class="<?= $presupuesto->estadoBadgeClass() ?>"><?= Html::encode($presupuesto->estadoLabel()) ?></span>
        &mdash;
        <?= Html::a('Volver al presupuesto', ['view', 'id' => $presupuesto->id]) ?>
    </p>

    <?php if (empty($grupos)): ?>
        <p class="text-muted">Este presupuesto todavía no tiene líneas.</p>
    <?php else: ?>
        <?php foreach ($grupos as $nombreCategoria => $grupo): ?>
            <h3><?= Html::encode($nombreCategoria) ?></h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Ítem</th>
                        <th>Partida</th>
                        <th>Unidad</th>
                        <th>Cantidad</th>
                        <th>Precio unitario</th>
                        <th>Precio total</th>
                        <th>Origen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grupo['lineas'] as $linea): ?>
                        <tr>
                            <td>
                                <?= Html::encode($linea->subcatalogoItem->descripcion ?? '') ?>
                                <?php if (!empty($linea->observacion)): ?>
                                    <div class="small text-muted"><?= Html::encode($linea->observacion) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= Html::encode($linea->partida ?? '') ?></td>
                            <td><?= Html::encode($linea->unidadMedida ?? '') ?></td>
                            <td><?= Yii::$app->formatter->asDecimal($linea->cantidad, 2) ?></td>
                            <td><?= Yii::$app->formatter->asDecimal($linea->precioUnitario, 2) ?></td>
                            <td><?= Yii::$app->formatter->asDecimal($linea->precioTotal, 2) ?></td>
                            <td>
                                <?= $linea->idCotizacionDetalle === null
                                    ? '<span class="text-muted">Precio manual</span>'
                                    : Html::encode($linea->cotizacionDetalle->cotizacion->proveedor->razonSocial ?? '') ?>
                            </td>
                            <td>
                                <?php if ($presupuesto->puedeEditarse()): ?>
                                    <?= Html::beginForm(['eliminar-linea', 'id' => $linea->id], 'post') ?>
                                        <?= Html::submitButton('Quitar', [
                                            'class' => 'btn btn-danger btn-sm',
                                            'data' => ['confirm' => '¿Quitar esta línea del presupuesto?'],
                                        ]) ?>
                                    <?= Html::endForm() ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" style="text-align:right">Subtotal <?= Html::encode($nombreCategoria) ?>:</th>
                        <th><?= Yii::$app->formatter->asDecimal($grupo['subtotal'], 2) ?></th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        <?php endforeach; ?>

        <h2>Total general: <?= Yii::$app->formatter->asDecimal($totalGeneral, 2) ?></h2>

        <?= $this->render('_resumen-partidas', ['resumenPartidas' => $resumenPartidas]) ?>
    <?php endif; ?>

    <?php if ($presupuesto->puedeEditarse()): ?>
        <h2>Ítems de la lista sin presupuestar</h2>

        <?php if ($pendientes === []): ?>
            <p class="text-muted">
                No hay ítems pendientes. Los ítems se agregan en la
                <?= Html::a('lista de ítems del proyecto', ['/lista-items/index', 'id' => $presupuesto->idProyecto]) ?>.
            </p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Ítem</th>
                        <th>Clasificación</th>
                        <th>Unidad</th>
                        <th>Cantidad</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendientes as $pendiente): ?>
                        <tr>
                            <td>
                                <?= Html::encode($pendiente->subcatalogoItem->descripcion ?? '') ?>
                                <?php if (!empty($pendiente->observacion)): ?>
                                    <div class="small text-muted"><?= Html::encode($pendiente->observacion) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= Html::encode($pendiente->categoria->nombre ?? '') ?></td>
                            <td><?= Html::encode($pendiente->unidadMedida ?? '') ?></td>
                            <td><?= Yii::$app->formatter->asDecimal($pendiente->cantidad, 2) ?></td>
                            <td>
                                <?= Html::a('Presupuestar', ['detalle', 'id' => $presupuesto->id, 'idProyectoItem' => $pendiente->id, '#' => 'presupuestar'], [
                                    'class' => 'btn btn-sm ' . ((int) $nuevaLinea->idProyectoItem === (int) $pendiente->id ? 'btn-primary' : 'btn-outline'),
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (empty($nuevaLinea->idProyectoItem)): ?>
            <?php if ($nuevaLinea->hasErrors('idProyectoItem')): ?>
                <div class="alert alert-danger"><?= Html::encode($nuevaLinea->getFirstError('idProyectoItem')) ?></div>
            <?php endif; ?>
        <?php else: ?>
        <h2 id="presupuestar">Presupuestar: <?= Html::encode($nuevaLinea->subcatalogoItem->descripcion ?? '') ?></h2>

        <?php $form = ActiveForm::begin(['action' => ['detalle', 'id' => $presupuesto->id]]); ?>

        <?php // El item de la lista viaja oculto; el servidor verifica que sea de este proyecto. ?>
        <?= Html::activeHiddenInput($nuevaLinea, 'idProyectoItem') ?>
        <?= Html::error($nuevaLinea, 'idProyectoItem', ['class' => 'alert alert-danger']) ?>

        <?= $form->field($nuevaLinea, 'observacion')
            ->textInput(['maxlength' => 300])
            ->hint('Opcional. Detalle propio de esta línea: medidas ("12mm x 4 metros"), "según especificación técnica", etc. Sale junto al nombre del ítem en el PDF.') ?>

        <?= $form->field($nuevaLinea, 'idCotizacionDetalle')->dropDownList($cotizaciones, [
            'prompt' => '(ninguna — voy a ingresar el precio manualmente)',
        ])->hint('Si elige una cotización, el precio unitario se copia de ahí y ya no se puede cambiar después. Si no elige ninguna, complete el precio manualmente abajo.') ?>

        <?= $form->field($nuevaLinea, 'cantidad')->textInput(['type' => 'number', 'step' => '0.01']) ?>

        <?= $form->field($nuevaLinea, 'unidadMedida')->textInput(['maxlength' => 20])->hint('Se completa sola con la unidad del ítem si elige una cotización y la deja vacía.') ?>

        <?= $form->field($nuevaLinea, 'precioUnitario')->textInput(['type' => 'number', 'step' => '0.01'])->hint('Solo obligatorio si no eligió una cotización arriba.') ?>

        <div class="form-group">
            <?= Html::submitButton('Agregar línea', ['class' => 'btn btn-primary']) ?>
        </div>

        <?php ActiveForm::end(); ?>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-muted">Este presupuesto está "<?= Html::encode($presupuesto->estadoLabel()) ?>" — las líneas ya no se pueden agregar ni quitar. Una ampliación se registra como un proyecto nuevo de fase siguiente.</p>
    <?php endif; ?>

</div>
