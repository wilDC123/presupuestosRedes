<?php

declare(strict_types=1);

use backend\models\CorrelativoDocumento;
use backend\models\EspecificacionEmision;
use backend\models\Presupuesto;
use backend\models\Proceso;
use backend\models\Proyecto;
use backend\models\ProyectoAreaIntervencion;
use backend\models\SolicitudCotizacion;
use yii\helpers\Html;

/**
 * HTML fuente de la FICHA TECNICA DEL PROYECTO. Se renderiza con
 * renderPartial() y se le pasa a mPDF -- ver ProyectoController::actionFicha().
 *
 * Es un DOCUMENTO de la gestion documental (revision del Ing. de DTIC del
 * 2026-11-05, punto 2) y es UNO por proyecto (parte 5d): sale igual desde
 * cualquier fase. Desde el 2026-11-09 (parte 6c) sigue el Word "00 PT
 * RD-PI-PT-001" de los FORMATOS CORREGIDOS v3:
 *  - Cabecera de todos los documentos: codigo RD-PI-PT-NNN (el de la fase 1),
 *    version (1.0, 1.1 al aprobarse otra fase...), fecha de creacion de esa
 *    version y pagina. Ver ProyectoService::datosFicha().
 *  - 1. INFORMACION GENERAL: datos del proyecto, "Fases" (si hay varias), el
 *    COSTO TOTAL DEL PROYECTO (presupuestos aprobados de fases y ordenes de
 *    cambio, con su detalle) y las FECHAS en el mismo cuadro (con varias
 *    fases, una columna por fase). 2. UNIDADES.
 *  - Despues lo de cada fase: con fase unica, 3 area de intervencion, 4 equipo
 *    y 5 carpeta economica; con varias, "3. FASE 1" (3.1, 3.2, 3.3), "4. FASE 2"...
 *  - CARPETA ECONOMICA: tabla Documento | Codigo | Detalle | Version (lista de
 *    materiales, presupuesto, especificaciones -con el motivo si se
 *    reemitieron-, solicitudes de proforma y proformas recibidas). Las ORDENES
 *    DE CAMBIO de la fase van dentro de su carpeta, cada una con su barra
 *    (numero, fechas y motivo) y sus documentos.
 *  - SIN firmas (antes, del 2026-11-05 al 09, el control de elaboracion);
 *    mientras alguna fase no este aprobada, marca de agua BORRADOR (la pone el
 *    controlador). Al final, cuando y quien la imprimio.
 *
 * Carlito 11 (Calibri 11 del lineamiento), la carpeta en 10; escala de grises.
 * Fechas dd-mm-aaaa, como en el Word.
 *
 * @var yii\web\View $this
 * @var Proyecto $model la fase desde la que se imprime (los datos del proyecto son los mismos en todas)
 * @var array $datos Ver ProyectoService::datosFicha()
 * @var string|null $usuario quien la imprime
 */

$formato = Yii::$app->formatter;
$fecha = static fn (?string $valor, string $patron = 'php:d-m-Y') => $valor === null || $valor === '' ? '—' : $formato->asDatetime($valor, $patron);
$guiones = static fn (string $valor): string => $valor === '' ? '—' : str_replace('/', '-', $valor);
$texto = static fn (?string $valor) => $valor === null || trim($valor) === '' ? '—' : Html::encode($valor);
$monto = static fn (?float $valor): string => $formato->asDecimal((float) $valor, 2);
$fases = $datos['fases'];
$variasFases = $datos['variasFases'];
$costo = $datos['costo'];
// Los datos del proyecto, de la fase 1 (son los mismos en todas).
/** @var Proyecto $proyecto */
$proyecto = $fases[0]['proyecto'];

/** Una fila de la carpeta: rotulo (vacio = sigue el anterior) | codigo | detalle | version. */
$fila = static fn (string $rotulo, string $codigo, string $detalle, string $version): string => '<tr><th>' . $rotulo . '</th><td>' . $codigo . '</td><td>' . $detalle . '</td><td class="centro">' . $version . '</td></tr>';

/**
 * Las filas de la carpeta economica de una fase o de una orden de cambio
 * (ProyectoService::carpeta()).
 */
$carpeta = static function (array $c) use ($fila, $texto, $monto, $fecha, $guiones): string {
    /** @var Proyecto $p */
    $p = $c['proyecto'];
    $presupuesto = $p->presupuesto;
    $lista = $c['lista'];
    $html = '';

    // Lista de materiales: la version que corresponde (la siguiente en borrador mientras esta en edicion).
    $html .= $fila(
        // "BoM" (bill of materials), pedido del desarrollador el 2026-11-10.
        'Lista de materiales y condiciones previas (BoM)',
        $texto($p->codigoLista),
        'Elaborada el ' . $guiones($lista['fechaCreacion']) . ($lista['borrador'] ? ' <em>· borrador: se emite al enviar a cotización</em>' : ''),
        Html::encode($lista['version'])
    );

    if ($presupuesto === null) {
        $html .= $fila('Presupuesto', '—', 'Todavía no tiene.', '—');
    } else {
        $estado = $presupuesto->estado === Presupuesto::ESTADO_APROBADO
            ? 'Aprobado el ' . $guiones($presupuesto->fechaAprobacionFormateada())
            : Html::encode($presupuesto->estadoLabel());
        $html .= $fila('Presupuesto', $texto($presupuesto->numeroPresupuesto), $estado . ' · Total Bs. ' . $monto($c['monto']), Html::encode(Presupuesto::VERSION_DOCUMENTO));
    }

    /** @var EspecificacionEmision[] $especificaciones */
    $especificaciones = $c['especificaciones'];
    if ($especificaciones === []) {
        $html .= $fila('Especificaciones técnicas', '—', 'Ninguna.', '—');
    }
    foreach (array_values($especificaciones) as $i => $emision) {
        $detalle = Html::encode($emision->titulo);
        // Reemitida: cuando y por que (el motivo quedo en la emision anterior).
        if ((int) $emision->versionDocumento > 1) {
            $motivo = $c['reemisiones'][$emision->id] ?? '';
            $detalle .= '<br><em>Reemitida el ' . $fecha($emision->fechaEmision) . ($motivo !== '' ? ': ' . Html::encode($motivo) : '') . '</em>';
        }
        $html .= $fila($i === 0 ? 'Especificaciones técnicas' : '', Html::encode($emision->codigo()), $detalle, Html::encode($emision->versionTexto()));
    }

    /** @var SolicitudCotizacion[] $solicitudes */
    $solicitudes = $c['solicitudes'];
    if ($solicitudes === []) {
        $html .= $fila('Solicitud de proforma de cotización', '—', 'Ninguna.', '—');
    }
    foreach ($solicitudes as $i => $solicitud) {
        $html .= $fila(
            $i === 0 ? 'Solicitud de proforma de cotización' : '',
            $texto($solicitud->codigoDocumento ?? $solicitud->codigoCorto()),
            Html::encode($solicitud->nombre) . ' · ' . $guiones($solicitud->fechaDelDocumento()),
            Html::encode(SolicitudCotizacion::VERSION_DOCUMENTO)
        );
    }

    // Proformas recibidas (las cotizaciones de cada solicitud): solo si hay.
    $primera = true;
    foreach ($solicitudes as $solicitud) {
        foreach ($solicitud->cotizaciones as $cotizacion) {
            $html .= $fila(
                $primera ? 'Proformas recibidas' : '',
                $texto($solicitud->codigoDocumento ?? $solicitud->codigoCorto()),
                $texto($cotizacion->proveedor->razonSocial ?? null) . ' · ' . $fecha($cotizacion->fecha),
                '—'
            );
            $primera = false;
        }
    }

    return $html;
};

/**
 * Area, equipo y carpeta economica de UNA fase (con sus ordenes de cambio).
 * $numeros = los titulos de sus tres secciones ("3." "4." "5." con fase
 * unica; "3.1" "3.2" "3.3" en la fase 1...).
 */
$bloque = static function (array $fase, array $numeros) use ($texto, $carpeta, $fecha, $guiones): string {
    /** @var Proyecto $p */
    $p = $fase['proyecto'];
    $areas = ProyectoAreaIntervencion::lineasPorEdificio($p->areasIntervencion);

    // Area de intervencion: una casilla por edificio.
    $html = '<table class="ficha"><tr><th class="seccion">' . $numeros[0] . ' Área de intervención</th></tr>';
    if ($areas === []) {
        // Proyectos de antes del 2026-10-28: la ubicacion que tenian.
        $areas = [$p->ubicacionAnterior()];
    }
    foreach ($areas as $linea) {
        $html .= '<tr><td>' . $texto($linea) . '</td></tr>';
    }
    $html .= '</table>';

    // Equipo.
    $html .= '<table class="ficha"><tr><th class="seccion" colspan="3">' . $numeros[1] . ' Equipo del proyecto</th></tr>'
        . '<tr><th style="width: 39.8%;">Persona</th><th style="width: 43.1%;">Rol</th><th class="centro" style="width: 17.1%;">Responsable</th></tr>';
    if ($p->participantes === []) {
        $html .= '<tr><td colspan="3">—</td></tr>';
    }
    foreach ($p->participantes as $persona) {
        $html .= '<tr><td>' . Html::encode($persona->nombre) . '</td><td>' . $texto($persona->rol->nombre ?? null) . '</td>'
            . '<td class="centro">' . ($persona->esResponsable ? 'Sí' : '') . '</td></tr>';
    }
    $html .= '</table>';

    // Carpeta economica: la de la fase y, dentro, la de cada orden de cambio.
    $html .= '<table class="ficha carpeta"><tr><th class="seccion" colspan="4">' . $numeros[2] . ' Carpeta económica</th></tr>'
        . '<tr><th style="width: 25.6%;">Documento</th><th style="width: 20.4%;">Código</th><th style="width: 44.6%;">Detalle</th><th class="centro" style="width: 9.4%;">Versión</th></tr>'
        . $carpeta($fase);
    foreach ($fase['ordenes'] as $orden) {
        /** @var Proyecto $o */
        $o = $orden['proyecto'];
        $aprobada = ($o->presupuesto->estado ?? null) === Presupuesto::ESTADO_APROBADO;
        $html .= '<tr><td colspan="4" class="orden"><strong>' . Html::encode($o->textoOrdenCambio())
            . ' · solicitada el ' . $fecha($o->fechaCreacion)
            . ($aprobada ? ' · aprobada el ' . $guiones($o->presupuesto->fechaAprobacionFormateada()) : '')
            . '</strong><br><em>Motivo: ' . Html::encode((string) $o->motivoCambio) . ' (hoja de la orden en el formato RD-GD-FR-006).</em></td></tr>'
            . $carpeta($orden);
    }
    $html .= '</table>';

    return $html;
};

// Fechas de cada fase (HistorialService::fechas()).
$filasFechas = [
    'Creación' => static fn (array $f) => $fecha($f['fechas']['creacion']),
    'Revisión' => static fn (array $f) => $fecha($f['fechas']['revision']),
    'Aprobación' => static fn (array $f) => $fecha($f['fechas']['aprobacion']),
    'Última modificación' => static fn (array $f) => $fecha($f['fechas']['ultimaModificacion'], 'php:d-m-Y H:i')
        . ($f['fechas']['ultimoUsuario'] !== null ? ' · ' . Html::encode($f['fechas']['ultimoUsuario']) : ''),
];

// Costo total: el monto y, debajo, de donde sale.
$partes = $costo['partes'];
if ($partes === []) {
    $detalleCosto = 'Todavía no hay presupuestos aprobados';
} elseif (count($partes) === 1 && !$variasFases) {
    $detalleCosto = 'Presupuesto aprobado';
} else {
    $detalleCosto = implode(' + ', array_map(static fn (array $parte) => Html::encode($parte['rotulo']) . ': ' . $monto($parte['monto']), $partes))
        . ' (presupuestos aprobados)';
}
$columnasFechas = count($fases);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* Calibri 11 (lineamiento RD-GD-LI-001, 8.8.1): Carlito tiene sus mismas medidas. */
    body {
        font-family: carlito;
        font-size: 11pt;
        color: #000;
    }
    /* Cabecera repetida en cada pagina: ver views/pdf/_cabecera.php. */
    @page {
        header: html_cabecera;
        margin-header: 5mm;
        margin-top: 37mm;
    }
    table.ficha {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
    }
    table.ficha th,
    table.ficha td {
        border: 1px solid #000;
        padding: 2px 6px;
        vertical-align: top;
        text-align: left;
    }
    /* Rotulos de fila y de columna, como el Word: gris claro y negrita. */
    table.ficha th {
        background-color: #f2f2f2;
        font-weight: bold;
    }
    /* Titulo de cada seccion ("1. Información general"): primera fila de su tabla. */
    table.ficha th.seccion {
        background-color: #e0e0e0;
    }
    /* La carpeta economica, en 10 (como el Word). */
    table.carpeta th,
    table.carpeta td {
        font-size: 10pt;
    }
    table.carpeta th.seccion {
        font-size: 11pt;
    }
    /* Barra de cada orden de cambio dentro de la carpeta. */
    table.ficha td.orden {
        background-color: #d0d0d0;
    }
    /* Barra de cada fase, por encima de sus tres secciones. */
    table.fase {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
    }
    table.fase td {
        border: 1px solid #000;
        background-color: #d0d0d0;
        font-size: 12pt;
        font-weight: bold;
        padding: 3px 6px;
    }
    /* Con "table.ficha" delante, para ganarle al text-align: left de las celdas. */
    table.ficha th.centro,
    table.ficha td.centro {
        text-align: center;
    }
    .nota {
        font-size: 9pt;
        margin-top: 8px;
    }
</style>
</head>
<body>

<?= $this->render('//pdf/_cabecera', [
    'titulo' => 'FICHA TÉCNICA DEL PROYECTO',
    'codigo' => $datos['codigo'] ?: '—',
    'version' => $datos['version'],
    // El codigo PT siempre es del proceso PI.
    'proceso' => Proceso::nombreDe(CorrelativoDocumento::PROCESO),
    'fecha' => $fecha($datos['fechaDocumento']),
]) ?>

<?php // 1. Informacion general: tres columnas (rotulo y dos para el valor, o una por fase en las fechas). ?>
<table class="ficha">
    <tr><th class="seccion" colspan="<?= 1 + max(2, $columnasFechas) ?>">1. Información general</th></tr>
    <?php $span = max(2, $columnasFechas); ?>
    <tr><th style="width: 28.4%;">N.° de proyecto</th><td colspan="<?= $span ?>"><?= Html::encode($proyecto->numeroTexto()) ?></td></tr>
    <tr><th>Nombre del proyecto</th><td colspan="<?= $span ?>"><?= Html::encode(mb_strtoupper((string) $proyecto->nombre, 'UTF-8')) ?></td></tr>
    <tr><th>Descripción</th><td colspan="<?= $span ?>"><?= $proyecto->descripcion ? nl2br(Html::encode($proyecto->descripcion)) : '—' ?></td></tr>
    <tr><th>Tipo de proyecto</th><td colspan="<?= $span ?>"><?= $texto($proyecto->tipoProyecto->nombre ?? null) ?></td></tr>
    <tr><th>Proceso</th><td colspan="<?= $span ?>"><?= $texto($proyecto->proceso?->etiqueta()) ?></td></tr>
    <?php if ($variasFases): ?>
        <tr><th>Fases</th><td colspan="<?= $span ?>"><?= count($fases) ?></td></tr>
    <?php endif; ?>
    <tr>
        <th>Costo total del proyecto</th>
        <td colspan="<?= $span ?>"><strong>Bs. <?= $monto($costo['total']) ?></strong><br><em><?= $detalleCosto ?></em></td>
    </tr>
    <?php if ($variasFases): ?>
        <?php // Una columna por fase. ?>
        <tr>
            <th>Fechas</th>
            <?php foreach ($fases as $f): ?>
                <th class="centro">Fase <?= (int) $f['proyecto']->numeroFase ?></th>
            <?php endforeach; ?>
        </tr>
        <?php foreach ($filasFechas as $rotulo => $valor): ?>
            <tr>
                <td><?= $rotulo ?></td>
                <?php foreach ($fases as $f): ?>
                    <td class="centro"><?= $valor($f) ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <?php foreach ($filasFechas as $rotulo => $valor): ?>
            <tr><th>Fecha de <?= mb_strtolower($rotulo, 'UTF-8') ?></th><td colspan="<?= $span ?>"><?= $valor($fases[0]) ?></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<table class="ficha">
    <tr><th class="seccion" colspan="2">2. Unidades / Dependencia</th></tr>
    <tr><th style="width: 28.4%;">Unidad / dependencia (beneficiaria)</th><td><?= $texto($proyecto->unidadDependencia) ?></td></tr>
    <tr><th>Unidad solicitante</th><td><?= $texto($proyecto->unidadSolicitante) ?></td></tr>
</table>

<?php if (!$variasFases): ?>
    <?= $bloque($fases[0], ['3.', '4.', '5.']) ?>
<?php else: ?>
    <?php foreach ($fases as $i => $f): ?>
        <?php $n = 3 + $i; ?>
        <table class="fase"><tr><td><?= $n ?>. FASE <?= (int) $f['proyecto']->numeroFase ?></td></tr></table>
        <?= $bloque($f, ["$n.1", "$n.2", "$n.3"]) ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="nota">
    Impresa el <?= date('d-m-Y H:i') ?><?= $usuario !== null ? ' por ' . Html::encode($usuario) : '' ?>.
</div>

</body>
</html>
