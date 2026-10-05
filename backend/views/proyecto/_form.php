<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JqueryAsset;
use yii\widgets\ActiveForm;

/**
 * Formulario de proyecto (crear y editar). Los botones Cancelar / Guardar van
 * en el encabezado de la pagina; el atributo HTML form="proyecto-form" los
 * conecta con este formulario.
 *
 * Los bloques y proyectistas elegidos se muestran como "chips". Cada chip
 * lleva dentro un <input type="hidden">, que es lo que realmente se envia.
 * web/js/proyecto-form.js agrega y quita chips y actualiza edificios y
 * bloques cuando cambia la facultad.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $model
 * @var yii\widgets\ActiveForm $form
 * @var array<string, string> $tipos [codigo => nombre]
 * @var array<string, string> $facultades [codigo => nombre]
 * @var array<string, string> $edificios edificios de la facultad actual
 * @var array<string, string> $bloques bloques del edificio actual
 * @var string[] $proyectistas nombres disponibles
 * @var array $arbolUbicacion facultades -> edificios -> bloques, para el JS
 * @var array<int, array{etiqueta: string, codigo: string, resumen: string}> $proyectosAprobados
 */

$this->registerJsFile('@web/js/proyecto-form.js', ['depends' => [JqueryAsset::class]]);

$nombreBloques = Html::getInputName($model, 'codigosBloque');
$nombreProyectistas = Html::getInputName($model, 'nombresProyectista');
$bloquesElegidos = (array) $model->codigosBloque;
$proyectistasElegidos = (array) $model->nombresProyectista;

// Opciones del desplegable de fase anterior; cada <option> lleva como
// atributos data-* lo que se muestra en "Proyecto vinculado".
$opcionesFase = [];
$atributosFase = [];

foreach ($proyectosAprobados as $id => $aprobado) {
    $opcionesFase[$id] = $aprobado['etiqueta'];
    $atributosFase[$id] = ['data-codigo' => $aprobado['codigo'], 'data-resumen' => $aprobado['resumen']];
}

$vinculado = $proyectosAprobados[(int) $model->idProyectoAnterior] ?? null;
?>
<?php $form = ActiveForm::begin([
    'id' => 'proyecto-form',
    'options' => ['data-ubicacion' => Json::htmlEncode($arbolUbicacion)],
]); ?>

<div class="rd-cols proyecto-form">

    <div class="card rd-col-2 rd-card-pad">
        <div class="rd-card-head-plano">
            <div class="rd-card-titulo">Datos generales</div>
            <span class="pill-fase">Fase <span id="proyecto-fase"><?= (int) $model->numeroFase ?></span></span>
        </div>

        <div class="rd-fila-2">
            <div class="field">
                <label>Código de proyecto</label>
                <input type="text" class="form-control" disabled
                       value="<?= Html::encode($model->codigo ?: 'Se genera al guardar') ?>">
                <div class="hint">Generado automáticamente al guardar. No se puede cambiar.</div>
            </div>

            <?= $form->field($model, 'tipo')->dropDownList($tipos, ['prompt' => 'Seleccione el tipo', 'class' => 'form-select']) ?>
        </div>

        <?= $form->field($model, 'nombre')->textInput([
            'maxlength' => 300,
            'placeholder' => 'Ej. Ampliación de red — Facultad de Tecnología',
        ]) ?>

        <?= $form->field($model, 'descripcion')->textarea([
            'rows' => 3,
            'maxlength' => 1000,
            'placeholder' => 'Alcance general del proyecto...',
        ]) ?>

        <div class="rd-fila-2">
            <?= $form->field($model, 'codigoFacultad')->dropDownList($facultades, [
                'prompt' => 'Seleccione la facultad',
                'class' => 'form-select',
                'id' => 'proyecto-facultad',
            ]) ?>

            <?= $form->field($model, 'codigoEdificio')->dropDownList($edificios, [
                'prompt' => 'Seleccione el edificio',
                'class' => 'form-select',
                'id' => 'proyecto-edificio',
            ]) ?>
        </div>

        <div class="form-group<?= $model->hasErrors('codigosBloque') ? ' has-error' : '' ?>">
            <label for="proyecto-agregar-bloque"><?= Html::encode($model->getAttributeLabel('codigosBloque')) ?></label>
            <?php // Campo vacio previo: si se quitan todos los chips, igual llega "codigosBloque" (vacio) y se borran los guardados. ?>
            <?= Html::hiddenInput($nombreBloques, '') ?>
            <div class="rd-chips" id="proyecto-bloques" data-nombre="<?= Html::encode($nombreBloques) ?>[]">
                <?php foreach ($bloquesElegidos as $codigo): ?>
                    <?php if (isset($bloques[$codigo])): ?>
                        <span class="chip" data-valor="<?= Html::encode($codigo) ?>">
                            <?= Html::encode($bloques[$codigo]) ?>
                            <?= Html::hiddenInput($nombreBloques . '[]', $codigo) ?>
                            <button type="button" class="x" aria-label="Quitar">×</button>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?= Html::dropDownList(null, null, $bloques, [
                'id' => 'proyecto-agregar-bloque',
                'class' => 'form-select',
                'prompt' => '+ Agregar bloque...',
            ]) ?>
            <div class="hint">Un proyecto puede involucrar uno o varios bloques del edificio elegido.</div>
            <div class="help-block"><?= Html::encode($model->getFirstError('codigosBloque')) ?></div>
        </div>

        <div class="form-group<?= $model->hasErrors('nombresProyectista') ? ' has-error' : '' ?>">
            <label for="proyecto-agregar-proyectista"><?= Html::encode($model->getAttributeLabel('nombresProyectista')) ?></label>
            <?= Html::hiddenInput($nombreProyectistas, '') ?>
            <div class="rd-chips" id="proyecto-proyectistas" data-nombre="<?= Html::encode($nombreProyectistas) ?>[]">
                <?php foreach ($proyectistasElegidos as $nombre): ?>
                    <span class="chip" data-valor="<?= Html::encode($nombre) ?>">
                        <?= Html::encode($nombre) ?>
                        <?= Html::hiddenInput($nombreProyectistas . '[]', $nombre) ?>
                        <button type="button" class="x" aria-label="Quitar">×</button>
                    </span>
                <?php endforeach; ?>
            </div>
            <?= Html::dropDownList(null, null, array_combine($proyectistas, $proyectistas), [
                'id' => 'proyecto-agregar-proyectista',
                'class' => 'form-select',
                'prompt' => '+ Agregar proyectista...',
            ]) ?>
            <div class="help-block"><?= Html::encode($model->getFirstError('nombresProyectista')) ?></div>
        </div>
    </div>

    <div class="card rd-col-1 rd-card-pad">
        <div class="rd-card-titulo mb-2">Continuidad de fases</div>
        <p class="rd-nota">
            Si este proyecto amplía una intervención ya aprobada, seleccione el proyecto de la fase
            anterior. Un proyecto aprobado nunca se modifica: la ampliación siempre genera un
            proyecto nuevo, con su propio código.
        </p>

        <?= $form->field($model, 'idProyectoAnterior')->dropDownList($opcionesFase, [
            'prompt' => 'Ninguno — proyecto nuevo',
            'class' => 'form-select',
            'id' => 'proyecto-fase-anterior',
            'options' => $atributosFase,
        ])->hint($proyectosAprobados === [] ? 'Todavía no hay proyectos con presupuesto aprobado.' : '') ?>

        <div class="rd-vinculado<?= $vinculado === null ? ' d-none' : '' ?>" id="proyecto-vinculado">
            <div class="kicker">Proyecto vinculado</div>
            <div class="rd-celda-titulo" id="proyecto-vinculado-codigo"><?= Html::encode($vinculado['codigo'] ?? '') ?></div>
            <div class="rd-celda-sub" id="proyecto-vinculado-resumen"><?= Html::encode($vinculado['resumen'] ?? '') ?></div>
        </div>
    </div>

</div>

<?php ActiveForm::end(); ?>
