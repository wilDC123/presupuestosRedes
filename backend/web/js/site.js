/**
 * Los navegadores (Chrome/Edge en particular) cambian el valor de un
 * <input type="number"> con el foco puesto ahi cuando el usuario gira la
 * rueda del mouse sobre el campo -- suma o resta "step" por cada tick de
 * scroll, sin ningun aviso visual inmediato. Esto pasa facilmente sin
 * querer (por ejemplo, al desplazarse por la pagina con el cursor todavia
 * sobre el campo) y corrompe datos numericos como cantidad o precioUnitario
 * antes de que el formulario se envie.
 *
 * Quitarle el foco al campo durante el evento "wheel" evita el cambio: el
 * navegador solo aplica el ajuste por scroll si el input esta enfocado en el
 * momento en que procesa la accion por defecto del evento, que ocurre
 * despues de que corren los listeners -- este handler alcanza a hacer blur()
 * antes de eso. Se registra una sola vez, delegado en document, asi cubre
 * todos los <input type="number"> del sitio (actuales y futuros) sin tener
 * que repetirlo campo por campo.
 */
$(document).on('wheel', 'input[type="number"]', function () {
    $(this).trigger('blur');
});
