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
 * @var bool $puedeEditarse
 * @var bool $estaAprobado
 * @var int $totalItems items en la lista del proyecto
 * @var bool $listaEditable Ver ListaItemsService::puedeEditarse()
 */

$this->title = $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->codigo ?: '#' . $model->id;

$presupuesto = $model->presupuesto;
$formato = Yii::$app->formatter;
?>
<div class="proyecto-view">

    <div class="rd-pagehead">
        <div>
            <h1>
                <?= Html::encode($this->title) ?>
                <span class="pill-fase">Fase <?= (int) $model->numeroFase ?></span>
            </h1>
            <div class="rd-pagehead-sub mono"><?= Html::encode($model->codigo ?: 'Sin código') ?></div>
        </div>
        <div class="rd-acciones">
            <?php if ($puedeEditarse): ?>
                <?= Html::a('Editar datos', ['update', 'id' => $model->id], ['class' => 'btn btn-outline']) ?>
            <?php endif; ?>
            <?php if ($estaAprobado): ?>
                <?= Html::a('Crear fase siguiente →', ['create', 'idAnterior' => $model->id], ['class' => 'btn btn-primary']) ?>
            <?php endif; ?>
            <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => '¿Eliminar el proyecto ' . ($model->codigo ?: $model->nombre) . '?',
                    'method' => 'post',
                ],
            ]) ?>
        </div>
    </div>

    <div class="rd-pasos">
        <?php foreach ($pasos as $numero => $paso): ?>
            <?php
            $contenido = '<span class="rd-paso-num">' . ($paso['estado'] === 'hecho' ? '✓' : $numero + 1) . '</span>'
                . '<span class="rd-paso-titulo">' . Html::encode($paso['titulo']) . '</span>';
            $clase = 'rd-paso rd-paso-' . $paso['estado'];
            ?>
            <?= $paso['url'] === null
                ? Html::tag('span', $contenido, ['class' => $clase . ' rd-paso-sin-pantalla', 'title' => 'Esta pantalla todavía no está disponible'])
                : Html::a($contenido, $paso['url'], ['class' => $clase]) ?>
        <?php endforeach; ?>
    </div>

    <div class="rd-cols">

        <div class="card rd-col-2 rd-card-pad">
            <div class="rd-card-titulo mb-3">Datos generales</div>

            <div class="rd-dato"><strong>Tipo:</strong> <?= Html::encode($nombreTipo ?? '—') ?></div>
            <div class="rd-dato"><strong>Facultad:</strong> <?= Html::encode($model->facultad ?: '—') ?></div>
            <div class="rd-dato"><strong>Edificio:</strong> <?= Html::encode($model->edificio ?: '—') ?></div>
            <div class="rd-dato">
                <strong>Bloques:</strong>
                <?php if ($model->bloques === []): ?>
                    —
                <?php else: ?>
                    <?php foreach ($model->bloques as $bloque): ?>
                        <span class="chip"><?= Html::encode($bloque->nombre) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="rd-dato">
                <strong>Proyectistas:</strong>
                <?php if ($model->proyectistas === []): ?>
                    —
                <?php else: ?>
                    <?php foreach ($model->proyectistas as $persona): ?>
                        <span class="chip"><?= Html::encode($persona->nombre) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="rd-dato"><strong>Creado:</strong> <?= $formato->asDate($model->fechaCreacion, 'php:d/m/Y') ?></div>

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
                    <?= Html::a('Iniciar presupuesto →', ['/presupuesto/create', 'idProyecto' => $model->id], ['class' => 'btn btn-primary']) ?>
                <?php else: ?>
                    <div class="rd-dato">
                        <span class="<?= $presupuesto->estadoBadgeClass() ?>"><?= Html::encode($presupuesto->estadoLabel()) ?></span>
                    </div>
                    <div class="rd-dato"><strong>Total:</strong> Bs. <?= $formato->asDecimal((float) $monto, 2) ?></div>
                    <?= Html::a('Ver presupuesto →', ['/presupuesto/view', 'id' => $presupuesto->id], ['class' => 'btn btn-outline']) ?>
                <?php endif; ?>
                <?php if (!$puedeEditarse): ?>
                    <p class="rd-nota mt-3 mb-0">
                        Este proyecto ya no se modifica. Una ampliación se registra como un proyecto
                        nuevo de fase siguiente, con su propio código.
                    </p>
                <?php endif; ?>
            </div>

            <div class="card rd-card-pad">
                <div class="kicker mb-2">Continuidad de fases</div>
                <div class="rd-dato">
                    <strong>Fase anterior:</strong>
                    <?= $model->proyectoAnterior === null
                        ? 'ninguna (fase 1)'
                        : Html::a(Html::encode($model->proyectoAnterior->etiqueta()), ['view', 'id' => $model->proyectoAnterior->id]) ?>
                </div>
                <div class="rd-dato">
                    <strong>Fases siguientes:</strong>
                    <?php if ($model->fasesSiguientes === []): ?>
                        ninguna
                    <?php else: ?>
                        <?php foreach ($model->fasesSiguientes as $siguiente): ?>
                            <div><?= Html::a(Html::encode($siguiente->etiqueta()), ['view', 'id' => $siguiente->id]) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>
