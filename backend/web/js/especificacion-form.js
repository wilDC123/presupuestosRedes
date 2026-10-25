/**
 * Comportamiento del formulario de una ficha de la libreria
 * (views/especificacion/_form.php).
 *
 *  1. Tabla de caracteristicas: agregar (copiando la fila modelo del
 *     <template>), quitar, subir y bajar filas. Antes de enviar se renumeran
 *     los nombres Caracteristicas[n][...] en el orden en que se ven, que es
 *     el orden en que se imprimen.
 *  2. "Pegar varias filas": convierte lineas "Nombre: valor" en filas.
 *  3. "Extraer datos": manda el archivo al servidor y llena titulo, modelo
 *     sugerido, garantia y la tabla con lo que encuentre.
 *
 * El servidor vuelve a validar todo en EspecificacionService::guardar().
 */
$(function () {
    var $form = $('#especificacion-form');

    if ($form.length === 0) {
        return;
    }

    var $filas = $('#esp-filas');
    var filaModelo = $('#esp-fila-modelo').html();

    // --- 1. Tabla de caracteristicas ---

    function agregarFila(nombre, valor) {
        var $fila = $(filaModelo);
        $fila.find('.esp-nombre').val(nombre || '');
        $fila.find('.esp-valor').val(valor || '');
        $filas.append($fila);
        return $fila;
    }

    function filasConContenido() {
        return $filas.find('tr.esp-fila').filter(function () {
            return $.trim($(this).find('.esp-nombre').val()) !== '' || $.trim($(this).find('.esp-valor').val()) !== '';
        });
    }

    // Siempre hay al menos una fila en blanco para escribir.
    if ($filas.find('tr.esp-fila').length === 0) {
        agregarFila();
    }

    $('#esp-agregar').on('click', function () {
        agregarFila().find('.esp-nombre').trigger('focus');
    });

    $filas.on('click', '.esp-quitar', function () {
        $(this).closest('tr').remove();
        if ($filas.find('tr.esp-fila').length === 0) {
            agregarFila();
        }
    });

    $filas.on('click', '.esp-subir', function () {
        var $fila = $(this).closest('tr');
        $fila.prev('tr.esp-fila').before($fila);
    });

    $filas.on('click', '.esp-bajar', function () {
        var $fila = $(this).closest('tr');
        $fila.next('tr.esp-fila').after($fila);
    });

    $form.on('submit', function () {
        $filas.find('tr.esp-fila').each(function (i) {
            $(this).find('.esp-nombre').attr('name', 'Caracteristicas[' + i + '][nombre]');
            $(this).find('.esp-valor').attr('name', 'Caracteristicas[' + i + '][valor]');
        });
    });

    // --- 2. Pegar varias filas ---

    /**
     * "Nombre: valor" -> {nombre, valor}. Si lo que esta antes de los dos
     * puntos es muy largo, la linea es un solo texto (mismo criterio que el
     * extractor del servidor).
     */
    function separarLinea(linea) {
        var posicion = linea.indexOf(':');
        if (posicion > 0 && posicion <= 60) {
            return {nombre: $.trim(linea.substring(0, posicion)), valor: $.trim(linea.substring(posicion + 1))};
        }
        return {nombre: '', valor: $.trim(linea)};
    }

    function quitarFilasVacias() {
        $filas.find('tr.esp-fila').not(filasConContenido()).remove();
    }

    $('#esp-pegar-abrir').on('click', function () {
        $('#esp-pegar').removeClass('d-none');
        $('#esp-pegar-texto').trigger('focus');
    });

    $('#esp-pegar-cerrar').on('click', function () {
        $('#esp-pegar').addClass('d-none');
    });

    $('#esp-pegar-agregar').on('click', function () {
        var lineas = $('#esp-pegar-texto').val().split(/\r?\n/);

        quitarFilasVacias();
        $.each(lineas, function (i, linea) {
            if ($.trim(linea) !== '') {
                var partes = separarLinea(linea);
                agregarFila(partes.nombre, partes.valor);
            }
        });
        if ($filas.find('tr.esp-fila').length === 0) {
            agregarFila();
        }

        $('#esp-pegar-texto').val('');
        $('#esp-pegar').addClass('d-none');
    });

    // --- 3. Extraer datos del documento ---

    var $boton = $('#btn-extraer-metadatos');
    var $mensaje = $('#extraccion-mensaje');

    function mostrarMensaje(texto, tipo) {
        $mensaje.html($('<div>').addClass('alert alert-' + tipo + ' py-1 px-2 mb-0').text(texto));
    }

    function llenarSiVino(selector, valor) {
        if (valor) {
            $(selector).val(valor);
        }
    }

    $boton.on('click', function () {
        var archivo = $('#especificacion-archivoespecificacion')[0];

        if (!archivo.files || archivo.files.length === 0) {
            mostrarMensaje('Primero seleccione un archivo DOCX o PDF.', 'warning');
            return;
        }

        var datos = new FormData();
        datos.append('archivoEspecificacion', archivo.files[0]);
        datos.append(yii.getCsrfParam(), yii.getCsrfToken());

        $boton.prop('disabled', true);
        mostrarMensaje('Extrayendo datos, por favor espere…', 'info');

        $.ajax({
            url: $boton.data('url'),
            method: 'POST',
            data: datos,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (data) {
            if (data.error) {
                mostrarMensaje(data.error, 'danger');
                return;
            }

            var metadatos = data.metadatos || {};
            var caracteristicas = metadatos.caracteristicas || [];

            llenarSiVino('#especificacion-titulo', metadatos.titulo);
            llenarSiVino('#especificacionversion-modelosugerido', metadatos.modeloSugerido);
            llenarSiVino('#especificacionversion-garantia', metadatos.garantia);

            if (caracteristicas.length > 0) {
                var reemplazar = filasConContenido().length === 0
                    || window.confirm('La tabla ya tiene características. ¿Reemplazarlas por las ' + caracteristicas.length + ' del documento?');

                if (reemplazar) {
                    $filas.empty();
                    $.each(caracteristicas, function (i, c) {
                        agregarFila(c.nombre, c.valor);
                    });
                }
            }

            mostrarMensaje(
                'Datos extraídos' + (caracteristicas.length > 0 ? ' (' + caracteristicas.length + ' características)' : '')
                    + '. Revise y corrija lo que sea necesario antes de guardar.',
                'success'
            );
        }).fail(function () {
            mostrarMensaje('No se pudo conectar con el servidor para extraer los datos.', 'danger');
        }).always(function () {
            $boton.prop('disabled', false);
        });
    });
});
