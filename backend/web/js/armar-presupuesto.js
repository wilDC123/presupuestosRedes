/**
 * Armar presupuesto (views/presupuesto/armar.php).
 *
 *  1. El campo de precio manual solo se edita con la opcion "Precio
 *     manual" marcada (entrar al campo la marca sola).
 *  2. Calcula el total de cada fila (cantidad x precio elegido) y el total
 *     general, mientras se elige.
 *  3. Antes de enviar: cada precio manual escrito debe ser un numero >= 0,
 *     y al menos un item debe tener precio.
 *
 * El servidor vuelve a validar todo en PresupuestoService::armar().
 */
$(function () {
    var $form = $('#armar-form');

    if ($form.length === 0) {
        return;
    }

    var $error = $('#armar-error-cliente');

    // Mismo formato que el formatter de Yii (backend/config/main.php).
    function formatear(numero) {
        return numero.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // Precio elegido en la fila, o null si queda pendiente.
    function precioDe($fila) {
        var $marcada = $fila.find('.armar-opcion:checked');

        if ($marcada.length === 0 || $marcada.val() === '') {
            return null;
        }

        if ($marcada.val() === 'manual') {
            var texto = $.trim($fila.find('.armar-precio').val());

            return texto === '' || isNaN(Number(texto)) ? null : Number(texto);
        }

        return parseFloat($marcada.data('precio'));
    }

    function recalcular() {
        var total = 0;
        var conPrecio = 0;

        $form.find('tr.armar-fila').each(function () {
            var $fila = $(this);
            var manual = $fila.find('.armar-opcion:checked').val() === 'manual';
            var precio = precioDe($fila);
            var cantidad = parseFloat($fila.data('cantidad')) || 0;

            // readonly y no disabled: un campo disabled no recibe el foco
            // (ver arriba). El servidor ignora el precio si no es manual.
            $fila.find('.armar-precio').prop('readOnly', !manual).toggleClass('text-muted', !manual);

            if (precio === null) {
                $fila.find('.armar-total').text('—');
                return;
            }

            conPrecio++;
            total += cantidad * precio;
            $fila.find('.armar-total').text(formatear(cantidad * precio));
        });

        $('#armar-con-precio').text(conPrecio);
        $('#armar-total-general').text(formatear(total));
    }

    $form.on('change', '.armar-opcion', recalcular);
    $form.on('input', '.armar-precio', recalcular);

    // Entrar al campo de precio marca sola la opcion "Precio manual".
    $form.on('focus', '.armar-precio', function () {
        $(this).closest('label').find('.armar-opcion').prop('checked', true);
        recalcular();
    });

    recalcular();

    $form.on('submit', function () {
        var mensaje = '';
        var conPrecio = 0;

        $form.find('tr.armar-fila').each(function () {
            var $fila = $(this);
            var $precio = $fila.find('.armar-precio');
            var manual = $fila.find('.armar-opcion:checked').val() === 'manual';
            var texto = $.trim($precio.val());
            var correcto = !manual || texto === '' || (!isNaN(Number(texto)) && Number(texto) >= 0);

            $precio.toggleClass('is-invalid', !correcto);

            if (!correcto) {
                mensaje = 'Revise los precios manuales marcados: deben ser números mayores o iguales a 0.';
            }

            if (precioDe($fila) !== null) {
                conPrecio++;
            }
        });

        if (mensaje === '' && conPrecio === 0) {
            mensaje = 'No eligió ningún precio. Marque una cotización o escriba un precio manual en al menos un ítem.';
        }

        $error.text(mensaje).toggleClass('d-none', mensaje === '');

        return mensaje === '';
    });
});
