<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Tabla editable de líneas (CotizacionDetalle) embebida dentro del
 * formulario de edición de una Cotizacion. No es un CRUD aparte: las filas
 * se cargan, validan y guardan junto con la cabecera en
 * CotizacionController::actionUpdate().
 *
 * Las opciones de los desplegables llegan del controlador (esta vista no
 * consulta la base). La especificacion es opcional: "(ninguna)".
 *
 * @var backend\models\CotizacionDetalle[] $modelsDetalle
 * @var array<int, string> $items Ver SubcatalogoItemService::opciones()
 * @var array{grupos: array<int, array{nombre: string, opciones: array<int, string>}>, urls: array<int, string>} $especificaciones Ver EspecificacionService::selector()
 */

// Un <optgroup> por categoria de la libreria.
$opcionesEspecificacion = [];
foreach ($especificaciones['grupos'] as $grupo) {
    $opcionesEspecificacion[$grupo['nombre']] = $grupo['opciones'];
}
?>
<h2>Líneas de la cotización</h2>

<table class="table" id="cotizaciondetalle-table">
    <thead>
        <tr>
            <th>Ítem de subcatálogo</th>
            <th>Especificación</th>
            <th>Marca / modelo ofertado</th>
            <th>Cantidad</th>
            <th>Precio unitario</th>
            <th>Precio total</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($modelsDetalle as $i => $modelDetalle): ?>
            <tr class="detalle-row">
                <td>
                    <?= Html::activeHiddenInput($modelDetalle, "[$i]id") ?>
                    <?= Html::activeDropDownList($modelDetalle, "[$i]idSubcatalogo", $items, [
                        'class' => 'form-control',
                        'prompt' => 'Seleccione un ítem',
                    ]) ?>
                </td>
                <td>
                    <?= Html::activeDropDownList($modelDetalle, "[$i]idEspecificacion", $opcionesEspecificacion, [
                        'class' => 'form-control',
                        'prompt' => '(ninguna)',
                    ]) ?>
                </td>
                <td><?= Html::activeTextInput($modelDetalle, "[$i]marcaModelo", ['class' => 'form-control', 'maxlength' => 300]) ?></td>
                <td><?= Html::activeTextInput($modelDetalle, "[$i]cantidad", ['class' => 'form-control', 'type' => 'number', 'step' => '0.01']) ?></td>
                <td><?= Html::activeTextInput($modelDetalle, "[$i]precioUnitario", ['class' => 'form-control', 'type' => 'number', 'step' => '0.01']) ?></td>
                <td class="precio-total-display">
                    <?= $modelDetalle->isNewRecord ? '—' : Yii::$app->formatter->asDecimal($modelDetalle->precioTotal, 2) ?>
                </td>
                <td><button type="button" class="btn btn-danger btn-quitar-fila">Quitar</button></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<button type="button" id="btn-agregar-fila" class="btn btn-secondary">Añadir línea</button>

<?php
$this->registerJs(<<<'JS'
(function () {
    var $table = $('#cotizaciondetalle-table tbody');
    var index = $table.find('tr').length;

    $('#btn-agregar-fila').on('click', function () {
        var $clone = $table.find('tr').last().clone();

        $clone.find('input, select').each(function () {
            var $el = $(this);
            var name = $el.attr('name');
            var id = $el.attr('id');

            if (name) {
                $el.attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
            }
            if (id) {
                $el.attr('id', id.replace(/-\d+-/, '-' + index + '-'));
            }

            if ($el.is('select')) {
                $el.prop('selectedIndex', 0);
            } else {
                $el.val('');
            }
        });

        $clone.find('.precio-total-display').text('—');
        $table.append($clone);
        index++;
    });

    $(document).on('click', '.btn-quitar-fila', function () {
        if ($table.find('tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            $(this).closest('tr').find('input, select').val('');
        }
    });
})();
JS
);
?>
