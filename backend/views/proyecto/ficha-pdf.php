<?php

declare(strict_types=1);

use backend\components\PresupuestoPdfGenerator;
use backend\components\VersionDocumento;
use backend\models\CorrelativoDocumento;
use backend\models\EspecificacionEmision;
use backend\models\Proceso;
use backend\models\ProyectoAreaIntervencion;
use yii\helpers\Html;

/**
 * HTML fuente de la FICHA TECNICA DEL PROYECTO.
 * Espacios ajustados (celdas y titulos) para que el control de elaboracion entre al pie de la primera hoja en
 * un proyecto comun y no quede solo en una hoja aparte. Se renderiza con
 * renderPartial() y se le pasa a mPDF -- ver ProyectoController::actionFicha().
 *
 * Desde la revision del Ing. de DTIC del 2026-11-05 (punto 2) es un
 * DOCUMENTO de la gestion documental: "se imprime, se aprueba y lleva codigo"
 * (PT). Antes (desde el 2026-10-29) era un reporte de control sin codigo.
 *  - Cabecera del lineamiento: codigo, version, fecha (la de creacion del
 *    proyecto: "arriba va la fecha de creacion del documento") y pagina. El
 *    codigo es por ahora el del proyecto (RD-PI-PT-NNN); cuando el proyecto
 *    pase a tener solo un numero (parte 5), ese codigo queda para la ficha.
 *  - Orden: primero lo del proyecto (1 datos, 2 unidades, 3 fechas), despues
 *    lo de la fase (4 area de intervencion, 5 equipo, 6 documentos de la
 *    carpeta economica). Sin "Lugar de entrega" (va en cada especificacion).
 *    "Fase" solo si el proyecto tiene mas de una (con fase unica no se muestra).
 *  - Al fondo de la ultima pagina el control de elaboracion (elaborado y
 *    aprobado); mientras no esta aprobada, marca de agua BORRADOR (la pone el
 *    controlador). Ver ProyectoService::datosFicha().
 *
 * Carlito 11 (Calibri 11 del lineamiento), escala de grises.
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
$lista = $datos['lista'];
/** @var EspecificacionEmision[] $especificaciones */
$especificaciones = $datos['especificaciones'];

// Una casilla por edificio, con su bloque, piso y espacio en el mismo texto.
$areas = ProyectoAreaIntervencion::lineasPorEdificio($model->areasIntervencion);
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
    /* Titulos de la subtabla de especificaciones (Codigo | Item | Version). */
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
    'codigo' => $model->codigo ?: '—',
    'version' => VersionDocumento::texto(1),
    // El codigo PT siempre es del proceso PI.
    'proceso' => Proceso::nombreDe(CorrelativoDocumento::PROCESO),
    'fecha' => $fecha($model->fechaCreacion),
]) ?>

<h2>1. Datos del proyecto</h2>
<table class="datos">
    <tr><th>Código</th><td><strong><?= $texto($model->codigo) ?></strong></td></tr>
    <tr><th>Nombre del proyecto</th><td><?= $texto($model->nombre) ?></td></tr>
    <tr><th>Descripción</th><td><?= $model->descripcion ? nl2br(Html::encode($model->descripcion)) : '—' ?></td></tr>
    <tr><th>Tipo de proyecto</th><td><?= $texto($model->tipoProyecto->nombre ?? null) ?></td></tr>
    <tr><th>Proceso</th><td><?= $texto($model->proceso?->etiqueta()) ?></td></tr>
    <?php if ($datos['variasFases']): ?>
        <tr>
            <th>Fase</th>
            <td>
                Fase <?= (int) $model->numeroFase ?>
                <?php if ($model->proyectoAnterior !== null): ?>
                    — continúa el proyecto <?= Html::encode($model->proyectoAnterior->codigo) ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endif; ?>
</table>

<h2>2. Unidades</h2>
<table class="datos">
    <tr><th>Unidad / dependencia (beneficiaria)</th><td><?= $texto($model->unidadDependencia) ?></td></tr>
    <tr><th>Unidad solicitante</th><td><?= $texto($model->unidadSolicitante) ?></td></tr>
</table>

<h2>3. Fechas</h2>
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

<h2>4. Área de intervención</h2>
<table class="datos">
    <?php if ($areas !== []): ?>
        <?php foreach ($areas as $linea): ?>
            <tr><td><?= Html::encode($linea) ?></td></tr>
        <?php endforeach; ?>
    <?php else: ?>
        <?php // Proyectos de antes del 2026-10-28: la ubicacion que tenian. ?>
        <tr><td><?= $texto($model->ubicacionAnterior()) ?></td></tr>
    <?php endif; ?>
</table>

<h2>5. Equipo del proyecto</h2>
<table class="datos lista">
    <tr>
        <th>Persona</th>
        <th>Rol</th>
        <th style="width: 18%;" class="centro">Responsable</th>
    </tr>
    <?php if ($model->participantes === []): ?>
        <tr><td colspan="3">—</td></tr>
    <?php endif; ?>
    <?php foreach ($model->participantes as $persona): ?>
        <tr>
            <td><?= Html::encode($persona->nombre) ?></td>
            <td><?= $texto($persona->rol->nombre ?? null) ?></td>
            <td class="centro"><?= $persona->esResponsable ? 'Sí' : '' ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>6. Documentos de la carpeta económica</h2>
<?php // Cuatro columnas: el rotulo y, en la fila de especificaciones, Codigo | Item | Version (la version en su
// propia columna, pedido del 2026-11-05); las demas filas juntan las tres. ?>
<table class="datos">
    <tr>
        <th>Lista de materiales y condiciones previas</th>
        <td colspan="3">
            <?= $texto($model->codigoLista) ?> versión <?= Html::encode($lista['version']) ?>
            · <?= $lista['borrador']
                ? 'Borrador (se emite al enviar a cotización)'
                : 'Enviada a cotización' . ($lista['aprobacion']->fechaAprobacion !== null ? ' el ' . Html::encode($lista['aprobacion']->fechaAprobacionFormateada()) : '') ?>
        </td>
    </tr>
    <tr>
        <th>Presupuesto</th>
        <td colspan="3">
            <?php if ($presupuesto === null): ?>
                Todavía no tiene.
            <?php else: ?>
                <strong><?= $texto($presupuesto->numeroPresupuesto) ?></strong>
                · <?= Html::encode($presupuesto->estadoLabel()) ?><?= $presupuesto->fechaAprobacionFormateada() !== '' ? ' el ' . Html::encode($presupuesto->fechaAprobacionFormateada()) : '' ?>
                · Total Bs. <?= $formato->asDecimal((float) $datos['monto'], 2) ?>
            <?php endif; ?>
        </td>
    </tr>
    <?php if ($especificaciones === []): ?>
        <tr><th>Especificaciones técnicas vigentes</th><td colspan="3">Ninguna.</td></tr>
    <?php else: ?>
        <tr>
            <th rowspan="<?= count($especificaciones) + 1 ?>">Especificaciones técnicas vigentes</th>
            <td class="subtitulo" style="width: 24%;">Código</td>
            <td class="subtitulo">Ítem</td>
            <td class="subtitulo centro" style="width: 11%;">Versión</td>
        </tr>
        <?php foreach ($especificaciones as $emision): ?>
            <tr>
                <td><?= Html::encode($emision->codigo()) ?></td>
                <td><?= Html::encode($emision->titulo) ?></td>
                <td class="centro"><?= Html::encode($emision->versionTexto()) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    <tr>
        <th>Solicitudes de cotización</th>
        <td colspan="3">
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
</div>

<?php // Control de elaboracion: al fondo de la ultima pagina (PresupuestoPdfGenerator). ?>
<?= PresupuestoPdfGenerator::MARCA_AL_PIE ?>

<?= $this->render('//pdf/_control-elaboracion', [
    'version' => VersionDocumento::texto(1),
    'elaborado' => $datos['elaborado'],
    'aprobado' => $datos['aprobado'],
    'fechaAprobacion' => $datos['fechaAprobacion'],
]) ?>

</body>
</html>
