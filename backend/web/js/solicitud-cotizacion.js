/**
 * Solicitudes de cotizacion (views/solicitud-cotizacion/index.php).
 *
 *  1. Muestra el campo "nombre" solo cuando el destino es "Nueva solicitud".
 *  2. Casilla "marcar todos".
 *  3. Antes de enviar: al menos un item marcado y, si es nueva, su nombre.
 *
 * El servidor vuelve a validar todo en SolicitudCotizacionService::mover().
 */
$(function () {
    var $form = $('#solicitud-mover-form');

    if ($form.length === 0) {
        return;
    }

    var $destino = $('#solicitud-destino');
    var $nombre = $('#solicitud-nombre');
    var $error = $('#solicitud-error-cliente');

    function actualizarNombre() {
        $('#solicitud-nombre-nueva').toggleClass('d-none', $destino.val() !== 'nueva');
    }

    $destino.on('change', actualizarNombre);
    actualizarNombre();

    $('#solicitud-marcar-todos').on('change', function () {
        $form.find('.solicitud-item').prop('checked', $(this).prop('checked'));
    });

    $form.on('submit', function (evento) {
        var mensaje = '';

        if ($form.find('.solicitud-item:checked').length === 0) {
            mensaje = 'Marque al menos un ítem de la lista.';
        } else if ($destino.val() === 'nueva' && $.trim($nombre.val()) === '') {
            mensaje = 'Escriba el nombre de la solicitud (el rubro al que se envía).';
        }

        $error.text(mensaje).toggleClass('d-none', mensaje === '');

        if (mensaje !== '') {
            evento.preventDefault();
        }
    });
});
