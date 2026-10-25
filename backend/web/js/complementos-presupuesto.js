/**
 * Paso "Presupuesto" (views/elaborar-presupuesto/_complementos.php): las
 * secciones sin precio del final del PDF (materiales que proveerá la DTIC y
 * trabajos complementarios).
 *
 *  1. "+ Agregar fila" copia la plantilla <template> de la seccion; "Quitar"
 *     borra la fila (se guarda recien con "Guardar").
 *  2. Al elegir un item del subcatalogo se copian su nombre DTIC y su unidad,
 *     si esos campos estan vacios.
 *  3. Antes de enviar se revisa que cada fila escrita tenga descripcion y,
 *     si es un material, cantidad mayor que cero. Una fila en blanco se
 *     ignora. El servidor vuelve a validar todo en
 *     ElaborarPresupuestoService::guardarComplementos().
 */
$(function () {
    // Indice de las filas nuevas: el servidor solo usa el orden, no el numero.
    var siguiente = 1000;

    $('.complementos-form').each(function () {
        var $form = $(this);
        var $filas = $form.find('.complementos-filas');
        var plantilla = $form.find('.complemento-plantilla').html();
        var esMaterial = $form.data('tipo') === 'material';

        $form.on('click', '.complemento-agregar', function () {
            var $fila = $(plantilla.replace(/__n__/g, String(siguiente++)));

            $filas.append($fila);
            $fila.find('input, select').filter(':visible').first().trigger('focus');
        });

        $form.on('click', '.complemento-quitar', function () {
            $(this).closest('tr').remove();
        });

        $form.on('change', '.complemento-item', function () {
            var $opcion = $(this).find('option:selected');
            var $fila = $(this).closest('tr');
            var $descripcion = $fila.find('.complemento-descripcion');
            var $unidad = $fila.find('.complemento-unidad');

            if ($opcion.val() === '') {
                return;
            }
            if ($.trim($descripcion.val()) === '') {
                $descripcion.val($.trim($opcion.text()));
            }
            if ($unidad.val() === '' && $opcion.data('unidad')) {
                $unidad.val(String($opcion.data('unidad')));
            }
        });

        $form.on('submit', function (evento) {
            var errores = [];

            $filas.find('tr').each(function (i) {
                var $fila = $(this);
                // El subtitulo solo no cuenta (igual que en el servidor).
                var escrito = $fila.find('input, select').not('[name$="[grupo]"]').filter(function () {
                    return $.trim($(this).val()) !== '';
                }).length > 0;

                if (!escrito) {
                    return;
                }
                if ($.trim($fila.find('.complemento-descripcion').val()) === '') {
                    errores.push('Fila ' + (i + 1) + ': falta la descripción.');
                }
                if (esMaterial && !(parseFloat($fila.find('.complemento-cantidad').val()) > 0)) {
                    errores.push('Fila ' + (i + 1) + ': la cantidad debe ser mayor que cero.');
                }
                if (esMaterial && $.trim($fila.find('.complemento-grupo').val()) === '') {
                    errores.push('Fila ' + (i + 1) + ': falta el subtítulo (ej. "Cableado y materiales de red").');
                }
            });

            if (errores.length > 0) {
                evento.preventDefault();
                window.alert(errores.join('\n'));
            }
        });
    });
});
