<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Configuracion del documento "Solicitud de cotizacion": los textos fijos que
 * llevan todas las solicitudes. La solicitud no guarda copia de ellos, asi
 * que un cambio tambien se ve al reimprimir las anteriores.
 *
 * @var yii\web\View $this
 * @var backend\models\ConfiguracionSolicitudCotizacion $configuracion
 * @var backend\models\Proyecto|null $proyecto desde donde se entro (para volver)
 */

$this->title = 'Configuración de la solicitud de cotización';
if ($proyecto !== null) {
    $this->params['breadcrumbs'][] = ['label' => 'Solicitudes de cotización', 'url' => ['index', 'id' => $proyecto->id]];
}
$this->params['breadcrumbs'][] = 'Configuración';
?>
<div class="solicitud-cotizacion-configuracion">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                Valen para todas las solicitudes, también al reimprimir las anteriores.
            </div>
        </div>
        <?php if ($proyecto !== null): ?>
            <div class="rd-acciones">
                <?= Html::a('← Solicitudes de cotización', ['index', 'id' => $proyecto->id], ['class' => 'btn btn-outline']) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php $form = ActiveForm::begin(); ?>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Textos del documento</div></div>
        <div class="rd-card-pad">
            <?= $form->field($configuracion, 'textoCabecera')->textarea(['rows' => 2, 'maxlength' => 500])
                ->hint('Una línea por renglón. La primera sale en negrita (ej. el nombre de la universidad y la unidad).') ?>
            <?= $form->field($configuracion, 'textoInstrucciones')->textarea(['rows' => 4, 'maxlength' => 1000])
                ->hint('Sale debajo de los datos de la solicitud, antes de la tabla de ítems.') ?>
            <?= $form->field($configuracion, 'textoNotaFinal')->textarea(['rows' => 2, 'maxlength' => 500])
                ->hint('Opcional. Sale antes de la firma del proveedor.') ?>
            <p class="rd-nota mb-0">
                El título "SOLICITUD DE COTIZACIÓN", las columnas de la tabla y los datos que llena el proveedor (NIT,
                validez, tiempo de entrega, firma) son fijos. La nota de cada solicitud se escribe en la propia solicitud.
            </p>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
