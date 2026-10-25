/**
 * Paso "Presupuesto" (views/elaborar-presupuesto/index.php): guardado
 * automatico de cada precio con AJAX.
 *
 * AJAX = el navegador envia el cambio al servidor "por detras", sin recargar
 * la pagina; el servidor responde con los totales nuevos y aqui se
 * reemplazan. El token CSRF lo agrega solo yii.js (lee la etiqueta meta
 * que registra layouts/_head.php).
 *
 *  1. Marcar un precio se guarda al instante (el precio directo y su
 *     fuente, al salir del campo).
 *  2. "SIGMA: x.xx" copia el precio referencial de SIGMA al precio directo
 *     (con su fuente) y lo guarda.
 *  3. Si no es el menor precio, aparece la caja de justificacion, OPCIONAL:
 *     lo que se escriba se guarda con su boton "Guardar" (o Enter).
 *  4. Si el servidor rechaza el cambio, la fila vuelve a lo que estaba
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
    // Igual que ElaborarPresupuestoService::DIRECTO.
    var DIRECTO = 'directo';

    function estado($fila, texto, clase) {
        $fila.find('.elaborar-estado')
            .removeClass('text-success text-danger text-muted')
            .addClass(clase)
            .text(texto);
    }

    // Lo que la fila tiene marcado ahora: {opcion, precio, fuente}.
    function marcado($fila) {
        return {
            opcion: String($fila.find('.elaborar-opcion:checked').val() || ''),
            precio: $.trim($fila.find('.elaborar-precio').val()),
            fuente: $.trim($fila.find('.elaborar-fuente').val() || '')
        };
    }

    // true si lo marcado no es el menor precio (muestra la justificacion).
    function noEsElMenor($fila, opcion) {
        var menor = String($fila.data('menor'));

        return menor !== '' && opcion !== menor;
    }

    // Deja la fila como esta guardada en el servidor.
    function volverAGuardado($fila) {
        var guardada = String($fila.data('guardada'));
        var esDirecto = guardada === DIRECTO;

        $fila.find('.elaborar-opcion').prop('checked', false);
        $fila.find('.elaborar-opcion[value="' + guardada + '"]').prop('checked', true);
        $fila.find('.elaborar-precio').val(esDirecto ? $fila.data('precioGuardado') : '');
        $fila.find('.elaborar-fuente').val(esDirecto ? $fila.data('fuenteGuardada') : '');
        $fila.find('.elaborar-justificacion').toggleClass('d-none', !noEsElMenor($fila, guardada) || guardada === '');
    }

    function enviar($fila) {
        var datos = marcado($fila);

        if (datos.opcion === DIRECTO && datos.precio === '') {
            estado($fila, 'Escriba el precio unitario.', 'text-muted');
            return;
        }

        estado($fila, 'Guardando…', 'text-muted');

        $.post(url, {
            idProyectoItem: $fila.data('item'),
            opcion: datos.opcion,
            precio: datos.precio,
            fuente: datos.fuente,
            justificacion: $.trim($fila.find('.elaborar-texto-justificacion').val())
        }).done(function (respuesta) {
            if (!respuesta.ok) {
                volverAGuardado($fila);
                estado($fila, respuesta.error, 'text-danger');
                return;
            }

            var esDirecto = respuesta.fila.opcion === DIRECTO;

            $fila.data('guardada', respuesta.fila.opcion);
            $fila.data('precioGuardado', esDirecto ? datos.precio : '');
            $fila.data('fuenteGuardada', esDirecto ? datos.fuente : '');
            // Con una cotizacion, el precio directo y su fuente se vacian.
            if (!esDirecto) {
                $fila.find('.elaborar-precio, .elaborar-fuente').val('');
            }
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

    // Muestra la caja de justificacion (opcional) si no es el menor precio y
    // guarda.
    function intentar($fila) {
        var datos = marcado($fila);

        $fila.find('.elaborar-justificacion').toggleClass('d-none', !noEsElMenor($fila, datos.opcion));

        if (datos.opcion === DIRECTO && datos.precio === '') {
            $fila.find('.elaborar-precio').trigger('focus');
            estado($fila, 'Escriba el precio unitario.', 'text-muted');
            return;
        }

        enviar($fila);
    }

    // Marca la opcion "Precio directo" de la fila.
    function marcarDirecto($fila) {
        $fila.find('.elaborar-opcion[value="' + DIRECTO + '"]').prop('checked', true);
    }

    $tabla.on('change', '.elaborar-opcion', function () {
        intentar($(this).closest('tr'));
    });

    // Entrar al precio directo o a su fuente marca la opcion "Precio directo".
    $tabla.on('focus', '.elaborar-precio, .elaborar-fuente', function () {
        marcarDirecto($(this).closest('tr'));
    });

    // El precio directo y la fuente se guardan al salir del campo (evento
    // "change"). Sin precio, la fuente sola no se envia.
    $tabla.on('change', '.elaborar-precio', function () {
        intentar($(this).closest('tr'));
    });

    $tabla.on('change', '.elaborar-fuente', function () {
        var $fila = $(this).closest('tr');

        if (marcado($fila).precio !== '') {
            intentar($fila);
        }
    });

    $tabla.on('click', '.elaborar-usar-sigma', function () {
        var $fila = $(this).closest('tr');

        marcarDirecto($fila);
        $fila.find('.elaborar-precio').val($(this).data('precio'));
        $fila.find('.elaborar-fuente').val($(this).data('fuente'));
        intentar($fila);
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
