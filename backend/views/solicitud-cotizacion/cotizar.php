<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\web\JqueryAsset;
use yii\widgets\ActiveForm;

/**
 * Registrar (o corregir) la cotizacion que un proveedor respondio a una
 * solicitud. Los items YA vienen de la solicitud: el usuario solo escribe
 * marca/modelo y precio unitario. Un item sin precio = el proveedor no lo
 * cotizo. La cantidad es la de la lista (no se edita).
 *
 * Formulario:
 *   Cotizacion[...] (cabecera, validada por ActiveForm en el navegador)
 *   filas[idProyectoItem][marcaModelo]
 *   filas[idProyectoItem][precioUnitario]
 *
 * web/js/cotizar.js calcula los totales y valida los precios antes de
 * enviar; el servidor vuelve a validar todo en
 * CotizacionService::guardarDesdeSolicitud().
 *
 * @var yii\web\View $this
 * @var backend\models\SolicitudCotizacion $solicitud
 * @var backend\models\Cotizacion $cotizacion
 * @var backend\models\CotizacionDetalle[] $filas indexadas por idProyectoItem. Ver CotizacionService::filasDesdeSolicitud()
 * @var array<int, string> $referencias [idProyectoItem => marca y modelo de referencia pedidos en la solicitud]
 * @var string[] $errores
 * @var bool $editable Ver SolicitudCotizacionService::puedeEditarse()
 * @var array<int, string> $proveedores Ver ProveedorService::opcionesActivos()
 */

$this->registerJsFile('@web/js/cotizar.js', ['depends' => [JqueryAsset::class]]);

$proyecto = $solicitud->proyecto;
$this->title = ($cotizacion->isNewRecord ? 'Registrar cotización recibida' : 'Corregir cotización') . ' — ' . $solicitud->codigoCorto();
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['/proyecto/index']];
$this->params['breadcrumbs'][] = ['label' => $proyecto->codigo ?: '#' . $proyecto->id, 'url' => ['/proyecto/view', 'id' => $proyecto->id]];
$this->params['breadcrumbs'][] = ['label' => 'Solicitudes de cotización', 'url' => ['index', 'id' => $proyecto->id]];
$this->params['breadcrumbs'][] = $cotizacion->isNewRecord ? 'Registrar cotización' : 'Corregir cotización';

$formato = Yii::$app->formatter;
?>
<div class="solicitud-cotizar">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                <?= Html::encode($solicitud->codigo() . ' · ' . $solicitud->nombre) ?>
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('← Solicitudes', ['index', 'id' => $proyecto->id], ['class' => 'btn btn-outline']) ?>
        </div>
    </div>

    <?php if (!$editable): ?>
        <div class="notice mb-3">Las cotizaciones de este proyecto ya no se modifican.</div>
    <?php endif; ?>

    <?php if ($errores !== []): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $error): ?>
                <div><?= Html::encode($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="alert alert-danger d-none" id="cotizar-error-cliente"></div>

    <?php $form = ActiveForm::begin(['id' => 'cotizar-form', 'options' => ['enctype' => 'multipart/form-data']]); ?>

    <div class="card rd-card-pad mb-3">
        <div class="rd-card-titulo mb-2">Datos de la cotización</div>
        <div class="row">
            <div class="col-md-4">
                <?= $form->field($cotizacion, 'idProveedor')->dropDownList($proveedores, ['prompt' => 'Seleccione un proveedor'])
                    ->hint($proveedores === [] ? 'No hay proveedores activos: regístrelos en ' . Html::a('Proveedores', ['/proveedor/create']) . '.' : '') ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($cotizacion, 'numeroReferencia')->textInput(['maxlength' => 100]) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($cotizacion, 'fecha')->textInput([
                    'value' => $cotizacion->fechaFormateada(),
                    'placeholder' => 'dd/mm/aaaa',
                    'maxlength' => 10,
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($cotizacion, 'validezDias')->textInput(['type' => 'number', 'min' => 0]) ?>
            </div>
        </div>
        <?= $form->field($cotizacion, 'documentoFile')->fileInput(['accept' => '.pdf'])
            ->hint('PDF de la cotización del proveedor (opcional, máximo 10 MB).'
                . (!empty($cotizacion->documentoRuta) ? ' Archivo actual: ' . Html::a('ver PDF', $cotizacion->urlDocumento(), ['target' => '_blank']) . '. Déjelo vacío para conservarlo.' : '')) ?>
    </div>

    <div class="card">
        <div class="rd-card-head">
            <div class="rd-card-titulo">Precios por ítem</div>
            <div class="kicker">Deje en blanco el precio de los ítems que el proveedor no cotizó.</div>
        </div>

        <table class="table" id="cotizar-tabla">
            <thead>
                <tr>
                    <th>Ítem</th>
                    <th>Unidad</th>
                    <th class="rd-num">Cantidad</th>
                    <th>Marca / modelo ofertado</th>
                    <th class="rd-num">Precio unitario</th>
                    <th class="rd-num">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $idItem => $detalle): ?>
                    <?php $item = $detalle->proyectoItem; ?>
                    <tr class="cotizar-fila">
                        <td>
                            <?= Html::encode($item->subcatalogoItem->descripcion ?? 'Ítem no disponible') ?>
                            <?php if (isset($referencias[$idItem])): ?>
                                <div class="rd-celda-sub">Referencia pedida: <?= Html::encode($referencias[$idItem]) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item->observacion)): ?>
                                <div class="rd-celda-sub"><?= Html::encode($item->observacion) ?></div>
                            <?php endif; ?>
                            <?php if ($item->especificacion !== null): ?>
                                <div class="rd-celda-sub">Especificación técnica: <?= Html::encode($item->referenciaEspecificacion()) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= Html::encode((string) $item->unidadMedida) ?></td>
                        <td class="rd-num mono cotizar-cantidad" data-cantidad="<?= Html::encode((string) (float) $item->cantidad) ?>">
                            <?= $formato->asDecimal($item->cantidad, 2) ?>
                        </td>
                        <td>
                            <?= Html::textInput("filas[$idItem][marcaModelo]", (string) $detalle->marcaModelo, [
                                'class' => 'form-control',
                                'maxlength' => 300,
                                'disabled' => !$editable,
                                'aria-label' => 'Marca / modelo ofertado',
                            ]) ?>
                        </td>
                        <td class="rd-num">
                            <?= Html::textInput("filas[$idItem][precioUnitario]", $detalle->precioUnitario === null ? '' : (string) $detalle->precioUnitario, [
                                'class' => 'form-control cotizar-precio text-end',
                                'type' => 'number',
                                'step' => '0.01',
                                'min' => '0',
                                'placeholder' => 'No cotizó',
                                'disabled' => !$editable,
                                'aria-label' => 'Precio unitario',
                            ]) ?>
                        </td>
                        <td class="rd-num mono cotizar-total">—</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="subtotal-row">
                    <td colspan="5">
                        Total cotizado (<span id="cotizar-cotizados">0</span> de <?= count($filas) ?> ítems)
                    </td>
                    <td class="rd-num mono" id="cotizar-total-general">0.00</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if ($editable): ?>
        <div class="rd-acciones mt-3">
            <?= Html::submitButton('Guardar cotización', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Cancelar', ['index', 'id' => $proyecto->id], ['class' => 'btn btn-outline']) ?>
        </div>
    <?php endif; ?>

    <?php ActiveForm::end(); ?>

</div>
