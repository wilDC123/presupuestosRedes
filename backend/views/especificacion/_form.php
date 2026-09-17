<?php

declare(strict_types=1);

use backend\models\SubcatalogoItem;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var backend\models\Especificacion $model
 * @var yii\widgets\ActiveForm $form
 */

$items = ArrayHelper::map(
    SubcatalogoItem::find()->orderBy(['descripcion' => SORT_ASC])->all(),
    'id',
    'descripcion'
);

$extraerMetadatosUrl = Url::to(['especificacion/extraer-metadatos']);
?>
<div class="especificacion-form">

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <?= $form->field($model, 'idSubcatalogo')->dropDownList($items, ['prompt' => 'Seleccione un ítem de subcatálogo']) ?>

    <div class="card mb-3">
        <div class="card-body">
            <?= $form->field($model, 'archivoEspecificacion')->fileInput(['accept' => '.pdf,.docx'])->hint('Suba el documento de especificación técnica (PDF o DOCX, máximo 10 MB) y presione "Extraer datos automáticamente" para prellenar los campos de abajo. El archivo también quedará guardado como el documento definitivo al guardar el formulario.') ?>

            <button type="button" id="btn-extraer-metadatos" class="btn btn-outline-secondary">
                Extraer datos automáticamente
            </button>

            <div id="extraccion-mensaje" class="mt-2"></div>
        </div>
    </div>

    <?= $form->field($model, 'codigoNet')->textInput(['maxlength' => 20, 'placeholder' => 'NET:832/26']) ?>

    <?= $form->field($model, 'titulo')->textInput(['maxlength' => 300]) ?>

    <?= $form->field($model, 'dependencia')->textInput(['maxlength' => 200]) ?>

    <?= $form->field($model, 'modeloSugerido')->textInput(['maxlength' => 200]) ?>

    <?= $form->field($model, 'documentoRuta')->textInput(['maxlength' => 500])->hint('Ruta al PDF/DOCX original con el detalle técnico completo. Se completa sola si sube un archivo arriba; también se puede escribir a mano si el documento ya vive en otro lugar.') ?>

    <?= $form->field($model, 'fechaEmision')->textInput([
        'value' => $model->fechaEmisionFormateada(),
        'placeholder' => 'dd/mm/aaaa',
        'maxlength' => 10,
    ])->hint('Formato: dd/mm/aaaa') ?>

    <?= $form->field($model, 'activo')->checkbox() ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php
$js = <<<JS
(function () {
    var boton = document.getElementById('btn-extraer-metadatos');
    var mensaje = document.getElementById('extraccion-mensaje');
    var inputArchivo = document.getElementById('especificacion-archivoespecificacion');

    function mostrarMensaje(texto, tipo) {
        mensaje.innerHTML = '<div class="alert alert-' + tipo + ' py-1 px-2 mb-0">' + texto + '</div>';
    }

    function fechaIsoAFormularioDate(iso) {
        if (!iso) {
            return '';
        }
        var partes = iso.split('-');
        if (partes.length !== 3) {
            return iso;
        }
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function setValor(idCampo, valor) {
        var campo = document.getElementById(idCampo);
        if (campo) {
            campo.value = valor || '';
        }
    }

    boton.addEventListener('click', function () {
        if (!inputArchivo.files || inputArchivo.files.length === 0) {
            mostrarMensaje('Primero seleccione un archivo PDF o DOCX.', 'warning');
            return;
        }

        var datosFormulario = new FormData();
        datosFormulario.append('archivoEspecificacion', inputArchivo.files[0]);
        datosFormulario.append(yii.getCsrfParam(), yii.getCsrfToken());

        boton.disabled = true;
        mostrarMensaje('Extrayendo datos, por favor espere…', 'info');

        fetch('{$extraerMetadatosUrl}', {
            method: 'POST',
            body: datosFormulario,
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin'
        })
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (data) {
                boton.disabled = false;

                if (data.error) {
                    mostrarMensaje(data.error, 'danger');
                    return;
                }

                var metadatos = data.metadatos || {};

                setValor('especificacion-codigonet', metadatos.codigoNet);
                setValor('especificacion-titulo', metadatos.titulo);
                setValor('especificacion-dependencia', metadatos.dependencia);
                setValor('especificacion-modelosugerido', metadatos.modeloSugerido);
                setValor('especificacion-fechaemision', fechaIsoAFormularioDate(metadatos.fechaEmision));

                mostrarMensaje('Datos extraídos. Revise y corrija lo que sea necesario antes de guardar.', 'success');
            })
            .catch(function () {
                boton.disabled = false;
                mostrarMensaje('No se pudo conectar con el servidor para extraer los datos.', 'danger');
            });
    });
})();
JS;

$this->registerJs($js);
?>
