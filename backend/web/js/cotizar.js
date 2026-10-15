/**
 * Registrar una cotizacion recibida (views/solicitud-cotizacion/cotizar.php).
 *
 *  1. Calcula el total de cada fila (cantidad de la lista x precio) y el
 *     total cotizado, mientras se escribe.
 *  2. Antes de enviar: cada precio escrito debe ser un numero >= 0, y al
 *     menos un item debe tener precio.
 *
 * El servidor vuelve a validar todo en CotizacionService::guardarDesdeSolicitud().
 */
$(function () {
    var $form = $('#cotizar-form');

    if ($form.length === 0) {
        return;
    }

    var $error = $('#cotizar-error-cliente');

    // Mismo formato que el formatter de Yii (backend/config/main.php):
    // 1,234.56, el que usan los presupuestos de la DTIC.
    function formatear(numero) {
        return numero.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function recalcular() {
        var total = 0;
        var cotizados = 0;

        $form.find('tr.cotizar-fila').each(function () {
            var $fila = $(this);
            var texto = $.trim($fila.find('.cotizar-precio').val());
            var cantidad = parseFloat($fila.find('.cotizar-cantidad').data('cantidad')) || 0;
            var precio = Number(texto);

            if (texto === '' || isNaN(precio)) {
                $fila.find('.cotizar-total').text('—');
                return;
            }

            cotizados++;
            total += cantidad * precio;
            $fila.find('.cotizar-total').text(formatear(cantidad * precio));
        });

        $('#cotizar-cotizados').text(cotizados);
        $('#cotizar-total-general').text(formatear(total));
    }

    $form.on('input', '.cotizar-precio', recalcular);
    recalcular();

    // 'beforeSubmit' es el evento de ActiveForm (yii.activeForm.js): se
    // dispara cuando la cabecera ya paso su validacion; devolver false
    // cancela el envio.
    $form.on('beforeSubmit', function () {
        var mensaje = '';
        var conPrecio = 0;

        $form.find('.cotizar-precio').each(function () {
            var texto = $.trim($(this).val());
            var correcto = texto === '' || (!isNaN(Number(texto)) && Number(texto) >= 0);

            $(this).toggleClass('is-invalid', !correcto);

            if (!correcto) {
                mensaje = 'Revise los precios marcados: deben ser números mayores o iguales a 0.';
            }

            if (texto !== '') {
                conPrecio++;
            }
        });

        if (mensaje === '' && conPrecio === 0) {
            mensaje = 'Escriba el precio de al menos un ítem (los que el proveedor no cotizó se dejan en blanco).';
        }

        $error.text(mensaje).toggleClass('d-none', mensaje === '');

        return mensaje === '';
    });
});
