/**
 * Administracion de plantillas (views/plantilla/index.php).
 *
 *  1. Buscador + segmentado de estado (Activas/Inactivas/Todas) + tipo de
 *     proyecto: los tres filtros se combinan sobre las filas ya dibujadas
 *     (la pantalla no pagina ni pide nada al servidor).
 *  2. "Ver/Ocultar los N ítems": despliega la fila de detalle de esa
 *     plantilla. Al filtrar, una fila oculta tambien oculta su detalle; si
 *     estaba desplegada, sigue desplegada al volver a aparecer.
 */
$(function () {
    var $tabla = $('#plantilla-tabla');

    if ($tabla.length === 0) {
        return;
    }

    var $buscar = $('#plantilla-buscar');
    var $segmentado = $('#plantilla-segmentado');
    var $tipo = $('#plantilla-tipo');
    var $contador = $('#plantilla-contador');
    var estadoActual = $segmentado.find('.rd-segmento[aria-pressed="true"]').data('estado');

    function filtrar() {
        var texto = $.trim($buscar.val()).toLowerCase();
        var tipo = String($tipo.val() || '');
        var visibles = 0;

        $tabla.find('tr.rd-fila-plantilla').each(function () {
            var $fila = $(this);
            var $boton = $fila.find('.rd-ver-items');
            var coincideTexto = String($fila.data('nombre')).toLowerCase().indexOf(texto) !== -1;
            var coincideEstado = estadoActual === '' || String($fila.data('activo')) === String(estadoActual);
            var coincideTipo = tipo === '' || String($fila.data('tipo')) === tipo;
            var visible = coincideTexto && coincideEstado && coincideTipo;

            $fila.toggleClass('d-none', !visible);

            if ($boton.length > 0) {
                var $detalle = $('#' + $boton.data('detalle'));
                $detalle.toggleClass('d-none', !visible || $boton.attr('aria-expanded') !== 'true');
            }

            if (visible) {
                visibles += 1;
            }
        });

        $contador.text('Mostrando ' + visibles + (visibles === 1 ? ' plantilla' : ' plantillas'));
    }

    $buscar.on('input', filtrar);
    $tipo.on('change', filtrar);

    $segmentado.on('click', '.rd-segmento', function () {
        $segmentado.find('.rd-segmento').attr('aria-pressed', 'false');
        $(this).attr('aria-pressed', 'true');
        estadoActual = $(this).data('estado');
        filtrar();
    });

    $tabla.on('click', '.rd-ver-items', function () {
        var $boton = $(this);
        var $detalle = $('#' + $boton.data('detalle'));
        var expandido = $boton.attr('aria-expanded') === 'true';
        var total = $boton.data('total');

        $boton.attr('aria-expanded', String(!expandido));
        $boton.text((expandido ? 'Ver los ' : 'Ocultar los ') + total + ' ítems');
        $detalle.toggleClass('d-none', expandido);
    });

    filtrar();
});
