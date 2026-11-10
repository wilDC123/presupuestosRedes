<?php

declare(strict_types=1);

use backend\models\Proyecto;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * Formulario de una ORDEN DE CAMBIO (parte 6; formato RD-GD-FR-006):
 * registrar una nueva sobre una fase aprobada, o corregir una que todavia no
 * se aprobo. Del FR-006 el sistema guarda solo tipo, descripcion y motivo: el
 * formulario se sigue haciendo en Word y se le adjunta el presupuesto que da
 * el sistema. Todo lo demas (datos del proyecto, area, equipo, lugar de
 * entrega) lo copia OrdenCambioFaseService de la fase.
 *
 * @var yii\web\View $this
 * @var Proyecto $orden nueva (isNewRecord) o la que se edita
 * @var Proyecto $fase la fase a la que pertenece
 */

$nueva = $orden->isNewRecord;
$this->title = $nueva ? 'Registrar orden de cambio' : 'Editar ' . mb_strtolower($orden->textoOrdenCambio(), 'UTF-8');
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $fase->identificador(), 'url' => ['view', 'id' => $fase->id]];
if (!$nueva) {
    $this->params['breadcrumbs'][] = ['label' => $orden->textoOrdenCambio(), 'url' => ['view', 'id' => $orden->id]];
}
$this->params['breadcrumbs'][] = $nueva ? 'Orden de cambio' : 'Editar';
?>
<div class="proyecto-orden-cambio">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                <?= Html::encode($fase->identificador() . ' — ' . $fase->nombre) ?>
                <?php if ($nueva): ?>
                    · será la <strong>orden de cambio N.° <?= (int) $orden->numeroOrdenCambio ?></strong>
                <?php endif; ?>
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('Cancelar', ['view', 'id' => $nueva ? $fase->id : $orden->id], ['class' => 'btn btn-outline']) ?>
            <?= Html::submitButton($nueva ? 'Registrar y armar su lista →' : 'Guardar cambios', ['class' => 'btn btn-primary', 'form' => 'orden-cambio-form']) ?>
        </div>
    </div>

    <?php $form = ActiveForm::begin(['id' => 'orden-cambio-form']); ?>

    <div class="rd-cols">
        <div class="card rd-col-2 rd-card-pad">
            <div class="rd-card-titulo mb-2">Orden de cambio</div>

            <?= $form->field($orden, 'tipoCambio')->dropDownList(Proyecto::tiposCambio(), ['prompt' => 'Seleccione el tipo', 'class' => 'form-select'])
                ->hint('Los tipos del formato RD-GD-FR-006 (7.1.1).') ?>
            <?= $form->field($orden, 'descripcionCambio')->textarea(['rows' => 3, 'maxlength' => 1000])
                ->hint('Qué se agrega (ej. "50 m más de cable UTP y 2 cámaras para el anfiteatro").') ?>
            <?= $form->field($orden, 'motivoCambio')->textarea(['rows' => 3, 'maxlength' => 1000])
                ->hint('Por qué hace falta (ej. "la unidad pidió ampliar la cobertura durante la obra").') ?>
        </div>

        <div class="card rd-col-1 rd-card-pad">
            <div class="rd-card-titulo mb-2">Cómo funciona</div>
            <p class="rd-nota">
                Una orden de cambio es un <strong>presupuesto adicional</strong> de la fase ya aprobada, solo con lo que
                se agrega: no quita ni corrige lo aprobado.
            </p>
            <p class="rd-nota">
                Tiene su propia lista de materiales (código LM), especificaciones, solicitudes, presupuesto (código PR) y
                carta. Toma de la fase los datos del proyecto, el área de intervención y el equipo.
            </p>
            <p class="rd-nota mb-0">
                El formulario FR-006 se sigue haciendo en Word; se le adjunta el presupuesto. Si la orden no se aprueba,
                se elimina: sus especificaciones emitidas quedan anuladas.
            </p>
        </div>
    </div>

    <?php ActiveForm::end(); ?>

</div>
