/**
 * Comportamiento del formulario de proyecto (views/proyecto/_form.php).
 *
 *  1. Area de intervencion, de dos formas:
 *     - buscador: se escribe parte del nombre del edificio o del codigo o
 *       nombre del ambiente ("ciencias y tecnologia", "E303"). Cada
 *       resultado muestra arriba lo que se busco y abajo, en gris, donde
 *       esta. Un edificio encontrado se pone en el paso a paso (para elegir
 *       bloque, piso o agregarlo entero); un ambiente se agrega directamente;
 *     - paso a paso: edificio -> bloque -> piso -> ambiente. Edificio y
 *       ambiente no son <select> (sus listas son largas y la del navegador
 *       se abre muy alta): son campos que filtran al escribir, con su lista
 *       debajo (ver crearCombo()). En el edificio, los ya usados en
 *       proyectos van primero. Al elegir uno se piden al servidor los de
 *       abajo (urls en los data-url-* del <form>). Se puede agregar el area
 *       en cualquier paso: un edificio entero, un bloque, un piso o un ambiente.
 *     - a mano: si el edificio (o el bloque, piso o espacio) no esta en
 *       UsfxGEO, por ej. porque esta en construccion. Al abrir el bloque se
 *       copia lo elegido en el paso a paso, para escribir solo lo que falta.
 *     Las areas elegidas se muestran agrupadas por edificio y bloque; cada
 *     una lleva sus <input type="hidden">: los 4 id, o "manual" y los 4
 *     nombres si se escribio a mano.
 *  2. Equipo del proyecto: "+ Agregar persona" copia la fila modelo
 *     (<template>) con una clave nueva; "Quitar" la borra. Si queda una sola
 *     fila, o se quita la responsable, se marca la primera como responsable.
 *  3. Al elegir el proyecto de fase anterior se muestra su resumen y el
 *     numero de fase que le correspondera a este proyecto.
 *
 * Esto es solo comodidad para el usuario: el servidor vuelve a validar todo
 * en ProyectoService (que el area exista y este vigente en UsfxGEO, etc.).
 */
$(function () {
    var $form = $('#proyecto-form');

    if ($form.length === 0) {
        return;
    }

    var $areas = $('#proyecto-areas');
    var $bloque = $('#proyecto-area-bloque');
    var $nivel = $('#proyecto-area-nivel');
    var $agregarArea = $('#proyecto-agregar-area');
    var $buscar = $('#proyecto-buscar-ambiente');
    var $resultados = $('#proyecto-resultados-ambiente');
    var $equipo = $('#proyecto-equipo tbody');
    var siguienteFila = parseInt($('#proyecto-equipo').data('siguiente'), 10) || 0;
    var siguienteArea = parseInt($areas.data('siguiente'), 10) || 0;

    // Minusculas y sin tildes: "Tecnología" y "tecnologia" son lo mismo.
    function normalizar(texto) {
        return String(texto).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    /**
     * Campo de texto que filtra una lista al escribir y la muestra debajo,
     * con alto fijo (en vez de un <select>, cuya lista abre el navegador).
     * El id elegido queda en un <input type="hidden">.
     *
     * cfg: $texto, $valor (hidden), $lista (<ul>), alCambiar (funcion que
     * se llama al elegir o anular), sinResultados (texto), y opcionalmente
     * gruposSinTexto(items): lista de {titulo, items} para cuando no hay
     * nada escrito (el edificio muestra primero los usados).
     *
     * Cada item es {id, nombre, nota?}.
     */
    function crearCombo(cfg) {
        var items = [];
        var nombreElegido = '';

        function itemLi(item) {
            var $li = $('<li>').text(item.nombre).data('item', item);

            if (item.nota) {
                $li.append($('<span class="rd-nota">').text(' · ' + item.nota));
            }

            return $li;
        }

        function mostrar() {
            // Deshabilitado (ej. "Espacio" antes de elegir el piso): no hay lista que mostrar.
            if (cfg.$texto.prop('disabled')) {
                return;
            }

            var palabras = normalizar(cfg.$texto.val()).split(/\s+/).filter(Boolean);

            cfg.$lista.empty().removeClass('d-none');

            if (palabras.length === 0 && cfg.gruposSinTexto) {
                $.each(cfg.gruposSinTexto(items), function (_, grupo) {
                    if (grupo.titulo) {
                        cfg.$lista.append($('<li class="grupo">').text(grupo.titulo));
                    }

                    $.each(grupo.items, function (_, item) { cfg.$lista.append(itemLi(item)); });
                });
            } else {
                var encontrados = items.filter(function (item) {
                    var nombre = normalizar(item.nombre);

                    return palabras.every(function (p) { return nombre.indexOf(p) !== -1; });
                });

                if (encontrados.length === 0) {
                    cfg.$lista.append($('<li class="vacio">').text(cfg.sinResultados));
                }

                $.each(encontrados, function (_, item) { cfg.$lista.append(itemLi(item)); });
            }

            cfg.$lista.scrollTop(0);
        }

        function cerrar() {
            cfg.$lista.addClass('d-none').empty();
        }

        function elegir(id, nombre) {
            nombreElegido = nombre;
            cfg.$texto.val(nombre);
            cfg.$valor.val(id);
            cerrar();
            cfg.alCambiar();
        }

        // Deja el campo sin eleccion (sin avisar: lo usa quien ya sabe que cambio).
        function limpiar() {
            nombreElegido = '';
            cfg.$texto.val('');
            cfg.$valor.val('');
            cerrar();
        }

        cfg.$texto.on('focus click', function () {
            // Con algo ya elegido se muestra la lista completa (para cambiarlo).
            if (cfg.$valor.val()) {
                cfg.$texto.select();
            }

            mostrar();
        });

        cfg.$texto.on('input', function () {
            // Al cambiar el texto, lo elegido deja de valer hasta elegir otro.
            if (cfg.$valor.val() && cfg.$texto.val() !== nombreElegido) {
                cfg.$valor.val('');
                nombreElegido = '';
                cfg.alCambiar();
            }

            mostrar();
        });

        cfg.$lista.on('mousedown', 'li', function (evento) {
            // mousedown y no click: el campo no pierde el foco antes de elegir.
            evento.preventDefault();
            var item = $(this).data('item');

            if (item) {
                elegir(item.id, item.nombre);
            }
        });

        // Flechas para moverse en la lista, Enter para elegir, Escape para cerrar.
        cfg.$texto.on('keydown', function (evento) {
            var $opciones = cfg.$lista.find('li').filter(function () { return $(this).data('item'); });
            var $activa = $opciones.filter('.activo');
            var indice = $opciones.index($activa);

            if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
                evento.preventDefault();

                if (cfg.$lista.hasClass('d-none')) {
                    mostrar();
                    return;
                }

                indice = evento.key === 'ArrowDown' ? Math.min(indice + 1, $opciones.length - 1) : Math.max(indice - 1, 0);
                $activa.removeClass('activo');
                var $nueva = $opciones.eq(indice).addClass('activo');

                if ($nueva.length) {
                    $nueva[0].scrollIntoView({block: 'nearest'});
                }
            } else if (evento.key === 'Enter') {
                evento.preventDefault();
                var elegida = ($activa.length ? $activa : ($opciones.length === 1 ? $opciones : $())).data('item');

                if (elegida) {
                    elegir(elegida.id, elegida.nombre);
                }
            } else if (evento.key === 'Escape') {
                cerrar();
            }
        });

        cfg.$texto.on('blur', function () {
            cerrar();

            // Texto a medias sin elegir: vuelve a lo elegido (o queda vacio).
            if (cfg.$texto.val() !== nombreElegido) {
                cfg.$texto.val(cfg.$valor.val() ? nombreElegido : '');
            }
        });

        return {
            elegir: elegir,
            limpiar: limpiar,
            nombre: function () { return cfg.$valor.val() ? nombreElegido : null; },
            // Cambia las opciones; sin ninguna, el campo queda deshabilitado.
            cargar: function (nuevos) {
                items = nuevos || [];
                limpiar();
                cfg.$texto.prop('disabled', items.length === 0);
            }
        };
    }

    // ---- Lista de areas elegidas, agrupada por edificio y bloque ----

    // Misma regla que ProyectoAreaIntervencion::grupo() y detalle().
    function grupoDe(area) {
        return $.grep([area.edificio, area.bloque], Boolean).join(' · ');
    }

    function detalleDe(area) {
        if (area.ambiente) {
            return area.nivel ? area.nivel + ': ' + area.ambiente : area.ambiente;
        }

        if (area.nivel) {
            return area.nivel + ' (todo el piso)';
        }

        return area.bloque ? '(todo el bloque)' : '(todo el edificio)';
    }

    // Vuelve a armar los grupos con los .rd-area que haya (al cargar, al
    // agregar y al quitar). Mueve los mismos elementos: sus hidden no cambian.
    function reagrupar() {
        var grupos = {};
        var orden = [];

        $areas.find('.rd-area').each(function () {
            var grupo = String($(this).attr('data-grupo'));

            if (!grupos[grupo]) {
                grupos[grupo] = [];
                orden.push(grupo);
            }

            grupos[grupo].push(this);
        });

        $areas.find('.rd-area').detach();
        $areas.empty();

        $.each(orden, function (_, grupo) {
            $('<div class="rd-area-grupo">')
                .append($('<div class="rd-area-titulo">').text(grupo))
                .append(grupos[grupo])
                .appendTo($areas);
        });
    }

    function yaEsta(valor) {
        return $areas.find('.rd-area').filter(function () {
            return String($(this).attr('data-valor')) === String(valor);
        }).length > 0;
    }

    // Para no repetir un area. Misma regla que el data-valor de _form.php:
    // las escritas a mano se comparan por sus nombres, sin mayusculas.
    function valorDe(area) {
        if (area.manual) {
            return ('manual|' + [area.edificio, area.bloque || '', area.nivel || '', area.ambiente || ''].join('|')).toLowerCase();
        }

        return [area.idEdificio, area.idBloqueArea || '', area.numeroNivel || '', area.idEspacio || ''].join('|');
    }

    // area = {idEdificio, idBloqueArea, numeroNivel, idEspacio, edificio, bloque, nivel, ambiente}
    // o, escrita a mano, {manual: true, edificio, bloque, nivel, ambiente}.
    function agregarArea(area) {
        var valor = valorDe(area);

        if (yaEsta(valor)) {
            return;
        }

        var nombre = $areas.data('nombre') + '[' + siguienteArea++ + ']';
        var campos = area.manual ? ['manual', 'edificio', 'bloque', 'nivel', 'ambiente'] : ['idEdificio', 'idBloqueArea', 'numeroNivel', 'idEspacio'];
        var $area = $('<div class="rd-area">')
            .toggleClass('rd-area-manual', !!area.manual)
            .attr({'data-valor': valor, 'data-grupo': grupoDe(area), title: $.grep([area.edificio, area.bloque, area.nivel, area.ambiente], Boolean).join(' – ')})
            .append($('<span class="rd-area-detalle">').text(detalleDe(area)));

        if (area.manual) {
            $area.append($('<span class="rd-area-marca">').text('a mano'));
        }

        $.each(campos, function (_, campo) {
            $area.append($('<input type="hidden">').attr('name', nombre + '[' + campo + ']').val(campo === 'manual' ? '1' : (area[campo] || '')));
        });

        $area.append($('<button type="button" class="x" aria-label="Quitar">').text('×')).appendTo($areas);
        reagrupar();
    }

    $areas.on('click', '.rd-area .x', function () {
        $(this).closest('.rd-area').remove();
        reagrupar();
    });

    reagrupar();

    // ---- Area paso a paso ----

    function llenarSelect($select, opciones) {
        $select.find('option:not(:first)').remove();

        $.each(opciones || [], function (_, opcion) {
            $select.append($('<option>').val(opcion.id).text(opcion.nombre));
        });

        $select.val('').prop('disabled', !opciones || opciones.length === 0);
    }

    // UsfxGEO no responde (el servidor contesta 503 con {error}): se muestra el aviso arriba del buscador,
    // en vez de dejar la lista vacia sin decir nada.
    function avisoInfraestructura(xhr) {
        var texto = (xhr && xhr.responseJSON && xhr.responseJSON.error)
            || 'No se pudo consultar la infraestructura de la universidad. Puede escribir el área a mano o intentar más tarde.';

        $('#proyecto-aviso-infraestructura').text(texto).removeClass('d-none');
    }

    function cargarSelect($select, url, datos) {
        llenarSelect($select, []);

        $.getJSON(url, datos).done(function (opciones) {
            llenarSelect($select, opciones);
        }).fail(avisoInfraestructura);
    }

    function textoElegido($select) {
        return $select.val() ? $select.find('option:selected').text() : null;
    }

    var USADOS_MAXIMO = 10;

    var ambiente = crearCombo({
        $texto: $('#proyecto-area-ambiente-texto'),
        $valor: $('#proyecto-area-ambiente'),
        $lista: $('#proyecto-lista-ambientes'),
        sinResultados: 'Ningún espacio de este piso con ese nombre.',
        alCambiar: function () {}
    });

    var edificio = crearCombo({
        $texto: $('#proyecto-area-edificio-texto'),
        $valor: $('#proyecto-area-edificio'),
        $lista: $('#proyecto-lista-edificios'),
        sinResultados: 'Ningún edificio vigente con ese nombre.',
        // Sin texto: primero los usados en proyectos, despues todos.
        gruposSinTexto: function (items) {
            var usados = items.filter(function (e) { return e.usos > 0; })
                .sort(function (a, b) { return b.usos - a.usos || a.nombre.localeCompare(b.nombre); })
                .slice(0, USADOS_MAXIMO);

            return usados.length === 0
                ? [{titulo: null, items: items}]
                : [{titulo: 'Usados en proyectos', items: usados}, {titulo: 'Todos los edificios', items: items}];
        },
        alCambiar: function () {
            var id = $('#proyecto-area-edificio').val();

            llenarSelect($nivel, []);
            ambiente.cargar([]);
            $agregarArea.prop('disabled', !id);

            if (id) {
                cargarSelect($bloque, $form.data('url-bloques'), {idEdificio: id});
            } else {
                llenarSelect($bloque, []);
            }
        }
    });

    // Los edificios vienen en la pagina; la nota dice en cuantos proyectos se usaron.
    edificio.cargar($.map($('#proyecto-area-edificio-texto').data('edificios') || [], function (e) {
        return {id: e.id, nombre: e.nombre, usos: e.usos, nota: e.usos > 0 ? e.usos + (e.usos === 1 ? ' proyecto' : ' proyectos') : null};
    }));

    $bloque.on('change', function () {
        ambiente.cargar([]);

        if ($bloque.val()) {
            cargarSelect($nivel, $form.data('url-niveles'), {idBloqueArea: $bloque.val()});
        } else {
            llenarSelect($nivel, []);
        }
    });

    $nivel.on('change', function () {
        ambiente.cargar([]);

        if ($nivel.val()) {
            $.getJSON($form.data('url-ambientes'), {idBloqueArea: $bloque.val(), numeroNivel: $nivel.val()}).done(function (opciones) {
                ambiente.cargar(opciones);
            }).fail(avisoInfraestructura);
        }
    });

    $agregarArea.on('click', function () {
        var idEdificio = $('#proyecto-area-edificio').val();

        if (!idEdificio) {
            return;
        }

        var conBloque = !!$bloque.val();
        var conNivel = conBloque && !!$nivel.val();

        agregarArea({
            idEdificio: idEdificio,
            idBloqueArea: $bloque.val(),
            numeroNivel: conBloque ? $nivel.val() : '',
            idEspacio: conNivel ? $('#proyecto-area-ambiente').val() : '',
            edificio: edificio.nombre(),
            bloque: textoElegido($bloque),
            nivel: conBloque ? textoElegido($nivel) : null,
            ambiente: conNivel ? ambiente.nombre() : null
        });

        // Se dejan edificio, bloque y piso elegidos: lo comun es agregar otro
        // ambiente del mismo piso.
        ambiente.limpiar();
    });

    // ---- Area escrita a mano ----

    var $manual = $('#proyecto-area-manual');
    var $abrirManual = $('#proyecto-abrir-manual');
    var manual = {
        edificio: $('#proyecto-manual-edificio'),
        bloque: $('#proyecto-manual-bloque'),
        nivel: $('#proyecto-manual-nivel'),
        ambiente: $('#proyecto-manual-ambiente')
    };

    function cerrarManual() {
        $manual.addClass('d-none');
        $abrirManual.removeClass('d-none');
        $.each(manual, function (_, $campo) { $campo.val('').removeClass('is-invalid'); });
    }

    $abrirManual.on('click', function () {
        // Lo elegido en el paso a paso se copia: si el edificio existe pero
        // el bloque nuevo no, solo se escribe el bloque.
        var conBloque = !!$bloque.val();

        manual.edificio.val(edificio.nombre() || '');
        manual.bloque.val(textoElegido($bloque) || '');
        manual.nivel.val(conBloque ? (textoElegido($nivel) || '') : '');
        manual.ambiente.val(conBloque && $nivel.val() ? (ambiente.nombre() || '') : '');

        $abrirManual.addClass('d-none');
        $manual.removeClass('d-none');
        manual.edificio.trigger('focus');
    });

    $('#proyecto-cerrar-manual').on('click', cerrarManual);

    $('#proyecto-agregar-manual').on('click', function () {
        var area = {manual: true};

        $.each(manual, function (campo, $campo) {
            area[campo] = $.trim($campo.val()).replace(/\s+/g, ' ') || null;
        });

        if (!area.edificio) {
            manual.edificio.addClass('is-invalid').trigger('focus');
            return;
        }

        agregarArea(area);
        cerrarManual();
    });

    // Enter en estos campos agrega el area, no envia el formulario.
    $manual.on('keydown', 'input', function (evento) {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            $('#proyecto-agregar-manual').trigger('click');
        }
    });

    // Los campos de "escribirla a mano" no tienen name: solo llegan al servidor
    // si se pulsa "+ Agregar área escrita a mano". Si se guarda con el edificio
    // escrito sin pulsarlo, el area se perdia sin aviso (2026-11-03); ahora se
    // agrega sola antes de enviar (cerrarManual() vacia los campos, asi que el
    // texto que queda es uno que todavia no se agrego).
    $form.on('submit', function () {
        if (!$manual.hasClass('d-none') && $.trim(manual.edificio.val()) !== '') {
            $('#proyecto-agregar-manual').trigger('click');
        }
    });

    // ---- Buscador de edificios y ambientes ----

    var espera = null;
    var ultimaBusqueda = '';

    function cerrarResultados() {
        $resultados.addClass('d-none').empty();
    }

    // Resultado en dos renglones: arriba lo que se busco, abajo donde esta.
    function resultado(titulo, ubicacion) {
        return $('<li class="rd-resultado">')
            .append($('<div class="rd-resultado-titulo">').text(titulo))
            .append($('<div class="rd-resultado-sub">').text(ubicacion));
    }

    $buscar.on('input', function () {
        var texto = $.trim($buscar.val());

        clearTimeout(espera);

        if (texto.length < 2) {
            cerrarResultados();
            return;
        }

        // Espera a que se deje de escribir para no consultar letra por letra.
        espera = setTimeout(function () {
            ultimaBusqueda = texto;

            $.getJSON($form.data('url-buscar'), {q: texto}).done(function (respuesta) {
                if (texto !== ultimaBusqueda) {
                    return; // llego la respuesta de una busqueda anterior
                }

                $resultados.empty().removeClass('d-none');

                if (respuesta.error) {
                    avisoInfraestructura({responseJSON: respuesta});
                    $resultados.append($('<li class="vacio">').text('No se pudo buscar: la infraestructura de la universidad no responde.'));

                    return;
                }

                if (respuesta.edificios.length === 0 && respuesta.ambientes.length === 0) {
                    $resultados.append($('<li class="vacio">').text('Sin resultados en los edificios y espacios vigentes.'));
                }

                if (respuesta.edificios.length > 0) {
                    $resultados.append($('<li class="grupo">').text('Edificios'));

                    $.each(respuesta.edificios, function (_, e) {
                        resultado(e.edificio, 'Elegir bloque, piso o espacio, o agregarlo entero')
                            .data('edificio', e)
                            .appendTo($resultados);
                    });
                }

                if (respuesta.ambientes.length > 0) {
                    $resultados.append($('<li class="grupo">').text('Espacios'));

                    $.each(respuesta.ambientes, function (_, fila) {
                        resultado(fila.ambiente, $.grep([fila.edificio, fila.bloque, fila.nivel], Boolean).join(' · '))
                            .data('area', fila)
                            .appendTo($resultados);
                    });
                }

                if (respuesta.hayMas) {
                    $resultados.append($('<li class="vacio">').text('Hay más espacios: escriba algo más preciso (ej. el código del espacio o el bloque).'));
                }
            });
        }, 300);
    });

    $resultados.on('click', 'li.rd-resultado', function () {
        var e = $(this).data('edificio');

        if (e) {
            // El edificio se pone en el paso a paso: carga sus bloques y
            // habilita "+ Agregar área" (para agregarlo entero).
            edificio.elegir(e.idEdificio, e.edificio);
        } else {
            agregarArea($(this).data('area'));
        }

        $buscar.val('');
        cerrarResultados();
    });

    // Enter en el buscador no debe enviar el formulario.
    $buscar.on('keydown', function (evento) {
        if (evento.key === 'Enter') {
            evento.preventDefault();
        } else if (evento.key === 'Escape') {
            cerrarResultados();
        }
    });

    $(document).on('click', function (evento) {
        if (!$(evento.target).closest('.rd-buscar-ambiente').length) {
            cerrarResultados();
        }
    });

    // ---- Equipo del proyecto ----

    // Si nadie quedo como responsable, lo es la primera fila.
    function asegurarResponsable() {
        var $radios = $equipo.find('input[type="radio"]');

        if ($radios.length > 0 && $radios.filter(':checked').length === 0) {
            $radios.first().prop('checked', true);
        }
    }

    $('#proyecto-agregar-persona').on('click', function () {
        var html = $('#proyecto-equipo-fila-modelo').html().replace(/__n__/g, String(siguienteFila));
        siguienteFila++;

        var $fila = $(html).appendTo($equipo);
        asegurarResponsable();
        $fila.find('select').first().trigger('focus');
    });

    $equipo.on('click', '.rd-equipo-quitar', function () {
        $(this).closest('tr').remove();
        asegurarResponsable();
    });

    // ---- Chips de las areas ----

    // Delegado en el formulario: tambien funciona con los chips agregados despues.
    $form.on('click', '.chip .x', function () {
        $(this).closest('.chip').remove();
    });
});
