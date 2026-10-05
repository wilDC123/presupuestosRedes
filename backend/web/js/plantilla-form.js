/**
 * Comportamiento del formulario de plantilla (views/plantilla/_form.php).
 *
 *  1. Buscador: filtra en el navegador la lista del subcatalogo.
 *  2. Boton "+": agrega una fila a "Items de la plantilla", copiando la fila
 *     modelo (<template>) y dandole un indice nuevo en items[n][...].
 *     La clasificacion inicial es la categoria que el item tiene en el
 *     subcatalogo, y su casilla se marca sola si no lo estaba.
 *  3. Boton "×": quita la fila.
 *
 * El servidor vuelve a validar todo en PlantillaService::guardar().
 */
$(function () {
    var $form = $('#plantilla-form');

    if ($form.length === 0) {
        return;
    }

    var $items = $('#plantilla-items');
    var modelo = $('#plantilla-fila-modelo').html();
    // Indice de la proxima fila. Solo crece: asi dos filas nunca comparten indice.
    var siguiente = parseInt($items.data('siguiente'), 10) || 0;

    function actualizarTotal() {
        var total = $items.find('tr.plantilla-item').length;

        $('#plantilla-total').text(total);
        $('#plantilla-vacia').toggleClass('d-none', total > 0);
    }

    $('#plantilla-buscar').on('input', function () {
        var texto = $.trim($(this).val()).toLowerCase();

        $('#plantilla-subcatalogo .row-item').each(function () {
            var coincide = String($(this).data('nombre')).toLowerCase().indexOf(texto) !== -1;

            $(this).toggleClass('d-none', !coincide);
        });
    });

    $('#plantilla-subcatalogo').on('click', '.addbtn', function () {
        var $origen = $(this).closest('.row-item');
        var idCategoria = String($origen.data('categoria'));
        var $fila = $(modelo.replace(/__n__/g, String(siguiente++)));

        $fila.find('input[type="hidden"]').val($origen.data('id'));
        $fila.find('.plantilla-item-nombre').text($origen.data('nombre'));
        $fila.find('.plantilla-item-categoria').val(idCategoria);

        // La clasificacion del item debe estar entre las marcadas.
        $('#plantilla-categorias input[value="' + idCategoria + '"]').prop('checked', true);

        $items.append($fila);
        actualizarTotal();
    });

    $items.on('click', '.rd-quitar', function () {
        $(this).closest('tr').remove();
        actualizarTotal();
    });

    // Si se cambia la clasificacion de una fila, se marca su casilla.
    $items.on('change', '.plantilla-item-categoria', function () {
        $('#plantilla-categorias input[value="' + $(this).val() + '"]').prop('checked', true);
    });
});
