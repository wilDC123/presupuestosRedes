<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Pantalla central del proyecto: datos basicos, barra de pasos (en que paso
 * va y a donde lleva cada uno) y estado actual.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $model
 * @var array<int, array{titulo: string, estado: string, url: array|null}> $pasos Ver ProyectoService::pasos()
 * @var string|null $nombreTipo
 * @var float|null $monto total del presupuesto (null si no tiene)
 * @var array{total: float, partes: array<int, array{rotulo: string, monto: float}>} $costo Ver ProyectoService::costoTotal()
 * @var array $urlPresupuesto Ver ProyectoService::urlPresupuesto()
 * @var bool $puedeEditarse
 * @var bool $estaAprobado
 * @var bool $puedeAgregarFase la ultima fase del proyecto (aprobada o no): se le puede agregar la siguiente
 * @var array<int, array<int, array{id: int, texto: string, presupuesto: ?backend\models\Presupuesto, monto: ?float, actual: bool}>> $franjaFases Ver ProyectoService::franjaFases()
 * @var bool $puedeRegistrarOrden una fase con su presupuesto aprobado: se le puede registrar una orden de cambio (parte 6)
 * @var int $totalItems items en la lista del proyecto
 * @var bool $listaEditable Ver ListaItemsService::puedeEditarse()
 * @var array{creacion: string|null, revision: string|null, aprobacion: string|null, ultimaModificacion: string|null, ultimoUsuario: string|null} $fechas Ver HistorialService::fechas()
 * @var backend\models\HistorialProyecto[] $historial de lo mas nuevo a lo mas antiguo
 */

$this->title = $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->identificador();

$presupuesto = $model->presupuesto;
$formato = Yii::$app->formatter;
?>
<div class="proyecto-view">

    <div class="rd-pagehead">
        <div>
            <h1>
                <?= Html::encode($this->title) ?>
                <?php // Con fase unica no se muestra "Fase" (revision del 2026-11-05, punto 3). ?>
                <?php if ($model->tieneVariasFases()): ?>
                    <span class="pill-fase">Fase <?= (int) $model->numeroFase ?></span>
                <?php endif; ?>
                <?php if ($model->esOrdenCambio()): ?>
                    <span class="pill-fase"><?= Html::encode($model->textoOrdenCambio()) ?></span>
                <?php endif; ?>
            </h1>
            <?php // El numero es del proyecto; el codigo RD-PI-PT-NNN, de su ficha tecnica (2026-11-06). ?>
            <div class="rd-pagehead-sub"><strong><?= Html::encode($model->identificador()) ?></strong><?= $model->codigoFicha() ? ' · ficha técnica <span class="mono">' . Html::encode((string) $model->codigoFicha()) . '</span>' : '' ?></div>
        </div>
        <div class="rd-acciones">
            <?php if ($puedeEditarse): ?>
                <?= Html::a('Editar datos', ['update', 'id' => $model->id], ['class' => 'btn btn-outline']) ?>
            <?php endif; ?>
            <?php if ($puedeRegistrarOrden): ?>
                <?= Html::a('Registrar orden de cambio', ['orden-cambio', 'idFase' => $model->id], [
                    'class' => 'btn btn-outline',
                    'title' => 'Un presupuesto adicional de esta fase aprobada, solo con lo que se agrega (formato RD-GD-FR-006)',
                ]) ?>
            <?php endif; ?>
            <?php // Con varias fases (o con ordenes de cambio) "Agregar fase" va en la franja FASES, no aqui. ?>
            <?php if ($puedeAgregarFase && $franjaFases === []): ?>
                <?= Html::a('Agregar fase ' . ((int) $model->numeroFase + 1) . ' →', ['create', 'idAnterior' => $model->id], [
                    'class' => 'btn btn-primary',
                    'title' => 'La fase siguiente del mismo proyecto (mismo número), con su propia área, equipo y carpeta económica',
                ]) ?>
            <?php endif; ?>
            <?php // Una orden de cambio aprobada no se elimina; una que no se aprobo, si (con sus documentos). ?>
            <?php if (!$model->esOrdenCambio() || !$estaAprobado): ?>
                <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
                    'class' => 'btn btn-danger',
                    'data' => [
                        'confirm' => $model->esOrdenCambio()
                            ? '¿Eliminar la ' . mb_strtolower($model->textoOrdenCambio(), 'UTF-8') . ' con su lista, solicitudes, cotizaciones y presupuesto? Sus especificaciones emitidas quedarán anuladas.'
                            : '¿Eliminar el ' . $model->identificador() . ' (' . $model->nombre . ')?',
                        'method' => 'post',
                    ],
                ]) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($franjaFases !== []): ?>
        <?= $this->render('_franja-fases', ['model' => $model, 'grupos' => $franjaFases, 'puedeAgregarFase' => $puedeAgregarFase]) ?>
    <?php endif; ?>

    <?= $this->render('_pasos', ['pasos' => $pasos]) ?>

    <?php if ($model->esOrdenCambio()): ?>
        <?php // Lo que el sistema guarda del FR-006 (parte 6). ?>
        <div class="card rd-card-pad mb-3">
            <div class="rd-card-head-simple mb-2">
                <div class="rd-card-titulo"><?= Html::encode($model->textoOrdenCambio()) ?> de la <?= Html::a(Html::encode($model->faseOrigen->identificador()), ['view', 'id' => $model->idFaseOrigen]) ?></div>
            </div>
            <div class="rd-dato"><strong>Tipo de cambio:</strong> <?= Html::encode($model->tipoCambioLabel()) ?></div>
            <div class="rd-dato"><strong>Descripción del cambio:</strong> <?= nl2br(Html::encode((string) $model->descripcionCambio)) ?></div>
            <div class="rd-dato"><strong>Motivo:</strong> <?= nl2br(Html::encode((string) $model->motivoCambio)) ?></div>
            <div class="rd-nota mt-1">
                Un presupuesto adicional de la fase, solo con lo que se agrega. El área, el equipo y los datos del proyecto
                son los de la fase. El formulario FR-006 se hace en Word y se le adjunta este presupuesto.
            </div>
        </div>
    <?php endif; ?>

    <div class="rd-cols">

        <div class="card rd-col-2 rd-card-pad">
            <div class="rd-card-head-simple mb-3">
                <div class="rd-card-titulo">Datos generales</div>
                <?= Html::a('Imprimir ficha técnica', ['ficha', 'id' => $model->id], [
                    'class' => 'btn btn-outline btn-sm',
                    'target' => '_blank',
                    'title' => 'Ficha técnica del proyecto en PDF (formato provisional)',
                ]) ?>
            </div>

            <div class="rd-dato"><strong>Tipo:</strong> <?= Html::encode($nombreTipo ?? '—') ?></div>
            <div class="rd-dato"><strong>Proceso:</strong> <?= Html::encode($model->proceso?->etiqueta() ?? '—') ?></div>
            <div class="rd-dato"><strong>Unidad / dependencia:</strong> <?= Html::encode($model->unidadDependencia ?: '—') ?></div>
            <div class="rd-dato"><strong>Unidad solicitante:</strong> <?= Html::encode($model->unidadSolicitante ?: '—') ?></div>
            <div class="rd-dato">
                <strong>Área de intervención:</strong>
                <?php if ($model->areasIntervencion !== []): ?>
                    <?php // Agrupadas por edificio y bloque, como en el formulario: el grupo arriba y un chip por area.
                    // Las escritas a mano (no estan en UsfxGEO) llevan la marca, solo en pantalla. ?>
                    <?php
                    $grupos = [];
                    foreach ($model->areasIntervencion as $area) {
                        $grupos[$area->grupo()][] = $area;
                    }
                    ?>
                    <?php foreach ($grupos as $grupo => $areasDelGrupo): ?>
                        <div class="rd-area-grupo mt-1">
                            <div class="rd-area-titulo"><?= Html::encode($grupo) ?></div>
                            <?php foreach ($areasDelGrupo as $area): ?>
                                <span class="chip"><?= Html::encode($area->detalle()) ?>
                                    <?php if ($area->esManual()): ?><span class="rd-area-marca" title="Escrita a mano: no está en la infraestructura de la universidad (UsfxGEO)">a mano</span><?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($model->ubicacionAnterior() !== ''): ?>
                    <?php // Proyectos registrados antes del 2026-10-28: edificio y bloques del JSON de entonces. ?>
                    <?= Html::encode($model->ubicacionAnterior()) ?>
                    <span class="rd-nota">(ubicación registrada antes del cambio; al editar el proyecto puede elegir sus áreas)</span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </div>
            <div class="rd-dato">
                <strong>Equipo del proyecto:</strong>
                <?php if ($model->participantes === []): ?>
                    —
                <?php else: ?>
                    <?php foreach ($model->participantes as $persona): ?>
                        <div class="mt-1">
                            <?= Html::encode($persona->nombre) ?>
                            <span class="rd-nota">· <?= Html::encode($persona->rol->nombre ?? '') ?></span>
                            <?php if ($persona->esResponsable): ?><span class="badge b-activo">Responsable</span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($model->descripcion)): ?>
                <div class="rd-dato mt-3"><strong>Descripción</strong></div>
                <div class="rd-nota"><?= nl2br(Html::encode($model->descripcion)) ?></div>
            <?php endif; ?>
        </div>

        <div class="rd-col-1 rd-pila">
            <div class="card rd-card-pad">
                <div class="kicker mb-2">Lista de ítems</div>
                <div class="rd-dato">
                    <strong><?= $totalItems ?></strong> ítems en la lista
                    <span class="<?= $model->estadoListaBadgeClass() ?>"><?= Html::encode($model->estadoListaLabel()) ?></span>
                </div>
                <div class="rd-dato">
                    <strong>Origen:</strong>
                    <?php if (!$model->listaIniciada): ?>
                        todavía sin definir
                    <?php elseif ($model->plantillaOrigen === null): ?>
                        en blanco (sin plantilla)
                    <?php else: ?>
                        plantilla "<?= Html::encode($model->plantillaOrigen->nombre) ?>"
                    <?php endif; ?>
                </div>
                <div class="rd-acciones">
                    <?php if ($listaEditable): ?>
                        <?= Html::a($model->listaIniciada ? 'Cargar otra plantilla' : 'Cargar plantilla →', ['plantilla', 'id' => $model->id], [
                            'class' => $model->listaIniciada ? 'btn btn-outline' : 'btn btn-primary',
                        ]) ?>
                    <?php endif; ?>
                    <?php if ($model->listaIniciada || $totalItems > 0): ?>
                        <?= Html::a($listaEditable ? 'Abrir lista →' : 'Ver lista →', ['/lista-items/index', 'id' => $model->id], [
                            'class' => $listaEditable ? 'btn btn-primary' : 'btn btn-outline',
                        ]) ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card rd-card-pad">
                <div class="kicker mb-2">Presupuesto</div>
                <?php if ($presupuesto === null): ?>
                    <div class="rd-dato"><span class="badge b-inactivo">Sin presupuesto</span></div>
                    <?= Html::a('Elaborar presupuesto →', $urlPresupuesto, ['class' => 'btn btn-primary']) ?>
                <?php else: ?>
                    <div class="rd-dato">
                        <span class="<?= $presupuesto->estadoBadgeClass() ?>"><?= Html::encode($presupuesto->estadoLabel()) ?></span>
                    </div>
                    <div class="rd-dato"><strong>Total:</strong> Bs. <?= $formato->asDecimal((float) $monto, 2) ?></div>
                    <?= Html::a('Abrir presupuesto →', $urlPresupuesto, ['class' => $presupuesto->esBorrador() ? 'btn btn-primary' : 'btn btn-outline']) ?>
                <?php endif; ?>
                <?php if (!$puedeEditarse): ?>
                    <p class="rd-nota mt-3 mb-0">
                        Este proyecto ya no se modifica. Una ampliación se registra como un proyecto
                        nuevo de fase siguiente, con su propio código.
                    </p>
                <?php endif; ?>
            </div>

            <div class="card rd-card-pad">
                <div class="kicker mb-2">Fechas</div>
                <?php $fecha = static fn (?string $valor, string $formatoFecha) => $valor === null ? '—' : $formato->asDatetime($valor, $formatoFecha); ?>
                <div class="rd-dato"><strong>Creación:</strong> <?= $fecha($fechas['creacion'], 'php:d/m/Y') ?></div>
                <div class="rd-dato"><strong>Revisión:</strong> <?= $fecha($fechas['revision'], 'php:d/m/Y') ?></div>
                <div class="rd-dato"><strong>Aprobación:</strong> <?= $fecha($fechas['aprobacion'], 'php:d/m/Y') ?></div>
                <div class="rd-dato">
                    <strong>Última modificación:</strong> <?= $fecha($fechas['ultimaModificacion'], 'php:d/m/Y H:i') ?>
                    <?php if ($fechas['ultimoUsuario'] !== null): ?>
                        <span class="rd-nota">· <?= Html::encode($fechas['ultimoUsuario']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php // Todas las fases del proyecto (mismo numero), con el estado de su presupuesto. Con la franja
            // FASES arriba (varias fases u ordenes de cambio) la lista ya esta ahi: el recuadro queda solo con el
            // costo total (2026-11-11, "es doble informacion en una misma pantalla"). ?>
            <div class="card rd-card-pad">
                <?php $fases = $model->numeroProyecto !== null ? $model->fases : [$model]; ?>
                <?php if ($franjaFases !== []): ?>
                    <div class="kicker mb-2">Costo del proyecto <?= Html::encode($model->numeroTexto()) ?></div>
                <?php else: ?>
                    <div class="kicker mb-2">Fases del proyecto <?= Html::encode($model->numeroTexto()) ?></div>
                <?php endif; ?>
                <?php // Cada fase con sus ordenes de cambio debajo (parte 6). ?>
                <?php foreach ($franjaFases === [] ? $fases : [] as $fase): ?>
                    <?php foreach (array_merge([$fase], $fase->ordenesCambio) as $fila): ?>
                        <div class="rd-dato<?= $fila->esOrdenCambio() ? ' ms-3' : '' ?>">
                            <?php $texto = $fila->esOrdenCambio() ? $fila->textoOrdenCambio() : 'Fase ' . (int) $fila->numeroFase; ?>
                            <?= (int) $fila->id === (int) $model->id ? '<strong>' . Html::encode($texto) . '</strong> (esta)' : Html::a(Html::encode($texto), ['view', 'id' => $fila->id]) ?>
                            · <?= $fila->presupuesto === null ? 'sin presupuesto' : Html::encode($fila->presupuesto->estadoLabel()) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <?php // Costo total del proyecto: un solo total, de los presupuestos aprobados (parte 6c). ?>
                <div class="rd-dato<?= $franjaFases === [] ? ' mt-2' : '' ?>">
                    <strong>Costo total del proyecto:</strong> Bs. <?= $formato->asDecimal($costo['total'], 2) ?>
                    <?php if (count($costo['partes']) > 1): ?>
                        <div class="rd-nota"><?= Html::encode(implode(' + ', array_map(static fn (array $p) => $p['rotulo'] . ': ' . $formato->asDecimal($p['monto'], 2), $costo['partes']))) ?></div>
                    <?php elseif ($costo['partes'] === []): ?>
                        <div class="rd-nota">Todavía no hay presupuestos aprobados.</div>
                    <?php endif; ?>
                </div>
                <?php if (count($fases) === 1): ?>
                    <div class="rd-nota mt-1">Fase única. La siguiente se agrega con "Agregar fase"; con el presupuesto aprobado, lo que falte se pide con una orden de cambio.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <?php // Historial (paso 6 de la revision del 2026-10-29): todo lo que paso, con quien y cuando. ?>
    <div class="card mt-3" id="historial">
        <div class="rd-card-head">
            <div class="rd-card-titulo">Historial</div>
            <div class="kicker"><?= count($historial) ?> registros</div>
        </div>
        <?php if ($historial === []): ?>
            <div class="rd-vacio">Todavía no hay registros.</div>
        <?php else: ?>
            <table class="table rd-historial">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Hecho</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $registro): ?>
                        <tr>
                            <td class="mono"><?= $formato->asDatetime($registro->fecha, 'php:d/m/Y H:i') ?></td>
                            <td><?= Html::encode($registro->usuarioTexto()) ?></td>
                            <td><?= Html::encode($registro->accionLabel()) ?></td>
                            <td class="rd-historial-detalle"><?= nl2br(Html::encode((string) $registro->detalle)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
