/**
 * Panel "Reemitir formulario NET" de la lista de items
 * (views/lista-items/index.php).
 *
 * Cada boton "Reemitir" (o "Emitir formulario NET") trae en data-* el item y
 * las condiciones de su emision vigente; aqui se copian al panel, que tiene
 * su propio formulario (fuera del de la lista, porque no se pueden anidar).
 *
 * Si la lista tiene cambios sin guardar, se avisa: enviar este formulario
 * recarga la pagina y esos cambios se pierden.
 *
 * El servidor vuelve a validar todo en EspecificacionEmisionService::reemitir().
 */
$(function () {
    var $panel = $('#reemitir-panel');

    if ($panel.length === 0) {
        return;
    }

    function hayCambiosSinGuardar() {
        var $aviso = $('#lista-sin-guardar');
        return $aviso.length > 0 && !$aviso.hasClass('d-none');
    }

    $(document).on('click', '.esp-reemitir', function () {
        var $boton = $(this);
        var net = $boton.data('net');

        if (hayCambiosSinGuardar()
            && !window.confirm('La lista tiene cambios sin guardar y se perderán al emitir. Guarde la lista primero si los necesita. ¿Continuar?')) {
            return;
        }

        $('#reemitir-item').val($boton.data('item'));
        $('#reemitir-titulo').text((net ? 'Reemitir ' + net : 'Emitir formulario NET') + ': ' + $boton.data('titulo'));
        $('#reemitir-lugar').val($boton.data('lugar') || '');
        $('#reemitir-validez').val($boton.data('validez') || '');
        $('#reemitir-tiempo').val($boton.data('tiempo') || '');
        $('#reemitir-motivo').val('').prop('required', !!net);
        // Sin emision anterior no hay nada que reemplazar: no se pide motivo.
        $('#reemitir-motivo-grupo').toggleClass('d-none', !net);

        $panel.removeClass('d-none');
        $panel[0].scrollIntoView({behavior: 'smooth', block: 'center'});
        (net ? $('#reemitir-motivo') : $('#reemitir-lugar')).trigger('focus');
    });

    $('#reemitir-cancelar').on('click', function () {
        $panel.addClass('d-none');
    });
});
