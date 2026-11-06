<?php

declare(strict_types=1);

use backend\components\PresupuestoPdfGenerator;
use backend\models\CorrelativoDocumento;
use backend\models\EspecificacionEmision;
use backend\models\Proceso;
use backend\models\Proyecto;
use backend\models\ProyectoAreaIntervencion;
use yii\helpers\Html;

/**
 * HTML fuente de la FICHA TECNICA DEL PROYECTO. Se renderiza con
 * renderPartial() y se le pasa a mPDF -- ver ProyectoController::actionFicha().
 *
 * Es un DOCUMENTO de la gestion documental (revision del Ing. de DTIC del
 * 2026-11-05, punto 2: "se imprime, se aprueba y lleva codigo", PT) y es UNO
 * por proyecto (parte 5d, 2026-11-06): sale igual desde cualquier fase.
 *  - Cabecera del lineamiento: el codigo RD-PI-PT-NNN de la ficha (el de la
 *    fase 1), la version (1.0, 1.1 al aprobarse la fase 2...), la fecha de
 *    creacion de esa version y la pagina. Ver ProyectoService::datosFicha().
 *  - Primero lo del proyecto, que no cambia por fase: 1 datos, 2 unidades,
 *    3 fechas (con varias fases, una columna por fase).
 *  - Despues lo de cada fase: con fase unica, 4 area de intervencion, 5 equipo
 *    y 6 documentos de la carpeta economica, sin "Fase"; con varias, un bloque
 *    "4. FASE 1", "5. FASE 2"... con esas tres cosas (4.1, 4.2, 4.3).
 *  - Al fondo de la ultima pagina el control de elaboracion (elaborado y
 *    aprobado); mientras la ultima fase no esta aprobada, marca de agua
 *    BORRADOR (la pone el controlador).
 *  - Sin "Lugar de entrega" (va en cada especificacion). Espacios ajustados
 *    para que las firmas entren al pie de la ultima hoja y no queden solas.
 *
 * Carlito 11 (Calibri 11 del lineamiento), escala de grises.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $model la fase desde la que se imprime (los datos del proyecto son los mismos en todas)
 * @var array $datos Ver ProyectoService::datosFicha()
 * @var string|null $usuario quien la imprime
 */

$formato = Yii::$app->formatter;
$fecha = static fn (?string $valor, string $patron = 'php:d/m/Y') => $valor === null || $valor === '' ? '—' : $formato->asDatetime($valor, $patron);
$texto = static fn (?string $valor) => $valor === null || trim($valor) === '' ? '—' : Html::encode($valor);
$fases = $datos['fases'];
$variasFases = $datos['variasFases'];
// Los datos del proyecto, de la fase 1 (son los mismos en todas).
/** @var Proyecto $proyecto */
$proyecto = $fases[0]['proyecto'];

/**
 * Area, equipo y documentos de UNA fase. $numeros = los titulos de sus tres
 * secciones ("4." "5." "6." con fase unica; "4.1" "4.2" "4.3" en la fase 1...).
 */
$bloque = static function (array $fase, array $numeros) use ($texto, $formato): string {
    /** @var Proyecto $p */
    $p = $fase['proyecto'];
    $presupuesto = $p->presupuesto;
    $lista = $fase['lista'];
    /** @var EspecificacionEmision[] $especificaciones */
    $especificaciones = $fase['especificaciones'];
    $areas = ProyectoAreaIntervencion::lineasPorEdificio($p->areasIntervencion);

    // Area de intervencion: una casilla por edificio.
    $html = '<h2>' . $numeros[0] . ' Área de intervención</h2><table class="datos">';
    if ($areas !== []) {
        foreach ($areas as $linea) {
            $html .= '<tr><td>' . Html::encode($linea) . '</td></tr>';
        }
    } else {
        // Proyectos de antes del 2026-10-28: la ubicacion que tenian.
        $html .= '<tr><td>' . $texto($p->ubicacionAnterior()) . '</td></tr>';
    }
    $html .= '</table>';

    // Equipo.
    $html .= '<h2>' . $numeros[1] . ' Equipo del proyecto</h2><table class="datos lista">'
        . '<tr><th>Persona</th><th>Rol</th><th style="width: 18%;" class="centro">Responsable</th></tr>';
    if ($p->participantes === []) {
        $html .= '<tr><td colspan="3">—</td></tr>';
    }
    foreach ($p->participantes as $persona) {
        $html .= '<tr><td>' . Html::encode($persona->nombre) . '</td><td>' . $texto($persona->rol->nombre ?? null) . '</td>'
            . '<td class="centro">' . ($persona->esResponsable ? 'Sí' : '') . '</td></tr>';
    }
    $html .= '</table>';

    // Documentos de la carpeta economica. Cuatro columnas: el rotulo y, en la fila de especificaciones,
    // Codigo | Item | Version (la version en su propia columna); las demas filas juntan las tres.
    $html .= '<h2>' . $numeros[2] . ' Documentos de la carpeta económica</h2><table class="datos">';
    $html .= '<tr><th>Lista de materiales y condiciones previas</th><td colspan="3">'
        . $texto($p->codigoLista) . ' versión ' . Html::encode($lista['version']) . ' · '
        . ($lista['borrador']
            ? 'Borrador (se emite al enviar a cotización)'
            : 'Enviada a cotización' . ($lista['aprobacion']->fechaAprobacion !== null ? ' el ' . Html::encode($lista['aprobacion']->fechaAprobacionFormateada()) : ''))
        . '</td></tr>';
    $html .= '<tr><th>Presupuesto</th><td colspan="3">';
    if ($presupuesto === null) {
        $html .= 'Todavía no tiene.';
    } else {
        $aprobado = $presupuesto->fechaAprobacionFormateada();
        $html .= '<strong>' . $texto($presupuesto->numeroPresupuesto) . '</strong> · ' . Html::encode($presupuesto->estadoLabel())
            . ($aprobado !== '' ? ' el ' . Html::encode($aprobado) : '')
            . ' · Total Bs. ' . $formato->asDecimal((float) $fase['monto'], 2);
    }
    $html .= '</td></tr>';
    if ($especificaciones === []) {
        $html .= '<tr><th>Especificaciones técnicas vigentes</th><td colspan="3">Ninguna.</td></tr>';
    } else {
        $html .= '<tr><th rowspan="' . (count($especificaciones) + 1) . '">Especificaciones técnicas vigentes</th>'
            . '<td class="subtitulo" style="width: 24%;">Código</td><td class="subtitulo">Ítem</td>'
            . '<td class="subtitulo centro" style="width: 11%;">Versión</td></tr>';
        foreach ($especificaciones as $emision) {
            $html .= '<tr><td>' . Html::encode($emision->codigo()) . '</td><td>' . Html::encode($emision->titulo) . '</td>'
                . '<td class="centro">' . Html::encode($emision->versionTexto()) . '</td></tr>';
        }
    }
    $html .= '<tr><th>Solicitudes de cotización</th><td colspan="3">'
        . ($fase['solicitudes'] === []
            ? 'Ninguna.'
            : implode('<br>', array_map(
                static fn ($s) => Html::encode($s->codigoCorto() . ' – ' . $s->nombre . ' (' . count($s->cotizaciones) . ' cotizaciones)'),
                $fase['solicitudes']
            )))
        . '</td></tr></table>';

    return $html;
};
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
    h2 {
        font-size: 11pt;
        background-color: #e0e0e0;
        border: 1px solid #666;
        padding: 2px 6px;
        margin: 8px 0 0 0;
    }
    /* Barra de cada fase, por encima de sus tres secciones. */
    .fase {
        font-size: 11pt;
        font-weight: bold;
        text-align: center;
        background-color: #d0d0d0;
        border: 1px solid #666;
        padding: 3px 6px;
        margin: 12px 0 0 0;
    }
    table.datos {
        width: 100%;
        border-collapse: collapse;
    }
    table.datos th,
    table.datos td {
        border: 1px solid #666;
        padding: 2px 6px;
        vertical-align: top;
        text-align: left;
    }
    table.datos th {
        width: 28%;
        background-color: #f2f2f2;
        font-weight: bold;
    }
    table.lista th {
        width: auto;
        background-color: #f2f2f2;
    }
    /* Titulos de las subtablas (especificaciones; fases en las fechas). */
    table.datos td.subtitulo {
        background-color: #f2f2f2;
        font-weight: bold;
    }
    .centro {
        text-align: center;
    }
    .nota {
        font-size: 9pt;
        color: #444;
        margin-top: 6px;
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

<h2>1. Datos del proyecto</h2>
<table class="datos">
    <?php // El numero es del proyecto; el codigo RD-PI-PT-NNN va en la cabecera: es el de esta ficha. ?>
    <tr><th>N.° de proyecto</th><td><strong><?= Html::encode($proyecto->numeroTexto()) ?></strong></td></tr>
    <tr><th>Nombre del proyecto</th><td><?= $texto($proyecto->nombre) ?></td></tr>
    <tr><th>Descripción</th><td><?= $proyecto->descripcion ? nl2br(Html::encode($proyecto->descripcion)) : '—' ?></td></tr>
    <tr><th>Tipo de proyecto</th><td><?= $texto($proyecto->tipoProyecto->nombre ?? null) ?></td></tr>
    <tr><th>Proceso</th><td><?= $texto($proyecto->proceso?->etiqueta()) ?></td></tr>
    <?php if ($variasFases): ?>
        <tr><th>Fases</th><td><?= count($fases) ?></td></tr>
    <?php endif; ?>
</table>

<h2>2. Unidades</h2>
<table class="datos">
    <tr><th>Unidad / dependencia (beneficiaria)</th><td><?= $texto($proyecto->unidadDependencia) ?></td></tr>
    <tr><th>Unidad solicitante</th><td><?= $texto($proyecto->unidadSolicitante) ?></td></tr>
</table>

<h2>3. Fechas</h2>
<?php
$filasFechas = [
    'Creación' => static fn (array $f) => $fecha($f['fechas']['creacion']),
    'Revisión' => static fn (array $f) => $fecha($f['fechas']['revision']),
    'Aprobación' => static fn (array $f) => $fecha($f['fechas']['aprobacion']),
    'Última modificación' => static fn (array $f) => $fecha($f['fechas']['ultimaModificacion'], 'php:d/m/Y H:i')
        . ($f['fechas']['ultimoUsuario'] !== null ? ' · ' . Html::encode($f['fechas']['ultimoUsuario']) : ''),
];
?>
<table class="datos">
    <?php if ($variasFases): ?>
        <?php // Una columna por fase. ?>
        <tr>
            <th></th>
            <?php foreach ($fases as $f): ?>
                <td class="subtitulo centro">Fase <?= (int) $f['proyecto']->numeroFase ?></td>
            <?php endforeach; ?>
        </tr>
    <?php endif; ?>
    <?php foreach ($filasFechas as $rotulo => $valor): ?>
        <tr>
            <th><?= $rotulo ?></th>
            <?php foreach ($fases as $f): ?>
                <td<?= $variasFases ? ' class="centro"' : '' ?>><?= $valor($f) ?></td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
</table>

<?php if (!$variasFases): ?>
    <?= $bloque($fases[0], ['4.', '5.', '6.']) ?>
<?php else: ?>
    <?php foreach ($fases as $i => $f): ?>
        <?php $n = 4 + $i; ?>
        <div class="fase"><?= $n ?>. FASE <?= (int) $f['proyecto']->numeroFase ?></div>
        <?= $bloque($f, ["$n.1", "$n.2", "$n.3"]) ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="nota">
    Impresa el <?= date('d/m/Y H:i') ?><?= $usuario !== null ? ' por ' . Html::encode($usuario) : '' ?>.
</div>

<?php // Control de elaboracion: al fondo de la ultima pagina (PresupuestoPdfGenerator). ?>
<?= PresupuestoPdfGenerator::MARCA_AL_PIE ?>

<?= $this->render('//pdf/_control-elaboracion', [
    'version' => $datos['version'],
    'elaborado' => $datos['elaborado'],
    'aprobado' => $datos['aprobado'],
    'fechaAprobacion' => $datos['fechaAprobacion'],
]) ?>

</body>
</html>
