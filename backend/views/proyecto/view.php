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
 * @var backend\models\Proyecto|null $faseDatosComunes la fase 1 si hoy deja cambiar los datos comunes (solo en fases 2+). Ver ProyectoService::faseParaDatosComunes()
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

            <?php
            // Rediseño del 2026-11-11: arriba lo del PROYECTO (descripcion y datos comunes a todas las fases,
            // en una cuadricula de 2 columnas) y abajo lo de ESTA FASE (area de intervencion y equipo).
            // Todo sale de $model y de sus relaciones; la vista no hace consultas propias.

            // Una orden de cambio no tiene area ni equipo propios a la vista: usa los de su fase.
            $esOrden = $model->esOrdenCambio();
            $deLaFase = $esOrden && $model->faseOrigen !== null ? $model->faseOrigen : $model;
            $variasFases = $deLaFase->tieneVariasFases();

            // Descripcion: una corta sale entera; solo una LARGA (mas de 240 caracteres o con saltos de linea)
            // se corta en 2 lineas con "Ver descripcion completa", que la despliega en el mismo lugar (sin repetirla).
            $descripcion = trim((string) $model->descripcion);
            $descripcionLarga = mb_strlen($descripcion) > 240 || str_contains($descripcion, "\n");

            // Las dos unidades iguales (sin espacios de mas ni mayusculas) van en una sola celda.
            $normalizar = static fn (?string $texto): string => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim((string) $texto)), 'UTF-8');
            $unidadesIguales = $normalizar($model->unidadDependencia) !== '' && $normalizar($model->unidadDependencia) === $normalizar($model->unidadSolicitante);

            // Fase 2 en adelante: nota de que los datos son comunes; con enlace a la fase 1 solo si alli se
            // pueden cambiar hoy ($faseDatosComunes, ver ProyectoService::faseParaDatosComunes()).
            $notaDatosComunes = $model->tieneVariasFases() && (int) $model->numeroFase > 1;

            // "Ver 3 áreas más" / "Ver 1 persona más": cuenta lo que aparece al abrir el desplegable.
            $verMas = static fn (int $cuantos, string $uno, string $varios): string => 'Ver ' . $cuantos . ' ' . ($cuantos === 1 ? $uno : $varios) . ' más';

            // Areas agrupadas por edificio y bloque: los 3 primeros grupos a la vista, el resto en "Ver N áreas más".
            $gruposArea = [];
            foreach ($deLaFase->areasIntervencion as $area) {
                $gruposArea[$area->grupo()][] = $area;
            }
            $lineaArea = static function (string $grupo, array $areas): string {
                $detalles = array_map(static fn ($area): string => Html::encode($area->detalle())
                    . ($area->esManual() ? ' <span class="rd-area-marca" title="Escrita a mano: no está en la infraestructura de la universidad (UsfxGEO)">a mano</span>' : ''), $areas);

                return '<div class="rd-dg-area"><span class="rd-dg-grupo">' . Html::encode($grupo) . '</span> — ' . implode(', ', $detalles) . '</div>';
            };
            $gruposVisibles = array_slice($gruposArea, 0, 3, true);
            $gruposOcultos = array_slice($gruposArea, 3, null, true);

            // Equipo: la responsable primero y las demas en su orden (se ordena aqui, sin volver a la base).
            $equipo = $deLaFase->participantes;
            $responsables = array_values(array_filter($equipo, static fn ($persona) => (bool) $persona->esResponsable));
            $demas = array_values(array_filter($equipo, static fn ($persona) => !$persona->esResponsable));
            // 5 a la vista contando a la responsable; el resto en "Ver N personas más".
            $demasVisibles = array_slice($demas, 0, max(0, 5 - count($responsables)));
            $demasOcultas = array_slice($demas, count($demasVisibles));
            $personaEnLista = static fn ($persona): string => '<div>' . Html::encode($persona->nombre)
                . ' <span class="rd-nota">· ' . Html::encode($persona->rol->nombre ?? '') . '</span></div>';

            // Los enlaces a "Editar datos" solo si se puede editar (en una orden de cambio el area es de su fase).
            $puedeEditarArea = $puedeEditarse && !$esOrden;
            ?>

            <?php if ($descripcion !== ''): ?>
                <p class="rd-dg-desc<?= $descripcionLarga ? ' rd-dg-desc-corta' : '' ?>"><?= nl2br(Html::encode($descripcion)) ?></p>
                <?php if ($descripcionLarga): ?>
                    <?php // Vacio a proposito: al abrirlo, el CSS despliega el parrafo de arriba (redes.css, .rd-dg-desc-mas). ?>
                    <details class="rd-dg-mas rd-dg-desc-mas">
                        <summary><span class="rd-dg-ver">Ver descripción completa</span><span class="rd-dg-ocultar">Ver menos</span></summary>
                    </details>
                <?php endif; ?>
            <?php endif; ?>

            <dl class="rd-dg-datos">
                <div>
                    <dt>Tipo</dt>
                    <dd><?= Html::encode($nombreTipo ?? '—') ?></dd>
                </div>
                <div>
                    <dt>Proceso</dt>
                    <dd><?= Html::encode($model->proceso?->etiqueta() ?? '—') ?></dd>
                </div>
                <?php if ($unidadesIguales): ?>
                    <div class="rd-dg-ancho">
                        <dt>Unidad / dependencia y unidad solicitante</dt>
                        <dd><?= Html::encode($model->unidadDependencia) ?></dd>
                    </div>
                <?php else: ?>
                    <div>
                        <dt>Unidad / dependencia</dt>
                        <dd><?= Html::encode($model->unidadDependencia ?: '—') ?></dd>
                    </div>
                    <div>
                        <dt>Unidad solicitante</dt>
                        <dd><?= Html::encode($model->unidadSolicitante ?: '—') ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <?php if ($notaDatosComunes): ?>
                <div class="rd-nota rd-dg-comunes">
                    <?php if ($faseDatosComunes !== null): ?>
                        Estos datos son comunes a todas las fases y se editan en la <?= Html::a('Fase 1', ['view', 'id' => $faseDatosComunes->id]) ?>.
                    <?php else: ?>
                        Estos datos son comunes a todas las fases y ya no se pueden cambiar (hay una fase aprobada).
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php // Separador entre lo del proyecto y lo de la fase. Con fase unica no se dice "Fase". ?>
            <div class="rd-dg-separador">
                <?php if ($esOrden): ?>
                    <span class="kicker">De la fase</span>
                    <?php if ($variasFases): ?>
                        <?= Html::a('Fase ' . (int) $deLaFase->numeroFase, ['view', 'id' => $deLaFase->id], ['class' => 'pill-fase']) ?>
                        <span class="rd-nota">· la orden de cambio usa el área y el equipo de su fase</span>
                    <?php else: ?>
                        <span class="rd-nota">· la orden de cambio usa el área y el equipo del <?= Html::a('proyecto', ['view', 'id' => $deLaFase->id]) ?></span>
                    <?php endif; ?>
                <?php elseif ($variasFases): ?>
                    <span class="kicker">De esta fase</span>
                    <span class="pill-fase">Fase <?= (int) $model->numeroFase ?></span>
                <?php endif; ?>
            </div>

            <div class="rd-dg-fila">
                <div class="rd-dg-rot">Área de intervención</div>
                <div>
                    <?php if ($gruposArea !== []): ?>
                        <?php foreach ($gruposVisibles as $grupo => $areas): ?>
                            <?= $lineaArea((string) $grupo, $areas) ?>
                        <?php endforeach; ?>
                        <?php if ($gruposOcultos !== []): ?>
                            <details class="rd-dg-mas">
                                <summary><?= $verMas(array_sum(array_map('count', $gruposOcultos)), 'área', 'áreas') ?></summary>
                                <?php foreach ($gruposOcultos as $grupo => $areas): ?>
                                    <?= $lineaArea((string) $grupo, $areas) ?>
                                <?php endforeach; ?>
                            </details>
                        <?php endif; ?>
                    <?php elseif ($deLaFase->ubicacionAnterior() !== ''): ?>
                        <?php // Proyectos registrados antes del 2026-10-28: edificio y bloques del JSON de entonces. ?>
                        <div>
                            <?= Html::encode($deLaFase->ubicacionAnterior()) ?>
                            <span class="badge b-inactivo">ubicación anterior</span>
                        </div>
                        <div class="rd-nota">
                            Registrada antes del cambio. Al <?= $puedeEditarArea ? Html::a('editar los datos', ['update', 'id' => $model->id]) : 'editar los datos' ?> puede elegir sus áreas.
                        </div>
                    <?php else: ?>
                        <div>Sin área registrada.</div>
                        <?php if ($puedeEditarArea): ?>
                            <div class="rd-nota">Se elige en <?= Html::a('Editar datos', ['update', 'id' => $model->id]) ?>.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rd-dg-fila">
                <div class="rd-dg-rot">Equipo del proyecto</div>
                <div>
                    <?php if ($equipo === []): ?>
                        <div>Sin equipo registrado.</div>
                        <?php if ($puedeEditarArea): ?>
                            <div class="rd-nota">Se agrega en <?= Html::a('Editar datos', ['update', 'id' => $model->id]) ?>.</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php foreach ($responsables as $persona): ?>
                            <div class="rd-dg-responsable">
                                <span class="rd-dg-grupo"><?= Html::encode($persona->nombre) ?></span>
                                <span class="rd-nota">· <?= Html::encode($persona->rol->nombre ?? '') ?></span>
                                <span class="badge b-activo">Responsable</span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($demasVisibles !== []): ?>
                            <div class="rd-dg-equipo">
                                <?php foreach ($demasVisibles as $persona): ?>
                                    <?= $personaEnLista($persona) ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($demasOcultas !== []): ?>
                            <details class="rd-dg-mas">
                                <summary><?= $verMas(count($demasOcultas), 'persona', 'personas') ?></summary>
                                <div class="rd-dg-equipo">
                                    <?php foreach ($demasOcultas as $persona): ?>
                                        <?= $personaEnLista($persona) ?>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
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
                    <?php // Cada fase con sus ordenes de cambio debajo (parte 6). ?>
                    <?php foreach ($fases as $fase): ?>
                        <?php foreach (array_merge([$fase], $fase->ordenesCambio) as $fila): ?>
                            <div class="rd-dato<?= $fila->esOrdenCambio() ? ' ms-3' : '' ?>">
                                <?php $texto = $fila->esOrdenCambio() ? $fila->textoOrdenCambio() : 'Fase ' . (int) $fila->numeroFase; ?>
                                <?= (int) $fila->id === (int) $model->id ? '<strong>' . Html::encode($texto) . '</strong> (esta)' : Html::a(Html::encode($texto), ['view', 'id' => $fila->id]) ?>
                                · <?= $fila->presupuesto === null ? 'sin presupuesto' : Html::encode($fila->presupuesto->estadoLabel()) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
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
