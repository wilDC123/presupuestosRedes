/**
 * Paso "Presupuesto" (views/elaborar-presupuesto/index.php): guardado
 * automatico de cada precio con AJAX.
 *
 * AJAX = el navegador envia el cambio al servidor "por detras", sin recargar
 * la pagina; el servidor responde con los totales nuevos y aqui se
 * reemplazan. El token CSRF lo agrega solo yii.js (lee la etiqueta meta
 * que registra layouts/_head.php).
 *
 *  1. Marcar el menor precio (o un item sin cotizaciones) se guarda al instante.
 *  2. Marcar otro precio, o "Manual" teniendo cotizaciones, primero pide la
 *     justificacion: se guarda con el boton "Guardar" (o Enter).
 *  3. Si el servidor rechaza el cambio, la fila vuelve a lo que estaba
 *     guardado y se muestra el motivo.
 *
 * El servidor vuelve a validar todo en ElaborarPresupuestoService::elegir().
 */
$(function () {
    var $tabla = $('#elaborar-tabla');

    if ($tabla.length === 0) {
        return;
    }

    var url = $tabla.data('url');

    function estado($fila, texto, clase) {
        $fila.find('.elaborar-estado')
            .removeClass('text-success text-danger text-muted')
            .addClass(clase)
            .text(texto);
    }

    // Lo que la fila tiene marcado ahora: {opcion, precio}.
    function marcado($fila) {
        return {
            opcion: String($fila.find('.elaborar-opcion:checked').val() || ''),
            precio: $.trim($fila.find('.elaborar-precio').val())
        };
    }

    // true si lo marcado necesita justificacion (no es el menor precio).
    function necesitaJustificacion($fila, opcion) {
        var menor = String($fila.data('menor'));

        return menor !== '' && opcion !== menor;
    }

    // Deja la fila como esta guardada en el servidor.
    function volverAGuardado($fila) {
        var guardada = String($fila.data('guardada'));

        $fila.find('.elaborar-opcion').prop('checked', false);
        $fila.find('.elaborar-opcion[value="' + guardada + '"]').prop('checked', true);
        $fila.find('.elaborar-precio').val(guardada === 'manual' ? $fila.data('precioGuardado') : '');
        $fila.find('.elaborar-justificacion').toggleClass('d-none', !necesitaJustificacion($fila, guardada) || guardada === '');
    }

    function enviar($fila) {
        var datos = marcado($fila);

        if (datos.opcion === 'manual' && datos.precio === '') {
            estado($fila, 'Escriba el precio unitario.', 'text-muted');
            return;
        }

        estado($fila, 'Guardando…', 'text-muted');

        $.post(url, {
            idProyectoItem: $fila.data('item'),
            opcion: datos.opcion,
            precio: datos.precio,
            justificacion: $.trim($fila.find('.elaborar-texto-justificacion').val())
        }).done(function (respuesta) {
            if (!respuesta.ok) {
                // El motivo se ve, pero la fila vuelve a lo guardado solo si
                // no falta la justificacion (para que la escriba ahi mismo).
                if (!/[Jj]ustifique/.test(respuesta.error)) {
                    volverAGuardado($fila);
                }
                estado($fila, respuesta.error, 'text-danger');
                return;
            }

            $fila.data('guardada', respuesta.fila.opcion);
            $fila.data('precioGuardado', respuesta.fila.opcion === 'manual' ? datos.precio : '');
            $fila.find('.elaborar-total').text(respuesta.fila.total);
            $.each(respuesta.subtotales, function (idCategoria, subtotal) {
                $tabla.find('.elaborar-subtotal[data-categoria="' + idCategoria + '"]').text(subtotal);
            });
            $('#elaborar-total-general').text(respuesta.total);
            $('#elaborar-con-precio').text(respuesta.conPrecio);
            // HTML armado por el servidor con sus propias vistas parciales.
            $('#elaborar-partidas').html(respuesta.partidasHtml);
            $('#elaborar-control').html(respuesta.controlHtml);
            estado($fila, 'Guardado ✓', 'text-success');
        }).fail(function () {
            volverAGuardado($fila);
            estado($fila, 'No se pudo guardar (sin conexión con el servidor). Intente de nuevo.', 'text-danger');
        });
    }

    // Decide si se guarda ya o si primero hace falta la justificacion.
    function intentar($fila) {
        var datos = marcado($fila);
        var $caja = $fila.find('.elaborar-justificacion');
        var conJustificacion = necesitaJustificacion($fila, datos.opcion);

        $caja.toggleClass('d-none', !conJustificacion);

        if (datos.opcion === 'manual' && datos.precio === '') {
            $fila.find('.elaborar-precio').trigger('focus');
            estado($fila, 'Escriba el precio unitario.', 'text-muted');
            return;
        }

        if (conJustificacion && $.trim($caja.find('.elaborar-texto-justificacion').val()) === '') {
            $caja.find('.elaborar-texto-justificacion').trigger('focus');
            estado($fila, 'No es el menor precio: escriba la justificación y pulse Guardar.', 'text-muted');
            return;
        }

        enviar($fila);
    }

    $tabla.on('change', '.elaborar-opcion', function () {
        intentar($(this).closest('tr'));
    });

    // Entrar al precio manual marca la opcion "Manual".
    $tabla.on('focus', '.elaborar-precio', function () {
        $(this).closest('label').find('.elaborar-opcion').prop('checked', true);
    });

    // El precio manual se guarda al salir del campo (evento "change").
    $tabla.on('change', '.elaborar-precio', function () {
        intentar($(this).closest('tr'));
    });

    $tabla.on('click', '.elaborar-guardar', function () {
        enviar($(this).closest('tr'));
    });

    $tabla.on('keydown', '.elaborar-texto-justificacion', function (evento) {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            enviar($(this).closest('tr'));
        }
    });
});
