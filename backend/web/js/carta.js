/**
 * Carta de entrega (views/carta/index.php): al elegir el tratamiento, el
 * cargo sugerido cambia entre DECANO y DECANA (Señora/Señoras = DECANA).
 * Solo toca la primera palabra, y solo si es DECANO o DECANA: un cargo
 * escrito a mano (ej. "DIRECTOR DE CARRERA") no se cambia.
 */
$(function () {
    var $tratamiento = $('#carta-tratamiento');
    var $cargo = $('#carta-cargo');

    $tratamiento.on('change', function () {
        var femenino = $tratamiento.val() === 'Señora' || $tratamiento.val() === 'Señoras';

        $cargo.val($cargo.val().replace(/^DECAN[OA]\b/i, femenino ? 'DECANA' : 'DECANO'));
    });
});
