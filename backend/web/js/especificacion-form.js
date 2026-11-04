/**
 * Comportamiento del formulario de una ficha de la libreria
 * (views/especificacion/_form.php).
 *
 *  1. Tabla de caracteristicas: agregar (copiando la fila modelo del
 *     <template>), quitar, subir y bajar filas, y meterlas o sacarlas un
 *     nivel con las flechas → ← (1.4 -> 1.4.1). Antes de enviar se renumeran
 *     los nombres Caracteristicas[n][...] en el orden en que se ven, que es
 *     el orden en que se imprimen. La columna "N.°" muestra el numero que
 *     llevara cada fila en el formulario de especificacion (1.3, 1.4,
 *     1.4.1, 1.4.1.1...), con la MISMA regla que NumeracionEspecificacion (PHP), que
 *     es la que manda en el PDF y en la ficha.
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

    // Las 2 primeras filas (marca y modelo, URL) las agrega el formulario.
    var FILAS_FIJAS = 2;
    // Niveles: 1 = 1.4, 2 = 1.4.1, 3 = 1.4.1.1 (NumeracionEspecificacion::NIVEL_MAXIMO).
    var NIVEL_MAXIMO = 3;

    function tieneContenido($fila) {
        return $.trim($fila.find('.esp-nombre').val()) !== '' || $.trim($fila.find('.esp-valor').val()) !== '';
    }

    /** El nivel pedido de la fila (el campo oculto .esp-nivel). */
    function nivelPedido($fila) {
        return parseInt($fila.find('.esp-nivel').val(), 10) || 1;
    }

    /**
     * Pone el numero de cada fila. Igual que NumeracionEspecificacion::caracteristicas()
     * y niveles(): cada fila va en su nivel, sin saltarse ninguno (una de nivel
     * 3 sin una de nivel 2 arriba se toma como 2), y una de nivel 1 cierra el
     * grupo. El servidor hace la misma correccion al guardar, asi que lo que
     * se ve es lo que se guarda. Las filas vacias no se guardan: no llevan
     * numero ni cuentan.
     */
    function numerar() {
        var contadores = [FILAS_FIJAS];
        var anterior = 0;

        $filas.find('tr.esp-fila').each(function () {
            var $fila = $(this);
            var esSubtitulo = $fila.find('.esp-subtitulo').prop('checked');
            var conContenido = tieneContenido($fila);
            // Lo mas adentro que puede ir: un nivel mas que la fila de arriba.
            var maximo = Math.min(anterior + 1, NIVEL_MAXIMO);
            var nivel = Math.max(1, Math.min(nivelPedido($fila), maximo));
            var numero = '';

            $fila.toggleClass('esp-es-subtitulo', esSubtitulo);
            // Un subtitulo es un solo texto: se escribe en "Valor" (si tambien
            // tiene "Característica", el servidor avisa al guardar).
            $fila.find('.esp-valor').attr('placeholder', esSubtitulo ? 'Ej. Hardware' : 'Ej. 23 AWG');

            if (conContenido) {
                contadores.length = nivel;
                contadores[nivel - 1] = (contadores[nivel - 1] || 0) + 1;
                numero = '1.' + contadores.join('.');
                anterior = nivel;
            }

            $fila.data('nivel', nivel);
            $fila.find('.esp-numero').text(numero);
            $fila.find('td').eq(1)
                .toggleClass('esp-nivel-2', nivel === 2)
                .toggleClass('esp-nivel-3', nivel === 3);
            $fila.find('.esp-afuera').prop('disabled', nivel === 1);
            $fila.find('.esp-adentro').prop('disabled', nivel >= maximo);
        });
    }

    /**
     * Agrega una fila al final. Sin nivel, toma el de la ultima fila con
     * contenido, o uno mas si esa es un subtitulo (lo que se escribe debajo de
     * "Hardware" suele ir dentro de el).
     */
    function agregarFila(nombre, valor, nivel) {
        var $fila = $(filaModelo);

        if (nivel === undefined) {
            var $ultima = filasConContenido().last();
            nivel = $ultima.length === 0 ? 1
                : ($ultima.data('nivel') || 1) + ($ultima.find('.esp-subtitulo').prop('checked') ? 1 : 0);
        }

        $fila.find('.esp-nombre').val(nombre || '');
        $fila.find('.esp-valor').val(valor || '');
        $fila.find('.esp-nivel').val(Math.min(nivel, NIVEL_MAXIMO));
        $filas.append($fila);
        numerar();
        return $fila;
    }

    function filasConContenido() {
        return $filas.find('tr.esp-fila').filter(function () {
            return tieneContenido($(this));
        });
    }

    // Siempre hay al menos una fila en blanco para escribir.
    if ($filas.find('tr.esp-fila').length === 0) {
        agregarFila();
    }
    numerar();

    $filas.on('change', '.esp-subtitulo', function () {
        var $fila = $(this).closest('tr');
        var $nombre = $fila.find('.esp-nombre');
        var $valor = $fila.find('.esp-valor');

        // Si el texto se habia escrito como "Característica", pasa a "Valor".
        if (this.checked && $.trim($nombre.val()) !== '' && $.trim($valor.val()) === '') {
            $valor.val($nombre.val());
            $nombre.val('');
        }
        numerar();
    });

    $filas.on('input', '.esp-nombre, .esp-valor', numerar);

    $('#esp-agregar').on('click', function () {
        agregarFila().find('.esp-nombre').trigger('focus');
    });

    $filas.on('click', '.esp-quitar', function () {
        $(this).closest('tr').remove();
        if ($filas.find('tr.esp-fila').length === 0) {
            agregarFila();
        }
        numerar();
    });

    // Flechas de nivel: → mete la fila un nivel (queda debajo de la fila de arriba: 1.5 -> 1.4.1), ← la saca.
    $filas.on('click', '.esp-adentro, .esp-afuera', function () {
        var $fila = $(this).closest('tr');
        var cambio = $(this).hasClass('esp-adentro') ? 1 : -1;

        $fila.find('.esp-nivel').val(Math.max(1, ($fila.data('nivel') || 1) + cambio));
        numerar();
    });

    $filas.on('click', '.esp-subir', function () {
        var $fila = $(this).closest('tr');
        $fila.prev('tr.esp-fila').before($fila);
        numerar();
    });

    $filas.on('click', '.esp-bajar', function () {
        var $fila = $(this).closest('tr');
        $fila.next('tr.esp-fila').after($fila);
        numerar();
    });

    $form.on('submit', function () {
        $filas.find('tr.esp-fila').each(function (i) {
            $(this).find('.esp-nombre').attr('name', 'Caracteristicas[' + i + '][nombre]');
            $(this).find('.esp-valor').attr('name', 'Caracteristicas[' + i + '][valor]');
            $(this).find('.esp-subtitulo').attr('name', 'Caracteristicas[' + i + '][esSubtitulo]');
            $(this).find('.esp-nivel').attr('name', 'Caracteristicas[' + i + '][nivel]');
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
