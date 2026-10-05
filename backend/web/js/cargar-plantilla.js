/**
 * Pantalla "Cargar plantilla" (views/proyecto/plantilla.php): al elegir una
 * tarjeta se resalta y se muestra la vista previa de esa plantilla.
 *
 * La eleccion en si es un <input type="radio"> normal dentro de cada
 * tarjeta, asi que el formulario funciona aunque este archivo no cargue.
 */
$(function () {
    var $form = $('#cargar-plantilla-form');

    $form.on('change', '.tpl-radio', function () {
        var id = String($(this).val());

        $form.find('.tpl').removeClass('selected');
        $(this).closest('.tpl').addClass('selected');

        $form.find('.tpl-vista').each(function () {
            $(this).toggleClass('d-none', String($(this).data('plantilla')) !== id);
        });
    });
});
