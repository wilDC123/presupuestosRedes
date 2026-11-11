<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\web\JqueryAsset;
use yii\widgets\ActiveForm;

/**
 * Textos POR DEFECTO de la carta de entrega (redes.ConfiguracionCarta).
 * Solo llenan las cartas nuevas: cada carta guarda su propia copia, asi que
 * un cambio aqui no altera las ya escritas (salvo ciudad y numero de
 * oficio, que son formato y se leen al imprimir).
 *
 * @var yii\web\View $this
 * @var backend\models\ConfiguracionCarta $configuracion
 * @var int|null $proyecto id del proyecto desde donde se entro (para volver)
 */

$this->title = 'Configuración de la carta de entrega';
$this->params['breadcrumbs'][] = ['label' => 'Configuración', 'url' => ['/configuracion/index']];
$this->params['breadcrumbs'][] = 'Carta de entrega';

// Agregar, quitar y ordenar las filas de "Tratamientos".
$this->registerJsFile('@web/js/carta-configuracion.js', ['depends' => [JqueryAsset::class]]);

$nombreTratamientos = Html::getInputName($configuracion, 'tratamientos');
$tratamientos = is_array($configuracion->tratamientos) ? $configuracion->tratamientos : [];
$filaTratamiento = static function ($clave, string $nombre, bool $femenino) use ($nombreTratamientos): string {
    return '<tr>'
        . '<td>' . Html::textInput($nombreTratamientos . "[$clave][nombre]", $nombre, ['class' => 'form-control', 'maxlength' => 20, 'aria-label' => 'Tratamiento', 'placeholder' => 'ej. Señora']) . '</td>'
        . '<td class="rd-equipo-responsable">' . Html::checkbox($nombreTratamientos . "[$clave][femenino]", $femenino, ['value' => 1, 'aria-label' => 'Cargo en femenino']) . '</td>'
        . '<td class="text-nowrap">'
        . '<button type="button" class="btn btn-link btn-sm rd-tratamiento-subir" title="Subir" aria-label="Subir">↑</button>'
        . '<button type="button" class="btn btn-link btn-sm rd-tratamiento-bajar" title="Bajar" aria-label="Bajar">↓</button>'
        . '<button type="button" class="btn btn-link btn-sm rd-tratamiento-quitar">Quitar</button>'
        . '</td>'
        . '</tr>';
};

$volver = $proyecto !== null
    ? Html::a('← Carta del proyecto', ['index', 'idProyecto' => $proyecto], ['class' => 'btn btn-outline'])
    : Html::a('← Configuración', ['/configuracion/index'], ['class' => 'btn btn-outline']);
?>
<div class="carta-configuracion">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                Textos con los que empieza cada carta nueva. Las cartas ya guardadas no cambian.
            </div>
        </div>
        <div class="rd-acciones"><?= $volver ?></div>
    </div>

    <?php $form = ActiveForm::begin(); ?>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Encabezado</div></div>
        <div class="rd-card-pad">
            <div class="row g-3">
                <div class="col-md-4">
                    <?= $form->field($configuracion, 'ciudad')->textInput(['maxlength' => 60])
                        ->hint('Sale antes de la fecha: "Sucre, 16 de octubre de 2026".') ?>
                </div>
                <div class="col-md-8">
                    <?= $form->field($configuracion, 'formatoOficio')->textInput(['maxlength' => 100])
                        ->hint('{oficio} = número de oficio DTIC del presupuesto; {anio} = año de la carta. Se aplica también al reimprimir.') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Tratamientos</div></div>
        <div class="rd-card-pad">
            <div class="form-group<?= $configuracion->hasErrors('tratamientos') ? ' has-error' : '' ?>">
                <?php // Sin filas, "tratamientos" igual llega (vacio) y el servidor avisa que falta uno. ?>
                <?= Html::hiddenInput($nombreTratamientos, '') ?>
                <table class="table rd-equipo mb-2" id="carta-tratamientos" data-siguiente="<?= count($tratamientos) ?>" style="max-width: 560px">
                    <thead>
                        <tr>
                            <th>Tratamiento</th>
                            <th class="rd-equipo-responsable">Cargo en femenino</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_values($tratamientos) as $i => $fila): ?>
                            <?= $filaTratamiento($i, (string) ($fila['nombre'] ?? ''), !empty($fila['femenino'])) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <template id="carta-tratamiento-fila-modelo"><?= $filaTratamiento('__n__', '', false) ?></template>
                <button type="button" class="btn btn-outline btn-sm" id="carta-agregar-tratamiento">+ Agregar tratamiento</button>
                <div class="hint">Cómo se encabeza al destinatario ("Señora:"). La lista de la carta sale en este orden y una carta
                    nueva empieza con el primero. "Cargo en femenino": el cargo sugerido dice DECANA en vez de DECANO. Las
                    cartas ya guardadas conservan el suyo aunque se quite de aquí.</div>
                <div class="help-block"><?= Html::encode($configuracion->getFirstError('tratamientos')) ?></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Texto</div></div>
        <div class="rd-card-pad">
            <?= $form->field($configuracion, 'plantillaReferencia')->textarea(['rows' => 2, 'maxlength' => 500])
                ->hint('Sin "Ref." (lo pone la carta). {proyecto} = nombre del proyecto; {unidad} = su unidad solicitante; {dependencia} = la unidad beneficiaria. Se guarda en mayúsculas.') ?>
            <?= $form->field($configuracion, 'saludo')->textInput(['maxlength' => 150]) ?>
            <?= $form->field($configuracion, 'plantillaCuerpo')->textarea(['rows' => 10, 'maxlength' => 4000])
                ->hint('Deje una línea en blanco entre párrafos. {proyecto}, {unidad} (la solicitante) y {dependencia} (la beneficiaria) se reemplazan al crear la carta ({facultad}, el nombre de antes, también); {oficio} y {numeroPresupuesto}, al imprimirla.') ?>
            <?= $form->field($configuracion, 'despedida')->textInput(['maxlength' => 150]) ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Firmas</div></div>
        <div class="rd-card-pad">
            <div class="row g-3">
                <?php foreach ([1 => 'Firma izquierda', 2 => 'Firma derecha', 3 => 'Firma de abajo, centrada (V°B°)'] as $n => $titulo): ?>
                    <div class="col-md-4">
                        <div class="rd-dato"><strong><?= $titulo ?></strong></div>
                        <?= $form->field($configuracion, "firma{$n}Prefijo")->textInput(['maxlength' => 20, 'placeholder' => 'ej. V°B°'])
                            ->hint('Opcional. Sale antes del nombre.') ?>
                        <?= $form->field($configuracion, "firma{$n}Nombre")->textInput(['maxlength' => 150])
                            ->hint('Con su tratamiento (Ing., Lic.…). Sin nombre, la firma no sale.') ?>
                        <?= $form->field($configuracion, "firma{$n}Cargo")->textInput(['maxlength' => 200])
                            ->hint('Se guarda en mayúsculas.') ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Pie</div></div>
        <div class="rd-card-pad">
            <?= $form->field($configuracion, 'copia')->textInput(['maxlength' => 200])->hint('Sale como "Copia: …".') ?>
            <?= $form->field($configuracion, 'plantillaAdjunto')->textInput(['maxlength' => 300])
                ->hint('Sale como "Adjunto: …". Al imprimir, {numeroPresupuesto} se reemplaza con el del presupuesto y {especificaciones} con los códigos de las especificaciones técnicas vigentes del proyecto, separados por comas (ej. RD-PI-ET-001, RD-PI-ET-002).') ?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
