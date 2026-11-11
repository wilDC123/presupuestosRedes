/**
 * Configuracion de la carta (views/carta/configuracion.php): agregar, quitar
 * y ordenar las filas de "Tratamientos". El orden en que quedan es el que se
 * guarda (CartaService::guardarConfiguracion()).
 */
$(function () {
    var $tabla = $('#carta-tratamientos');
    var $cuerpo = $tabla.find('tbody');
    var siguiente = parseInt($tabla.data('siguiente'), 10) || 0;

    $('#carta-agregar-tratamiento').on('click', function () {
        var html = $('#carta-tratamiento-fila-modelo').html().replace(/__n__/g, String(siguiente));
        siguiente++;

        $(html).appendTo($cuerpo).find('input[type="text"]').trigger('focus');
    });

    $cuerpo.on('click', '.rd-tratamiento-quitar', function () {
        $(this).closest('tr').remove();
    });

    $cuerpo.on('click', '.rd-tratamiento-subir', function () {
        var $fila = $(this).closest('tr');
        $fila.prev().before($fila);
    });

    $cuerpo.on('click', '.rd-tratamiento-bajar', function () {
        var $fila = $(this).closest('tr');
        $fila.next().after($fila);
    });
});
