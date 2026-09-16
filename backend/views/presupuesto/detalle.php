<?php

declare(strict_types=1);

use backend\models\CotizacionDetalle;
use backend\models\SubcatalogoItem;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Pantalla principal de trabajo de un Presupuesto: lineas agrupadas por
 * categoria del item, con subtotal por grupo y total general, mas el
 * formulario para agregar una linea nueva.
 *
 * @var yii\web\View $this
 * @var backend\models\Presupuesto $presupuesto
 * @var backend\models\PresupuestoDetalle $nuevaLinea
 * @var array $grupos Ver PresupuestoController::agruparPorCategoria()
 */

$this->title = 'Líneas del presupuesto #' . $presupuesto->id;
$this->params['breadcrumbs'][] = ['label' => 'Presupuestos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => '#' . $presupuesto->id, 'url' => ['view', 'id' => $presupuesto->id]];
$this->params['breadcrumbs'][] = 'Líneas';

$totalGeneral = array_sum(ArrayHelper::getColumn($grupos, 'subtotal'));

$items = ArrayHelper::map(
    SubcatalogoItem::find()->with('categoria')->orderBy(['descripcion' => SORT_ASC])->all(),
    'id',
    'descripcion',
    static fn ($item) => $item->categoria->nombre ?? 'Sin categoría'
);

// Cada opcion muestra item + proveedor + precio, para que se pueda "elegir"
// la cotizacion sin tener que abrir cada una por separado.
$cotizaciones = ArrayHelper::map(
    CotizacionDetalle::find()->with(['subcatalogoItem', 'cotizacion.proveedor', 'especificacion'])->all(),
    'id',
    static function (CotizacionDetalle $cd) {
        $item = $cd->subcatalogoItem->descripcion ?? '?';
        $proveedor = $cd->cotizacion->proveedor->razonSocial ?? '?';
        $espec = $cd->especificacion->titulo ?? null;

        $texto = "$item — $proveedor — " . Yii::$app->formatter->asDecimal($cd->precioUnitario, 2);

        if ($cd->marcaModelo) {
            $texto .= " ({$cd->marcaModelo})";
        }
        if ($espec) {
            $texto .= " [$espec]";
        }

        return $texto;
    }
);
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
                            <td><?= Html::encode($linea->subcatalogoItem->descripcion ?? '') ?></td>
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
                        <th colspan="4" style="text-align:right">Subtotal <?= Html::encode($nombreCategoria) ?>:</th>
                        <th><?= Yii::$app->formatter->asDecimal($grupo['subtotal'], 2) ?></th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        <?php endforeach; ?>

        <h2>Total general: <?= Yii::$app->formatter->asDecimal($totalGeneral, 2) ?></h2>
    <?php endif; ?>

    <?php if ($presupuesto->puedeEditarse()): ?>
        <h2>Agregar línea</h2>

        <?php $form = ActiveForm::begin(['action' => ['detalle', 'id' => $presupuesto->id]]); ?>

        <?= $form->field($nuevaLinea, 'idSubcatalogo')->dropDownList($items, ['prompt' => 'Seleccione un ítem del subcatálogo']) ?>

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
    <?php else: ?>
        <p class="text-muted">Este presupuesto está "<?= Html::encode($presupuesto->estadoLabel()) ?>" — las líneas ya no se pueden agregar ni quitar. Para modificarlo hay que crear una nueva versión.</p>
    <?php endif; ?>

</div>
