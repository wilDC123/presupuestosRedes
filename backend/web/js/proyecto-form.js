/**
 * Comportamiento del formulario de proyecto (views/proyecto/_form.php).
 *
 *  1. Al cambiar la facultad se recargan los edificios; al cambiar el
 *     edificio se recargan los bloques disponibles. El arbol completo
 *     facultad -> edificios -> bloques viene en el atributo data-ubicacion
 *     del <form>, asi no hace falta pedirlo al servidor.
 *  2. Bloques y proyectistas se eligen de un desplegable y quedan como
 *     "chips". Cada chip contiene un <input type="hidden"> con el valor que
 *     se envia al guardar.
 *  3. Al elegir el proyecto de fase anterior se muestra su resumen y el
 *     numero de fase que le correspondera a este proyecto.
 *
 * Esto es solo comodidad para el usuario: el servidor vuelve a validar todo
 * en ProyectoService (que el edificio sea de la facultad, etc.).
 */
$(function () {
    var $form = $('#proyecto-form');

    if ($form.length === 0) {
        return;
    }

    var arbol = $form.data('ubicacion') || [];
    var $facultad = $('#proyecto-facultad');
    var $edificio = $('#proyecto-edificio');
    var $agregarBloque = $('#proyecto-agregar-bloque');
    var $bloques = $('#proyecto-bloques');
    var $agregarProyectista = $('#proyecto-agregar-proyectista');
    var $proyectistas = $('#proyecto-proyectistas');
    var $faseAnterior = $('#proyecto-fase-anterior');

    function buscar(lista, codigo) {
        for (var i = 0; i < (lista || []).length; i++) {
            if (lista[i].codigo === codigo) {
                return lista[i];
            }
        }

        return null;
    }

    // Deja en el <select> solo su primera opcion (el texto de ayuda) y agrega las nuevas.
    function llenar($select, opciones) {
        $select.find('option:not(:first)').remove();

        $.each(opciones || [], function (_, opcion) {
            $select.append($('<option>').val(opcion.codigo).text(opcion.nombre));
        });

        $select.val('');
    }

    function edificioActual() {
        var facultad = buscar(arbol, $facultad.val());

        return facultad ? buscar(facultad.edificios, $edificio.val()) : null;
    }

    function agregarChip($contenedor, valor, texto) {
        var yaEsta = $contenedor.find('.chip').filter(function () {
            return String($(this).data('valor')) === String(valor);
        }).length > 0;

        if (valor === '' || yaEsta) {
            return;
        }

        $('<span class="chip">')
            .attr('data-valor', valor)
            .text(texto + ' ')
            .append($('<input type="hidden">').attr('name', $contenedor.data('nombre')).val(valor))
            .append($('<button type="button" class="x" aria-label="Quitar">').text('×'))
            .appendTo($contenedor);
    }

    $facultad.on('change', function () {
        var facultad = buscar(arbol, $facultad.val());

        llenar($edificio, facultad ? facultad.edificios : []);
        llenar($agregarBloque, []);
        $bloques.empty();
    });

    $edificio.on('change', function () {
        var edificio = edificioActual();

        llenar($agregarBloque, edificio ? edificio.bloques : []);
        // Los bloques elegidos eran del edificio anterior: ya no aplican.
        $bloques.empty();
    });

    $agregarBloque.on('change', function () {
        agregarChip($bloques, $agregarBloque.val(), $agregarBloque.find('option:selected').text());
        $agregarBloque.val('');
    });

    $agregarProyectista.on('change', function () {
        agregarChip($proyectistas, $agregarProyectista.val(), $agregarProyectista.find('option:selected').text());
        $agregarProyectista.val('');
    });

    // Delegado en el formulario: tambien funciona con los chips agregados despues.
    $form.on('click', '.chip .x', function () {
        $(this).closest('.chip').remove();
    });

    $faseAnterior.on('change', function () {
        var $opcion = $faseAnterior.find('option:selected');
        var resumen = $opcion.data('resumen');
        var fase = 1;

        if ($faseAnterior.val() !== '' && resumen) {
            // El resumen empieza con "Fase N": la fase nueva es N + 1.
            var partes = /^Fase (\d+)/.exec(resumen);
            fase = partes ? parseInt(partes[1], 10) + 1 : 2;

            $('#proyecto-vinculado-codigo').text($opcion.data('codigo'));
            $('#proyecto-vinculado-resumen').text(resumen);
            $('#proyecto-vinculado').removeClass('d-none');
        } else {
            $('#proyecto-vinculado').addClass('d-none');
        }

        $('#proyecto-fase').text(fase);
    });
});
