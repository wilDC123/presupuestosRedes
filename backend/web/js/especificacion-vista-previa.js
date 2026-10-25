/**
 * Ventana "Ver detalle" de la especificacion tecnica, en la lista de items
 * del proyecto (views/lista-items/index.php).
 *
 * El boton "Ver detalle" de una fila abre la ventana (modal de Bootstrap):
 *
 *  - A la izquierda, una lista de fichas. Sin texto en el buscador muestra la
 *    ficha ya elegida en la fila y las "Sugeridas para este ítem" (fichas
 *    cuyo item de referencia es el de la fila). Al escribir, busca en TODA la
 *    libreria (EspecificacionController::actionBuscar: titulo, modelo y
 *    caracteristicas, todas las palabras en cualquier orden, sin importar
 *    tildes). Las flechas arriba/abajo recorren la lista sin soltar el teclado.
 *  - A la derecha, el detalle de la ficha marcada
 *    (EspecificacionController::actionVistaPrevia).
 *  - "Usar esta especificación" la deja elegida en la fila. Si la ficha aun no
 *    tiene item de referencia, la casilla "Recordar como sugerida para este
 *    ítem" marca la fila para que, al guardar, la ficha tome este item
 *    (ListaItemsService::guardar()). Nada se guarda hasta "Guardar lista".
 */
$(function () {
    var $ventana = $('#esp-previa');

    if ($ventana.length === 0) {
        return;
    }

    var modal = new bootstrap.Modal($ventana[0]);
    var $buscar = $('#esp-previa-buscar');
    var $lista = $('#esp-previa-lista');
    var $resumen = $('#esp-previa-resumen');
    var $contenido = $('#esp-previa-contenido');
    var $usar = $('#esp-previa-usar');
    var $recordarGrupo = $('#esp-previa-recordar-grupo');
    var $recordar = $('#esp-previa-recordar');
    var urlDetalle = $ventana.data('url');
    var urlBuscar = $ventana.data('urlBuscar');
    // Detalles ya pedidos en esta pantalla: volver a uno no consulta otra vez.
    var cacheDetalle = {};
    var temporizador = null;
    // Numero de la ultima busqueda enviada: una respuesta vieja que llega
    // tarde no pisa la lista de una busqueda mas nueva.
    var ultimaBusqueda = 0;
    // Fila que abrio la ventana: su selector y los ids de las fichas
    // sugeridas para su item (las decide el servidor, ver #lista-sugeridas).
    var $selectFila = null;
    var idsSugeridasFila = [];

    function marcada() {
        return $lista.find('.list-group-item.active');
    }

    function mostrarDetalle(id) {
        if (cacheDetalle[id] !== undefined) {
            $contenido.html(cacheDetalle[id]);
            return;
        }

        $contenido.html('<div class="rd-nota">Cargando…</div>');

        $.get(urlDetalle, {id: id})
            .done(function (html) {
                cacheDetalle[id] = html;
                // Si mientras tanto se marco otra, no se pisa.
                if (String(marcada().data('id')) === String(id)) {
                    $contenido.html(html);
                }
            })
            .fail(function () {
                $contenido.html('<div class="alert alert-danger mb-0">No se pudo cargar la especificación. Recargue la página e intente de nuevo.</div>');
            });
    }

    function marcar($opcion) {
        $lista.find('.list-group-item').removeClass('active').attr('aria-selected', 'false');

        if ($opcion.length === 0) {
            $usar.prop('disabled', true);
            $recordarGrupo.addClass('d-none');
            return;
        }

        $opcion.addClass('active').attr('aria-selected', 'true');
        $opcion[0].scrollIntoView({block: 'nearest'});
        $usar.prop('disabled', false);

        // Solo se puede "recordar" en una ficha que aun no tiene item de referencia.
        $recordar.prop('checked', false);
        $recordarGrupo.toggleClass('d-none', $opcion.data('conReferencia') === 1);

        mostrarDetalle($opcion.data('id'));
    }

    /**
     * Arma la lista. fichas: [{id, texto, conReferencia}]. Las sugeridas para
     * el item de la fila van primero, bajo su propio titulo.
     */
    function pintar(fichas, mensajeVacio) {
        var sugeridas = [];
        var otras = [];

        $.each(fichas, function (i, ficha) {
            ($.inArray(Number(ficha.id), idsSugeridasFila) !== -1 ? sugeridas : otras).push(ficha);
        });

        $lista.empty();

        function grupo(titulo, lista) {
            if (lista.length === 0) {
                return;
            }

            // .text() escapa los textos: nunca se interpretan como HTML.
            $('<div class="list-group-item esp-previa-grupo"></div>').text(titulo).appendTo($lista);

            $.each(lista, function (i, ficha) {
                $('<button type="button" class="list-group-item list-group-item-action" role="option"></button>')
                    .text(ficha.texto)
                    .attr('data-id', ficha.id)
                    .attr('data-con-referencia', ficha.conReferencia ? 1 : 0)
                    .appendTo($lista);
            });
        }

        grupo('Sugeridas para este ítem', sugeridas);
        grupo(sugeridas.length > 0 ? 'Otras de la librería' : 'Librería', otras);

        if (fichas.length === 0) {
            $('<div class="rd-vacio"></div>').text(mensajeVacio).appendTo($lista);
            $contenido.empty();
        }

        // Queda marcada la ficha elegida en la fila, si esta en la lista; si no, la primera.
        var $elegida = $lista.find('[data-id="' + $selectFila.val() + '"]');
        marcar($elegida.length > 0 ? $elegida : $lista.find('.list-group-item-action').first());
    }

    // Sin texto: la ficha elegida en la fila y las sugeridas (salen del propio selector de la fila).
    function pintarIniciales() {
        var fichas = [];

        $selectFila.find('option').each(function () {
            var $opcion = $(this);
            var esSugerida = $opcion.parent().is('[data-sugeridas]');

            if ($opcion.val() !== '' && (esSugerida || $opcion.is(':selected'))) {
                fichas.push({
                    id: $opcion.val(),
                    texto: $opcion.text(),
                    conReferencia: $opcion.data('conReferencia') === 1
                });
            }
        });

        $resumen.text(fichas.length > 0 ? '' : 'Escriba arriba para buscar en toda la librería.');
        pintar(fichas, 'Este ítem no tiene una especificación elegida ni fichas sugeridas.');
    }

    function buscar() {
        var texto = $.trim($buscar.val());
        var numero = ++ultimaBusqueda;

        if (texto === '') {
            pintarIniciales();
            return;
        }

        $resumen.text('Buscando…');

        $.getJSON(urlBuscar, {q: texto})
            .done(function (respuesta) {
                if (numero !== ultimaBusqueda) {
                    return;
                }

                var cantidad = respuesta.resultados.length;
                $resumen.text(respuesta.limiteAlcanzado
                    ? 'Se muestran las primeras ' + cantidad + ': agregue otra palabra para afinar.'
                    : cantidad + ' resultado(s)');
                pintar(respuesta.resultados, 'Ninguna ficha activa contiene todas esas palabras.');
            })
            .fail(function () {
                if (numero === ultimaBusqueda) {
                    $resumen.text('No se pudo buscar. Recargue la página e intente de nuevo.');
                }
            });
    }

    $(document).on('click', '.lista-item-ver-ficha', function () {
        var $fila = $(this).closest('tr');

        $selectFila = $fila.find('.lista-item-esp');
        idsSugeridasFila = ($('#lista-sugeridas').data('mapa') || {})[$fila.find('.lista-item-subcatalogo').val()] || [];

        // .text() escapa el nombre: nunca se interpreta como HTML.
        $('#esp-previa-titulo').text('Especificación técnica — ' + $fila.find('.lista-item-nombre').text());
        $buscar.val('');
        pintarIniciales();
        modal.show();
    });

    // El cursor va al buscador cuando la ventana termino de abrirse.
    $ventana.on('shown.bs.modal', function () {
        $buscar.trigger('focus');
    });

    // Espera 300 ms despues de la ultima tecla: no consulta con cada letra.
    $buscar.on('input', function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(buscar, 300);
    });

    // Flechas: recorren la lista. Enter: usa la marcada.
    $buscar.on('keydown', function (evento) {
        var $opciones = $lista.find('.list-group-item-action');
        var posicion = $opciones.index(marcada());

        if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
            evento.preventDefault();
            posicion += evento.key === 'ArrowDown' ? 1 : -1;
            if (posicion >= 0 && posicion < $opciones.length) {
                marcar($opciones.eq(posicion));
            }
        } else if (evento.key === 'Enter') {
            evento.preventDefault();
            if (!$usar.prop('disabled')) {
                $usar.trigger('click');
            }
        }
    });

    $lista.on('click', '.list-group-item-action', function () {
        marcar($(this));
    });

    $lista.on('dblclick', '.list-group-item-action', function () {
        $usar.trigger('click');
    });

    // Un cambio hecho a mano en el selector de la fila descarta el "recordar"
    // pendiente: era para la ficha elegida desde la ventana.
    $(document).on('change', '.lista-item-esp', function () {
        var $fila = $(this).closest('tr');

        $fila.find('.lista-item-recordar').val('');
        $fila.find('.lista-item-recordar-nota').addClass('d-none');
    });

    $usar.on('click', function () {
        var $opcion = marcada();

        if ($selectFila === null || $opcion.length === 0) {
            return;
        }

        var id = String($opcion.data('id'));
        var $fila = $selectFila.closest('tr');

        // Una ficha creada despues de abrir esta pantalla no esta en el selector de la fila.
        if ($selectFila.find('option[value="' + id + '"]').length === 0) {
            window.alert('Esa especificación se agregó a la librería después de abrir esta pantalla. Guarde la lista y recargue la página para poder elegirla.');
            return;
        }

        if ($selectFila.val() !== id) {
            // trigger('change'): lista-items.js actualiza "Ver documento", el
            // contador y el aviso de cambios sin guardar (y el handler de
            // arriba limpia un "recordar" anterior).
            $selectFila.val(id).trigger('change');
        }

        if ($recordar.is(':checked') && !$recordarGrupo.hasClass('d-none')) {
            $fila.find('.lista-item-recordar-nota').removeClass('d-none');
            // trigger('input'): lista-items.js marca "Hay cambios sin guardar"
            // (hace falta aunque la ficha ya fuera la elegida y no hubo "change").
            $fila.find('.lista-item-recordar').val('1').trigger('input');
        }

        modal.hide();
    });
});
