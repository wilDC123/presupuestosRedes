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
 * @var array $urlPresupuesto Ver ProyectoService::urlPresupuesto()
 * @var bool $puedeEditarse
 * @var bool $estaAprobado
 * @var bool $puedeAgregarFase la ultima fase del proyecto, ya aprobada: se le puede agregar la siguiente
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
            </h1>
            <?php // El numero es del proyecto; el codigo RD-PI-PT-NNN, de su ficha tecnica (2026-11-06). ?>
            <div class="rd-pagehead-sub"><strong><?= Html::encode($model->identificador()) ?></strong><?= $model->codigoFicha() ? ' · ficha técnica <span class="mono">' . Html::encode((string) $model->codigoFicha()) . '</span>' : '' ?></div>
        </div>
        <div class="rd-acciones">
            <?php if ($puedeEditarse): ?>
                <?= Html::a('Editar datos', ['update', 'id' => $model->id], ['class' => 'btn btn-outline']) ?>
            <?php endif; ?>
            <?php if ($puedeAgregarFase): ?>
                <?= Html::a('Agregar fase ' . ((int) $model->numeroFase + 1) . ' →', ['create', 'idAnterior' => $model->id], [
                    'class' => 'btn btn-primary',
                    'title' => 'La fase siguiente del mismo proyecto (mismo número), con su propia área, equipo y carpeta económica',
                ]) ?>
            <?php endif; ?>
            <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => '¿Eliminar el ' . $model->identificador() . ' (' . $model->nombre . ')?',
                    'method' => 'post',
                ],
            ]) ?>
        </div>
    </div>

    <?= $this->render('_pasos', ['pasos' => $pasos]) ?>

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
            <?php // Cada especificacion tiene el suyo (en la lista); este es el que toman si alli queda vacio (2026-11-05). ?>
            <div class="rd-dato"><strong>Lugar de entrega por defecto:</strong> <?= Html::encode($model->lugarEntrega ?: '— (el de la configuración del formulario)') ?></div>
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

            <?php // Todas las fases del proyecto (mismo numero), con el estado de su presupuesto. ?>
            <div class="card rd-card-pad">
                <div class="kicker mb-2">Fases del proyecto <?= Html::encode($model->numeroTexto()) ?></div>
                <?php $fases = $model->numeroProyecto !== null ? $model->fases : [$model]; ?>
                <?php foreach ($fases as $fase): ?>
                    <div class="rd-dato">
                        <?php $texto = 'Fase ' . (int) $fase->numeroFase; ?>
                        <?= (int) $fase->id === (int) $model->id ? '<strong>' . Html::encode($texto) . '</strong> (esta)' : Html::a(Html::encode($texto), ['view', 'id' => $fase->id]) ?>
                        · <?= $fase->presupuesto === null ? 'sin presupuesto' : Html::encode($fase->presupuesto->estadoLabel()) ?>
                    </div>
                <?php endforeach; ?>
                <?php if (count($fases) === 1): ?>
                    <div class="rd-nota mt-1">Fase única. La siguiente se agrega con "Agregar fase" cuando esta tenga su presupuesto aprobado.</div>
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
