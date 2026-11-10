/**
 * Comportamiento de la lista de items del proyecto (views/lista-items/index.php).
 *
 *  1. Buscador: filtra en el navegador la lista del subcatalogo.
 *  2. Boton "+": agrega una fila al grupo de la clasificacion del item,
 *     copiando la fila modelo (<template>) con un indice nuevo en
 *     items[n][...]. Si la clasificacion aun no tiene grupo, lo crea.
 *  3. Boton "×": quita la fila (y el grupo, si queda vacio).
 *     "Cambiar ítem" (filas ya guardadas): el siguiente "+" del panel pone
 *     ese item en la fila en vez de agregar otra; la fila pasa al grupo de
 *     su clasificacion y muestra "Antes: …" con "Deshacer".
 *  4. Antes de enviar: las cantidades de la lista deben ser numeros >= 0, y
 *     las de los materiales de la DTIC, mayores que 0 (como el servidor).
 *  5. Avisa que hay cambios sin guardar.
 *  6. Especificacion tecnica por fila (opcional): al elegirla muestra el
 *     enlace "Ver documento" y actualiza el contador; en las filas nuevas
 *     pone primero las sugeridas para el item (su item de referencia) y
 *     despues las de su categoria. "Ver detalle" lo maneja
 *     especificacion-vista-previa.js.
 *  7. Secciones sin precio debajo de la lista (materiales que aporta la
 *     DTIC, _materiales-dtic.php, y trabajos complementarios,
 *     _condiciones.php), agrupadas como la lista: materiales por categoria,
 *     trabajos por tipo. En materiales, un buscador del subcatalogo (los
 *     items del panel de la izquierda) agrega la fila con nombre, unidad y
 *     categoria fijos; "Escribirlo a mano" agrega una fila editable. Cambiar
 *     la categoria o el tipo de una fila la pasa a ese grupo. Se guardan con
 *     la lista.
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
        $('.lista-sin-guardar').removeClass('d-none');
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
        $('#lista-total-esp').text($tabla.find('.lista-item-esp').filter(function () {
            return $(this).val() !== '';
        }).length);
        $('#lista-vacia').toggleClass('d-none', total > 0);
    }

    // Muestra u oculta el enlace al documento de la especificacion elegida.
    function actualizarEnlaceDocumento($select) {
        var url = $select.find('option:selected').data('url');
        var $enlace = $select.siblings('.lista-item-doc');

        if (url) {
            $enlace.attr('href', url).removeClass('d-none');
        } else {
            $enlace.attr('href', '#').addClass('d-none');
        }
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

    // ---- "Cambiar ítem": otro item del subcatalogo en una fila ya guardada ----
    // Mientras $filaACambiar no es null, el "+" del panel reemplaza el item de
    // esa fila en vez de agregar una nueva. Al guardar, el servidor reemite su
    // formulario de especificacion con el mismo codigo (ListaItemsService::cambiarItem()).
    var $filaACambiar = null;

    function salirDeCambiar() {
        if ($filaACambiar !== null) {
            $filaACambiar.removeClass('lista-item-cambiando');
        }
        $filaACambiar = null;
        $('#lista-cambiando').addClass('d-none');
    }

    // Pone en la fila el item elegido: nombre, unidad, id y el grupo de su clasificacion.
    function ponerItem($fila, item) {
        var $grupoAnterior = $fila.closest('tbody');

        $fila.find('.lista-item-subcatalogo').val(item.id);
        $fila.find('.lista-item-nombre').text(item.nombre);
        $fila.find('.lista-item-unidad').text(item.unidad);

        if (String($grupoAnterior.data('categoria')) !== String(item.categoria)) {
            grupoDe(String(item.categoria), item.categoriaNombre).find('tr.subtotal-row').before($fila);

            if ($grupoAnterior.find('tr.lista-item').length === 0) {
                $grupoAnterior.remove();
            }
        }

        actualizarTotales();
        marcarCambios();
    }

    $tabla.on('click', '.lista-item-cambiar', function () {
        var $fila = $(this).closest('tr');
        var $grupo = $fila.closest('tbody');

        // Lo que tenia al cargar la pagina, para "Deshacer" (se guarda una sola vez).
        if (!$fila.data('original')) {
            $fila.data('original', {
                id: String($fila.find('.lista-item-subcatalogo').val()),
                nombre: $fila.find('.lista-item-nombre').text(),
                unidad: $fila.find('.lista-item-unidad').text(),
                categoria: String($grupo.data('categoria')),
                categoriaNombre: $grupo.find('.cat-row td').text()
            });
        }

        salirDeCambiar();
        $filaACambiar = $fila.addClass('lista-item-cambiando');
        $('#lista-cambiando-nombre').text($fila.find('.lista-item-nombre').text());
        $('#lista-cambiando').removeClass('d-none');
        $('#lista-buscar').trigger('focus');
    });

    $('#lista-cambiando-cancelar').on('click', salirDeCambiar);

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $filaACambiar !== null) {
            salirDeCambiar();
        }
    });

    $tabla.on('click', '.lista-item-deshacer', function (e) {
        e.preventDefault();
        var $fila = $(this).closest('tr');

        ponerItem($fila, $fila.data('original'));
        $fila.find('.lista-item-cambio').addClass('d-none').empty();
    });

    function cambiarItemDeFila($fila, $origen) {
        var original = $fila.data('original');
        var item = {
            id: String($origen.data('id')),
            nombre: String($origen.data('nombre')),
            unidad: String($origen.data('unidad') || ''),
            categoria: String($origen.data('categoria')),
            categoriaNombre: String($origen.data('categoriaNombre'))
        };
        var $nota = $fila.find('.lista-item-cambio');

        ponerItem($fila, item);
        salirDeCambiar();

        // Volvio a elegir el que tenia: no hay cambio.
        if (item.id === original.id) {
            $nota.addClass('d-none').empty();
            return;
        }

        // .text() escapa los nombres: nunca se interpretan como HTML.
        var antes = original.nombre + (original.unidad ? ' (' + original.unidad + ')' : '');
        var codigo = $.trim($fila.find('.lista-item-net strong').first().text());
        var texto = 'Antes: ' + antes + '. ' + (codigo
            ? 'Al guardar, el formulario ' + codigo + ' se corrige con el mismo código y la versión siguiente.'
            : 'Se cambia al guardar.');

        $nota.empty().text(texto + ' ')
            .append($('<a href="#" class="lista-item-deshacer">').text('Deshacer'))
            .removeClass('d-none');
        $fila.find('.qty').trigger('focus').trigger('select');
    }

    $('#lista-subcatalogo').on('click', '.addbtn', function () {
        var $origen = $(this).closest('.row-item');

        if ($filaACambiar !== null) {
            cambiarItemDeFila($filaACambiar, $origen);
            return;
        }

        var $fila = $(filaModelo.replace(/__n__/g, String(siguiente++)));

        $fila.find('.lista-item-subcatalogo').val($origen.data('id'));
        $fila.find('.lista-item-nombre').text($origen.data('nombre'));
        $fila.find('.lista-item-unidad').text($origen.data('unidad'));
        $fila.find('.qty').val('1');

        // Las especificaciones de la categoria del item, primero (despues
        // de la opcion "Sin especificacion").
        var $selectEsp = $fila.find('.lista-item-esp');
        $selectEsp.find('optgroup[data-categoria="' + $origen.data('categoria') + '"]')
            .insertAfter($selectEsp.find('option[value=""]'));

        // Y antes que todas, las sugeridas para este item: se MUEVEN a
        // "Sugeridas para este ítem", como en las filas que arma el servidor.
        // CUALES son lo decide el servidor (EspecificacionService::selector(),
        // mapa en #lista-sugeridas); aqui solo se acomodan. Los grupos que
        // quedan vacios se quitan.
        var idsSugeridas = ($('#lista-sugeridas').data('mapa') || {})[$origen.data('id')] || [];
        var $sugeridas = $selectEsp.find('option').filter(function () {
            return $.inArray(Number($(this).val()), idsSugeridas) !== -1;
        });

        if ($sugeridas.length > 0) {
            $('<optgroup label="Sugeridas para este ítem" data-sugeridas="1"></optgroup>')
                .append($sugeridas)
                .insertAfter($selectEsp.find('option[value=""]'));
            $selectEsp.find('optgroup').filter(function () {
                return $(this).children().length === 0;
            }).remove();
        }

        grupoDe(String($origen.data('categoria')), String($origen.data('categoriaNombre')))
            .find('tr.subtotal-row').before($fila);

        actualizarTotales();
        marcarCambios();
        $fila.find('.qty').trigger('focus').trigger('select');
    });

    $tabla.on('click', '.rd-quitar', function () {
        var $grupo = $(this).closest('tbody');

        if ($filaACambiar !== null && $filaACambiar.is($(this).closest('tr'))) {
            salirDeCambiar();
        }

        $(this).closest('tr').remove();

        if ($grupo.find('tr.lista-item').length === 0) {
            $grupo.remove();
        }

        actualizarTotales();
        marcarCambios();
    });

    $tabla.on('input', 'input', marcarCambios);

    // ---- Secciones sin precio: materiales que aporta la DTIC y trabajos complementarios ----
    // Minusculas y sin tildes: "Canaleta" y "canaleta" son lo mismo.
    function normalizar(texto) {
        return String(texto).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    $('.lista-seccion').each(function () {
        var $seccion = $(this);
        var $tablaSeccion = $seccion.find('table');
        var columnas = $tablaSeccion.find('thead th').length;
        // Indice de la proxima fila (materiales[n] / condiciones[n]); solo crece.
        var siguienteFila = parseInt($seccion.data('siguiente'), 10) || 0;

        function nuevaFila(selectorModelo) {
            return $($seccion.find(selectorModelo).html().replace(/__n__/g, String(siguienteFila++)));
        }

        // Grupo (subtitulo o tipo) con esa clave; si no existe, se crea al final.
        function grupoDe(clave, nombre) {
            var $grupo = $tablaSeccion.find('tbody.seccion-grupo').filter(function () {
                return String($(this).attr('data-grupo')) === String(clave);
            });

            if ($grupo.length === 0) {
                // .text() escapa el nombre: nunca se interpreta como HTML.
                $grupo = $('<tbody class="seccion-grupo">').attr('data-grupo', clave)
                    .append($('<tr class="cat-row">').append($('<td>').attr('colspan', columnas).text(nombre)));
                $tablaSeccion.append($grupo);
            }

            return $grupo;
        }

        // Quita los grupos vacios, cuenta las filas y muestra "Todavia no hay".
        function actualizar() {
            $tablaSeccion.find('tbody.seccion-grupo').filter(function () {
                return $(this).find('tr.seccion-fila').length === 0;
            }).remove();

            var total = $tablaSeccion.find('tr.seccion-fila').length;

            $seccion.find('.seccion-total').text(total);
            $seccion.find('.seccion-vacia').toggleClass('d-none', total > 0);
            marcarCambios();
        }

        // Pone la fila en el grupo de lo elegido en su desplegable (categoria o tipo).
        function ubicar($fila) {
            var $select = $fila.find('.seccion-grupo-select');

            grupoDe($select.val(), $select.find('option:selected').text()).append($fila);
            actualizar();
        }

        $seccion.on('change', '.seccion-grupo-select', function () {
            ubicar($(this).closest('tr'));
        });

        $seccion.on('click', '.seccion-quitar', function () {
            $(this).closest('tr').remove();
            actualizar();
        });

        $seccion.on('input change', 'input, select', function () {
            // El buscador de materiales no es un dato del formulario.
            if (!$(this).hasClass('material-buscar')) {
                marcarCambios();
            }
        });

        // Trabajos: "+ Agregar trabajo" agrega una fila en el grupo del primer tipo.
        $seccion.on('click', '.seccion-agregar', function () {
            var $fila = nuevaFila('template.seccion-modelo');

            ubicar($fila);
            $fila.find('input').first().trigger('focus');
        });

        // ---- Materiales: buscador del subcatalogo y "Escribirlo a mano" ----
        var $buscar = $seccion.find('.material-buscar');
        var $resultados = $seccion.find('.material-resultados');

        if ($buscar.length === 0) {
            return;
        }

        // Los items del panel "Subcatálogo de redes" (solo los activos).
        var items = $('#lista-subcatalogo .row-item').map(function () {
            var $item = $(this);

            return {
                id: String($item.data('id')),
                nombre: String($item.data('nombre')),
                unidad: String($item.data('unidad') || ''),
                categoria: String($item.data('categoriaNombre') || 'Otros')
            };
        }).get();

        function cerrarResultados() {
            $resultados.addClass('d-none').empty();
        }

        function mostrarResultados() {
            var palabras = normalizar($buscar.val()).split(/\s+/).filter(Boolean);

            $resultados.empty().removeClass('d-none');

            if (palabras.length === 0) {
                $resultados.append($('<li class="vacio">').text('Escriba parte del nombre (ej. canaleta, patch cord).'));
                return;
            }

            var encontrados = items.filter(function (item) {
                var nombre = normalizar(item.nombre);

                return palabras.every(function (p) { return nombre.indexOf(p) !== -1; });
            });

            if (encontrados.length === 0) {
                $resultados.append($('<li class="vacio">').text('Ningún ítem activo con ese nombre. Puede escribirlo a mano.'));
            }

            $.each(encontrados.slice(0, 30), function (_, item) {
                $resultados.append($('<li>').data('item', item)
                    .append($('<div class="rd-resultado-titulo">').text(item.nombre))
                    .append($('<div class="rd-resultado-sub">').text(item.categoria + (item.unidad ? ' · ' + item.unidad : ''))));
            });

            if (encontrados.length > 30) {
                $resultados.append($('<li class="vacio">').text('Hay más: escriba otra palabra para acotar.'));
            }
        }

        // Agrega el item como fila (nombre y unidad fijos); si ya esta, va a su cantidad.
        function agregarItem(item) {
            var $fila = $tablaSeccion.find('tr.seccion-fila').filter(function () {
                return String($(this).attr('data-item')) === item.id;
            });

            if ($fila.length === 0) {
                $fila = nuevaFila('template.material-modelo-item').attr('data-item', item.id);
                $fila.find('.material-id').val(item.id);
                $fila.find('.material-grupo').val(item.categoria);
                $fila.find('.material-descripcion').val(item.nombre);
                $fila.find('.material-nombre').text(item.nombre);
                $fila.find('.material-unidad').val(item.unidad);
                $fila.find('.material-unidad-texto').text(item.unidad);
                $fila.find('.qty').val('1');
                grupoDe(item.categoria, item.categoria).append($fila);
                actualizar();
            }

            $buscar.val('');
            cerrarResultados();
            $fila.find('.qty').trigger('focus').trigger('select');
        }

        $buscar.on('focus input', mostrarResultados);

        // Enter elige el primero (y no envia el formulario); Escape cierra.
        $buscar.on('keydown', function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                var $primero = $resultados.find('li').filter(function () { return $(this).data('item'); }).first();

                if ($primero.length > 0) {
                    agregarItem($primero.data('item'));
                }
            } else if (evento.key === 'Escape') {
                cerrarResultados();
            }
        });

        // mousedown (no click): ocurre antes de que el campo pierda el foco y cierre la lista.
        $resultados.on('mousedown', 'li', function (evento) {
            evento.preventDefault();

            if ($(this).data('item')) {
                agregarItem($(this).data('item'));
            }
        });

        $buscar.on('blur', cerrarResultados);

        $seccion.on('click', '.material-agregar', function () {
            $buscar.trigger('focus');
        });

        $seccion.on('click', '.material-a-mano', function (evento) {
            evento.preventDefault();
            var $fila = nuevaFila('template.material-modelo-manual');

            $fila.find('.qty').val('1');
            ubicar($fila);
            $fila.find('.material-descripcion').trigger('focus');
        });
    });

    $tabla.on('change', '.lista-item-esp', function () {
        actualizarEnlaceDocumento($(this));
        // El lugar de entrega es de la especificacion: sin especificacion no se pide (ni su campo, si estaba abierto).
        var $celda = $(this).closest('td');
        var sinEspecificacion = $(this).val() === '';
        $celda.find('.lista-item-lugar').toggleClass('d-none', sinEspecificacion);
        if (sinEspecificacion) {
            $celda.find('.lista-lugar-campo').addClass('d-none');
        }
        actualizarTotales();
        marcarCambios();
    });

    $form.on('submit', function (evento) {
        var valido = true;

        $tabla.find('.qty').each(function () {
            var valor = $.trim($(this).val());
            var correcto = valor !== '' && !isNaN(Number(valor)) && Number(valor) >= 0;

            $(this).toggleClass('is-invalid', !correcto);
            valido = valido && correcto;
        });

        // Materiales de la DTIC: la misma regla que MaterialDticService::armar().
        // Una fila con algo escrito (item, descripcion, unidad o cantidad; la
        // categoria sola no cuenta) necesita cantidad de al menos 0.01 (el
        // 'min' => 0.01 de ProyectoMaterialDtic); una fila vacia se ignora.
        // min="0" se deja en el campo para que las flechitas vayan de 1 en 1,
        // por eso se revisa aqui.
        $('#lista-materiales-dtic tr.seccion-fila').each(function () {
            var $fila = $(this);
            var $cantidad = $fila.find('.qty');
            var tieneAlgo = $fila.find('[name$="[idSubcatalogo]"], [name$="[descripcion]"], [name$="[unidadMedida]"], .qty')
                .filter(function () { return $.trim($(this).val()) !== ''; })
                .length > 0;
            var valor = $.trim($cantidad.val());
            var correcto = !tieneAlgo || (valor !== '' && !isNaN(Number(valor)) && Number(valor) >= 0.01);

            $cantidad.toggleClass('is-invalid', !correcto);
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

    // Los PDF (lista y especificaciones) salen de la lista GUARDADA.
    $('#lista-imprimir, #lista-imprimir-esp').on('click', function (evento) {
        if (hayCambios && !window.confirm('Hay cambios sin guardar: la impresión mostrará la lista guardada. ¿Imprimir de todos modos?')) {
            evento.preventDefault();
        }
    });

    // Ir a otro paso del proyecto (barra de pasos) descarta lo no guardado.
    $('.rd-pasos a').on('click', function (evento) {
        if (hayCambios && !window.confirm('Hay cambios sin guardar en la lista y se perderán. ¿Ir a otro paso de todos modos?')) {
            evento.preventDefault();
        }
    });

    // Lugar de entrega de arriba de la lista (2026-11-10): es el que toma cada
    // fila que deja su lugar vacio, asi que se muestra como su texto de ejemplo
    // (vacio = el de la configuracion del formulario). Tambien en las filas que
    // se agregan despues (al entrar al campo).
    var $lugarProyecto = $('#lista-lugar-entrega');

    function lugarDeEjemplo() {
        return $.trim($lugarProyecto.val()) || $lugarProyecto.data('configuracion') || 'Sin lugar de entrega';
    }

    // Fila plegada: "Otro lugar de entrega" / "Cambiar" muestra el campo (2026-11-10).
    $tabla.on('click', '.lista-lugar-abrir', function () {
        var $celda = $(this).closest('td');
        $celda.find('.lista-lugar-resumen').addClass('d-none');
        $celda.find('.lista-lugar-campo').removeClass('d-none').find('input').trigger('focus');
    });

    $lugarProyecto.on('input', function () {
        $('#lista-items-form input[name$="[lugarEntrega]"]').attr('placeholder', lugarDeEjemplo());
    });
    $('#lista-items-form').on('focus', 'input[name$="[lugarEntrega]"]', function () {
        if ($lugarProyecto.length) {
            $(this).attr('placeholder', lugarDeEjemplo());
        }
    });
});
