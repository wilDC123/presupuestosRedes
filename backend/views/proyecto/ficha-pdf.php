<?php

declare(strict_types=1);

use backend\models\EspecificacionEmision;
use yii\helpers\Html;

/**
 * HTML fuente de la FICHA TECNICA DEL PROYECTO (revision del Ing. de DTIC
 * del 2026-10-29, paso 7). Se renderiza con renderPartial() y se le pasa a
 * mPDF -- ver ProyectoController::actionFicha().
 *
 * Formato PROVISIONAL: el Ing. pasara el suyo. Lo que lleva es lo que pidio
 * en la reunion: codigo, nombre, descripcion, las dos unidades, area de
 * intervencion, proceso, el equipo (con el responsable) y las fechas, mas la
 * lista de documentos de la carpeta economica ("en tu primera pagina deberia
 * estar tu ficha tecnica... presupuesto, especificaciones, cotizaciones").
 *
 * No es un documento de la gestion documental ("es un reporte de control"):
 * usa la cabecera SIN codigo del lineamiento (la de la solicitud de
 * cotizacion), con el codigo del proyecto, la unidad / dependencia, la fecha
 * de impresion y la pagina.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $model
 * @var array $datos Ver ProyectoService::datosFicha()
 * @var string|null $usuario quien la imprime
 */

$formato = Yii::$app->formatter;
$fecha = static fn (?string $valor, string $patron = 'php:d/m/Y') => $valor === null || $valor === '' ? '—' : $formato->asDatetime($valor, $patron);
$texto = static fn (?string $valor) => $valor === null || trim($valor) === '' ? '—' : Html::encode($valor);
$fechas = $datos['fechas'];
$presupuesto = $model->presupuesto;

// Areas agrupadas por edificio y bloque, como en la vista del proyecto.
$grupos = [];
foreach ($model->areasIntervencion as $area) {
    $grupos[$area->grupo()][] = $area->detalle();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* Carlito: las medidas de Calibri, la letra del lineamiento RD-GD-LI-001. */
    body {
        font-family: carlito;
        font-size: 10.5pt;
        color: #000;
    }
    /* Cabecera repetida en cada pagina: ver views/pdf/_cabecera.php. */
    @page {
        header: html_cabecera;
        margin-header: 5mm;
        margin-top: 32mm;
    }
    h2 {
        font-size: 10.5pt;
        background-color: #e0e0e0;
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
        padding: 4px 6px;
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
    .grupo {
        font-weight: bold;
    }
    .nota {
        font-size: 8.5pt;
        color: #444;
        margin-top: 14px;
    }
</style>
</head>
<body>

<?= $this->render('//pdf/_cabecera', [
    'titulo' => 'FICHA TÉCNICA DEL PROYECTO',
    'numero' => $model->codigo ?: '#' . $model->id,
    'facultad' => $model->unidadDependencia,
    'fecha' => date('d/m/Y'),
]) ?>

<h2>1. Datos del proyecto</h2>
<table class="datos">
    <tr><th>Código</th><td><strong><?= $texto($model->codigo) ?></strong></td></tr>
    <tr><th>Nombre del proyecto</th><td><?= $texto($model->nombre) ?></td></tr>
    <tr><th>Descripción</th><td><?= $model->descripcion ? nl2br(Html::encode($model->descripcion)) : '—' ?></td></tr>
    <tr><th>Tipo de proyecto</th><td><?= $texto($model->tipoProyecto->nombre ?? null) ?></td></tr>
    <tr><th>Proceso</th><td><?= $texto($model->proceso?->etiqueta()) ?></td></tr>
    <tr>
        <th>Fase</th>
        <td>
            Fase <?= (int) $model->numeroFase ?>
            <?php if ($model->proyectoAnterior !== null): ?>
                — continúa el proyecto <?= Html::encode($model->proyectoAnterior->codigo) ?>
            <?php endif; ?>
        </td>
    </tr>
</table>

<h2>2. Unidades</h2>
<table class="datos">
    <tr><th>Unidad / dependencia (beneficiaria)</th><td><?= $texto($model->unidadDependencia) ?></td></tr>
    <tr><th>Unidad solicitante</th><td><?= $texto($model->unidadSolicitante) ?></td></tr>
    <tr><th>Lugar de entrega</th><td><?= $texto($model->lugarEntrega) ?></td></tr>
</table>

<h2>3. Área de intervención</h2>
<table class="datos">
    <?php if ($grupos !== []): ?>
        <?php foreach ($grupos as $grupo => $detalles): ?>
            <tr>
                <th class="grupo"><?= Html::encode($grupo) ?></th>
                <td><?= implode('<br>', array_map(static fn (string $d) => Html::encode($d), $detalles)) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <?php // Proyectos de antes del 2026-10-28: la ubicacion que tenian. ?>
        <tr><td><?= $texto($model->ubicacionAnterior()) ?></td></tr>
    <?php endif; ?>
</table>

<h2>4. Equipo del proyecto</h2>
<table class="datos lista">
    <tr>
        <th>Persona</th>
        <th>Rol</th>
        <th style="width: 18%;">Responsable</th>
    </tr>
    <?php if ($model->participantes === []): ?>
        <tr><td colspan="3">—</td></tr>
    <?php endif; ?>
    <?php foreach ($model->participantes as $persona): ?>
        <tr>
            <td><?= Html::encode($persona->nombre) ?></td>
            <td><?= $texto($persona->rol->nombre ?? null) ?></td>
            <td style="text-align: center;"><?= $persona->esResponsable ? 'Sí' : '' ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>5. Fechas</h2>
<table class="datos">
    <tr><th>Creación</th><td><?= $fecha($fechas['creacion']) ?></td></tr>
    <tr><th>Revisión</th><td><?= $fecha($fechas['revision']) ?></td></tr>
    <tr><th>Aprobación</th><td><?= $fecha($fechas['aprobacion']) ?></td></tr>
    <tr>
        <th>Última modificación</th>
        <td>
            <?= $fecha($fechas['ultimaModificacion'], 'php:d/m/Y H:i') ?>
            <?= $fechas['ultimoUsuario'] !== null ? '· ' . Html::encode($fechas['ultimoUsuario']) : '' ?>
        </td>
    </tr>
</table>

<h2>6. Documentos de la carpeta económica</h2>
<table class="datos">
    <?php $lista = $datos['lista']; ?>
    <tr>
        <th>Lista de materiales y condiciones previas</th>
        <td>
            <?= $texto($model->codigo) ?> versión <?= Html::encode($lista['version']) ?>
            · <?= $lista['borrador']
                ? 'Borrador (se aprueba al enviar a cotización)'
                : 'Aprobada' . ($lista['aprobacion']->fechaAprobacion !== null ? ' el ' . Html::encode($lista['aprobacion']->fechaAprobacionFormateada()) : '') ?>
        </td>
    </tr>
    <tr>
        <th>Presupuesto</th>
        <td>
            <?php if ($presupuesto === null): ?>
                Todavía no tiene.
            <?php else: ?>
                <strong><?= $texto($presupuesto->numeroPresupuesto) ?></strong>
                · <?= Html::encode($presupuesto->estadoLabel()) ?>
                · Total Bs. <?= $formato->asDecimal((float) $datos['monto'], 2) ?>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Especificaciones técnicas vigentes</th>
        <td>
            <?= $datos['especificaciones'] === []
                ? 'Ninguna.'
                : implode('<br>', array_map(
                    static fn (EspecificacionEmision $e) => Html::encode($e->codigoConVersion() . ' – ' . $e->titulo),
                    $datos['especificaciones']
                )) ?>
        </td>
    </tr>
    <tr>
        <th>Solicitudes de cotización</th>
        <td>
            <?= $datos['solicitudes'] === []
                ? 'Ninguna.'
                : implode('<br>', array_map(
                    static fn ($s) => Html::encode($s->codigoCorto() . ' – ' . $s->nombre . ' (' . count($s->cotizaciones) . ' cotizaciones)'),
                    $datos['solicitudes']
                )) ?>
        </td>
    </tr>
</table>

<div class="nota">
    Impresa el <?= date('d/m/Y H:i') ?><?= $usuario !== null ? ' por ' . Html::encode($usuario) : '' ?>.
    Reporte de control del proyecto (no es un documento codificado de la gestión documental).
</div>

</body>
</html>
