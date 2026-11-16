/**
 * Funciones que usan varios formularios del modulo de Redes. Se cargan con
 * backend\assets\ComunAsset: el JS de cada pagina que las use lo declara en
 * 'depends', y asi Yii pone este archivo antes.
 *
 * Quedan en un solo objeto global (window.Redes) para no llenar el espacio
 * global con nombres sueltos que podrian chocar con los de una pagina.
 */
window.Redes = (function () {
    'use strict';

    return {
        /**
         * Minusculas y sin tildes, para comparar textos escritos por el
         * usuario: "Tecnología" y "tecnologia", "Canaleta" y "canaleta" son
         * lo mismo. normalize('NFD') separa cada letra de su tilde y el
         * reemplazo quita las tildes sueltas.
         */
        normalizar: function (texto) {
            return String(texto).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        },

        /**
         * Fila nueva de una tabla que se completa en el navegador, a partir
         * de la fila modelo (<template>) que dibuja el servidor con "__n__"
         * donde va el indice (items[__n__][cantidad]). Cada fila nueva recibe
         * un indice que no se repite, para que PHP reciba un arreglo con una
         * entrada por fila. Devuelve la fila como objeto jQuery, sin
         * agregarla a la tabla.
         */
        filaDesdeModelo: function (html, indice) {
            return $(String(html).replace(/__n__/g, String(indice)));
        },

        /**
         * Buscador de la lista del subcatalogo (lista de items y plantilla):
         * oculta las filas cuyo data-nombre no contiene el texto escrito.
         */
        filtrarPorNombre: function ($filas, texto) {
            var buscado = $.trim(texto).toLowerCase();

            $filas.each(function () {
                var coincide = String($(this).data('nombre')).toLowerCase().indexOf(buscado) !== -1;

                $(this).toggleClass('d-none', !coincide);
            });
        }
    };
}());
