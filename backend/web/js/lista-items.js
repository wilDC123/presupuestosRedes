/**
 * Comportamiento de la lista de items del proyecto (views/lista-items/index.php).
 *
 *  1. Buscador: filtra en el navegador la lista del subcatalogo.
 *  2. Boton "+": agrega una fila al grupo de la clasificacion del item,
 *     copiando la fila modelo (<template>) con un indice nuevo en
 *     items[n][...]. Si la clasificacion aun no tiene grupo, lo crea.
 *  3. Boton "×": quita la fila (y el grupo, si queda vacio).
 *  4. Antes de enviar: las cantidades deben ser numeros >= 0.
 *  5. Avisa que hay cambios sin guardar.
 *
 * El servidor vuelve a validar todo en ListaItemsService::guardar().
 */
$(function () {
    var $form = $('#lista-items-form');

    if ($form.length === 0) {
        return;
    }

    var $tabla = $('#lista-tabla');
    var filaModelo = $('#lista-fila-modelo').html();
    var grupoModelo = $('#lista-grupo-modelo').html();
    // Indice de la proxima fila. Solo crece: asi dos filas nunca comparten indice.
    var siguiente = parseInt($('#lista-siguiente').data('siguiente'), 10) || 0;
    var hayCambios = false;

    function marcarCambios() {
        hayCambios = true;
        $('#lista-sin-guardar').removeClass('d-none');
    }

    function actualizarTotales() {
        var total = 0;

        $tabla.find('tbody.lista-grupo').each(function () {
            var cantidad = $(this).find('tr.lista-item').length;

            $(this).find('.lista-subtotal').text(cantidad);
            total += cantidad;
        });

        $('#lista-total').text(total);
        $('#lista-total-grupos').text($tabla.find('tbody.lista-grupo').length);
        $('#lista-vacia').toggleClass('d-none', total > 0);
    }

    // Devuelve el grupo de esa clasificacion; si no existe, lo crea al final.
    function grupoDe(idCategoria, nombreCategoria) {
        var $grupo = $tabla.find('tbody.lista-grupo[data-categoria="' + idCategoria + '"]');

        if ($grupo.length === 0) {
            $grupo = $(grupoModelo.replace(/__cat__/g, idCategoria));
            // .text() escapa el nombre: nunca se interpreta como HTML.
            $grupo.find('.cat-row td').text(nombreCategoria);
            $tabla.append($grupo);
        }

        return $grupo;
    }

    $('#lista-buscar').on('input', function () {
        var texto = $.trim($(this).val()).toLowerCase();

        $('#lista-subcatalogo .row-item').each(function () {
            var coincide = String($(this).data('nombre')).toLowerCase().indexOf(texto) !== -1;

            $(this).toggleClass('d-none', !coincide);
        });
    });

    $('#lista-subcatalogo').on('click', '.addbtn', function () {
        var $origen = $(this).closest('.row-item');
        var $fila = $(filaModelo.replace(/__n__/g, String(siguiente++)));

        $fila.find('.lista-item-subcatalogo').val($origen.data('id'));
        $fila.find('.lista-item-nombre').text($origen.data('nombre'));
        $fila.find('.lista-item-unidad').text($origen.data('unidad'));
        $fila.find('.qty').val('1');

        grupoDe(String($origen.data('categoria')), String($origen.data('categoriaNombre')))
            .find('tr.subtotal-row').before($fila);

        actualizarTotales();
        marcarCambios();
        $fila.find('.qty').trigger('focus').trigger('select');
    });

    $tabla.on('click', '.rd-quitar', function () {
        var $grupo = $(this).closest('tbody');

        $(this).closest('tr').remove();

        if ($grupo.find('tr.lista-item').length === 0) {
            $grupo.remove();
        }

        actualizarTotales();
        marcarCambios();
    });

    $tabla.on('input', 'input', marcarCambios);

    $form.on('submit', function (evento) {
        var valido = true;

        $tabla.find('.qty').each(function () {
            var valor = $.trim($(this).val());
            var correcto = valor !== '' && !isNaN(Number(valor)) && Number(valor) >= 0;

            $(this).toggleClass('is-invalid', !correcto);
            valido = valido && correcto;
        });

        $('#lista-error-cliente').toggleClass('d-none', valido);

        if (!valido) {
            evento.preventDefault();
        }
    });

    $('#lista-enviar').on('click', function (evento) {
        if (!window.confirm($(this).data('mensaje'))) {
            evento.preventDefault();
        }
    });

    // El PDF sale de la lista GUARDADA.
    $('#lista-imprimir').on('click', function (evento) {
        if (hayCambios && !window.confirm('Hay cambios sin guardar: la impresión mostrará la lista guardada. ¿Imprimir de todos modos?')) {
            evento.preventDefault();
        }
    });
});
