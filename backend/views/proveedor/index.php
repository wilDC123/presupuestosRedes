<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Proveedores: listado (izquierda) y panel "Proveedor seleccionado" (derecha).
 * Se selecciona un proveedor con el enlace de su nombre (?id=).
 *
 * @var yii\web\View $this
 * @var array<int, array{proveedor: backend\models\Proveedor, cotizaciones: int}> $filas Ver ProveedorService::listar()
 * @var string $buscar texto del buscador ('' = todos)
 * @var backend\models\Proveedor|null $seleccionado
 * @var array{cotizaciones: backend\models\Cotizacion[], montoTotal: float, ultimaCotizacion: backend\models\Cotizacion|null}|null $resumen Ver ProveedorService::resumen()
 */

$this->title = 'Proveedores';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="proveedor-index">

    <div class="rd-pagehead">
        <h1><?= Html::encode($this->title) ?></h1>
        <div class="rd-acciones">
            <?= Html::beginForm(['index'], 'get', ['class' => 'rd-acciones']) ?>
                <?= Html::textInput('buscar', $buscar, [
                    'class' => 'form-control rd-buscar',
                    'placeholder' => 'Buscar por razón social o NIT...',
                ]) ?>
                <?= Html::submitButton('Buscar', ['class' => 'btn btn-outline']) ?>
            <?= Html::endForm() ?>
            <?= Html::a('+ Nuevo proveedor', ['create'], ['class' => 'btn btn-primary']) ?>
        </div>
    </div>

    <div class="rd-cols">

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Proveedores registrados</div>
                <div class="kicker"><?= count($filas) ?> proveedores</div>
            </div>

            <?php if ($filas === []): ?>
                <div class="rd-vacio">
                    <?= $buscar === ''
                        ? 'Todavía no hay proveedores registrados.'
                        : 'Ningún proveedor coincide con "' . Html::encode($buscar) . '".' ?>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>Contacto</th>
                            <th>Cotiz.</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $fila): ?>
                            <?php
                            $proveedor = $fila['proveedor'];
                            $esSeleccionado = $seleccionado !== null && $seleccionado->id === $proveedor->id;
                            $datosFiscales = implode(' — ', array_filter([
                                $proveedor->nit ? 'NIT ' . $proveedor->nit : null,
                                $proveedor->direccion,
                            ]));
                            ?>
                            <tr class="<?= $esSeleccionado ? 'rd-fila-activa' : '' ?>">
                                <td>
                                    <div class="rd-celda-doble">
                                        <div class="avatar"><?= Html::encode($proveedor->iniciales()) ?></div>
                                        <div>
                                            <div class="rd-celda-titulo">
                                                <?= Html::a(Html::encode($proveedor->razonSocial), ['index', 'id' => $proveedor->id, 'buscar' => $buscar ?: null]) ?>
                                            </div>
                                            <div class="rd-celda-sub"><?= Html::encode($datosFiscales) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?= Html::encode($proveedor->email ?: ($proveedor->contacto ?? '')) ?></div>
                                    <div class="rd-celda-sub"><?= Html::encode($proveedor->telefono ?? '') ?></div>
                                </td>
                                <td class="mono"><?= $fila['cotizaciones'] ?></td>
                                <td>
                                    <?= $proveedor->activo
                                        ? '<span class="badge b-activo">Activo</span>'
                                        : '<span class="badge b-inactivo">Inactivo</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="rd-col-1 rd-pila">
            <?php if ($seleccionado === null): ?>
                <div class="card rd-card-pad">
                    <div class="kicker mb-2">Proveedor seleccionado</div>
                    <div class="rd-nota">Haga clic en el nombre de un proveedor para ver sus cotizaciones y acciones.</div>
                </div>
            <?php else: ?>
                <div class="card rd-card-pad">
                    <div class="kicker mb-3">Proveedor seleccionado</div>
                    <div class="rd-celda-doble mb-3">
                        <div class="avatar"><?= Html::encode($seleccionado->iniciales()) ?></div>
                        <div>
                            <div class="rd-celda-titulo"><?= Html::encode($seleccionado->razonSocial) ?></div>
                            <div class="rd-celda-sub"><?= Html::encode($seleccionado->contacto ?? '') ?></div>
                        </div>
                    </div>

                    <div class="rd-dato">
                        <strong>Cotizaciones registradas:</strong>
                        <?php if ($resumen['cotizaciones'] === []): ?>
                            ninguna
                        <?php else: ?>
                            <?= Html::encode(implode(', ', array_map(
                                static fn ($cotizacion) => 'N.° ' . ($cotizacion->numeroReferencia ?: $cotizacion->id),
                                $resumen['cotizaciones']
                            ))) ?>
                        <?php endif; ?>
                    </div>
                    <div class="rd-dato">
                        <strong>Monto total cotizado:</strong>
                        Bs. <?= Yii::$app->formatter->asDecimal($resumen['montoTotal'], 2) ?>
                    </div>
                    <div class="rd-dato">
                        <strong>Última cotización:</strong>
                        <?= Html::encode($resumen['ultimaCotizacion']?->fechaFormateada() ?: '—') ?>
                    </div>
                </div>

                <div class="card rd-card-pad">
                    <div class="kicker mb-3">Acciones</div>
                    <div class="rd-pila">
                        <?= Html::a('Editar datos', ['update', 'id' => $seleccionado->id], ['class' => 'btn btn-outline']) ?>
                        <?= Html::a('Ver cotizaciones vinculadas', ['/cotizacion/index', 'idProveedor' => $seleccionado->id], ['class' => 'btn btn-outline']) ?>
                        <?= Html::a('Eliminar proveedor', ['delete', 'id' => $seleccionado->id], [
                            'class' => 'btn btn-danger',
                            'data' => [
                                'confirm' => '¿Eliminar a "' . $seleccionado->razonSocial . '"?',
                                'method' => 'post',
                            ],
                        ]) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>
