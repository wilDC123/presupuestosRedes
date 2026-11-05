/**
 * Carta de entrega (views/carta/index.php): al elegir el tratamiento, el
 * cargo sugerido cambia entre DECANO y DECANA. Cada opcion trae
 * data-femenino ("Cargo en femenino" en Configuracion de la carta).
 * Solo toca la primera palabra, y solo si es DECANO o DECANA: un cargo
 * escrito a mano (ej. "DIRECTOR DE CARRERA") no se cambia.
 */
$(function () {
    var $tratamiento = $('#carta-tratamiento');
    var $cargo = $('#carta-cargo');

    $tratamiento.on('change', function () {
        var femenino = String($tratamiento.find('option:selected').data('femenino')) === '1';

        $cargo.val($cargo.val().replace(/^DECAN[OA]\b/i, femenino ? 'DECANA' : 'DECANO'));
    });
});
