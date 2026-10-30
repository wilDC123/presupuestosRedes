<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JqueryAsset;
use yii\widgets\ActiveForm;

/**
 * Formulario de proyecto (crear y editar). Los botones Cancelar / Guardar van
 * en el encabezado de la pagina; el atributo HTML form="proyecto-form" los
 * conecta con este formulario.
 *
 * Las areas de intervencion elegidas se muestran como una lista agrupada
 * por edificio y bloque, y los proyectistas como "chips". Cada una lleva
 * dentro sus <input type="hidden">, que es lo que realmente se envia. web/js/proyecto-form.js agrega y quita chips y, al
 * elegir edificio, bloque y nivel, pide al servidor los de abajo (acciones
 * proyecto/bloques, niveles, ambientes y buscar-ambiente).
 *
 * La unidad solicitante es texto libre; el <datalist> solo SUGIERE lo ya
 * escrito en otros proyectos y las facultades.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $model
 * @var yii\widgets\ActiveForm $form
 * @var array<int, string> $tipos [id => nombre] tipos activos (y el que ya tenia)
 * @var string[] $sugerenciasUnidad
 * @var array<int, array{id: string, nombre: string, usos: int}> $edificios vigentes de UsfxGEO, con cuantos proyectos los usan
 * @var array<int, array{idEdificio: string, idBloqueArea: ?int, numeroNivel: ?string, idEspacio: ?string, texto: string, grupo: string, detalle: string}> $areas Ver ProyectoService::areasDelFormulario()
 * @var string[] $proyectistas nombres disponibles
 * @var array<int, array{etiqueta: string, codigo: string, resumen: string}> $proyectosAprobados
 */

$this->registerJsFile('@web/js/proyecto-form.js', ['depends' => [JqueryAsset::class]]);

$nombreAreas = Html::getInputName($model, 'areas');
$nombreProyectistas = Html::getInputName($model, 'nombresProyectista');
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
    'options' => [
        'data-url-bloques' => Url::to(['bloques']),
        'data-url-niveles' => Url::to(['niveles']),
        'data-url-ambientes' => Url::to(['ambientes']),
        'data-url-buscar' => Url::to(['buscar-ambiente']),
    ],
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

            <?= $form->field($model, 'idTipoProyecto')->dropDownList($tipos, ['prompt' => 'Seleccione el tipo', 'class' => 'form-select']) ?>
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

        <?php // Las dos unidades comparten la lista de sugerencias (el <datalist> de abajo). ?>
        <?= $form->field($model, 'unidadDependencia')->textInput([
            'maxlength' => 250,
            'list' => 'proyecto-unidades-sugeridas',
            'autocomplete' => 'off',
            'placeholder' => 'A dónde va el proyecto. Ej. Canal Universitario, Carrera de Psicología',
        ])->hint('La unidad beneficiada: es la que sale como "Dependencia" en los documentos. Texto libre: facultad, carrera, oficina…') ?>

        <?= $form->field($model, 'unidadSolicitante')->textInput([
            'maxlength' => 250,
            'list' => 'proyecto-unidades-sugeridas',
            'autocomplete' => 'off',
            'placeholder' => 'Quién lo pide, tal como dice la nota. Ej. Rectorado, Decanato de Humanidades',
        ])->hint('Puede ser la misma unidad u otra (ej. el Rector pide cámaras para el Canal Universitario). Al escribir se sugieren las ya usadas.') ?>
        <datalist id="proyecto-unidades-sugeridas">
            <?php foreach ($sugerenciasUnidad as $sugerencia): ?>
                <option value="<?= Html::encode($sugerencia) ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <div class="form-group<?= $model->hasErrors('areas') ? ' has-error' : '' ?>">
            <label for="proyecto-buscar-ambiente"><?= Html::encode($model->getAttributeLabel('areas')) ?></label>
            <?php // Campo vacio previo: si se quitan todas las areas, igual llega "areas" (vacio) y se borran las guardadas. ?>
            <?= Html::hiddenInput($nombreAreas, '') ?>
            <?php // Cada area es un .rd-area con sus 4 hidden; web/js/proyecto-form.js las agrupa por edificio y bloque. ?>
            <div class="rd-areas" id="proyecto-areas" data-nombre="<?= Html::encode($nombreAreas) ?>" data-siguiente="<?= count($areas) ?>">
                <?php foreach ($areas as $i => $area): ?>
                    <div class="rd-area" title="<?= Html::encode($area['texto']) ?>"
                         data-valor="<?= Html::encode(implode('|', [$area['idEdificio'], $area['idBloqueArea'], $area['numeroNivel'], $area['idEspacio']])) ?>"
                         data-grupo="<?= Html::encode($area['grupo']) ?>">
                        <span class="rd-area-detalle"><?= Html::encode($area['detalle']) ?></span>
                        <?php foreach (['idEdificio', 'idBloqueArea', 'numeroNivel', 'idEspacio'] as $campo): ?>
                            <?= Html::hiddenInput($nombreAreas . "[$i][$campo]", (string) $area[$campo]) ?>
                        <?php endforeach; ?>
                        <button type="button" class="x" aria-label="Quitar">×</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="rd-buscar-ambiente">
                <input type="search" id="proyecto-buscar-ambiente" class="form-control" autocomplete="off"
                       placeholder="Buscar edificio o espacio: ej. «ciencias y tecnología», «E303», «laboratorio económicas»">
                <ul class="rd-resultados-ambiente d-none" id="proyecto-resultados-ambiente"></ul>
            </div>

            <div class="rd-nota mt-2 mb-1">O elíjala paso a paso (puede quedarse en el edificio, el bloque o el piso):</div>
            <?php // Edificio y ambiente no son <select>: sus listas son largas y la del navegador se abre muy alta.
            // Son campos que filtran al escribir, con su propia lista debajo; el id elegido queda en el
            // hidden, que es el que lee el resto del paso a paso. En el edificio, los usados en proyectos van primero. ?>
            <div class="rd-combo mb-2">
                <?= Html::hiddenInput(null, '', ['id' => 'proyecto-area-edificio']) ?>
                <?= Html::textInput(null, '', [
                    'id' => 'proyecto-area-edificio-texto',
                    'class' => 'form-control',
                    'placeholder' => 'Edificio: escriba para filtrar (ej. «tecnología», «medicina»)',
                    'autocomplete' => 'off',
                    'data-edificios' => $edificios,
                ]) ?>
                <ul class="rd-resultados-ambiente d-none" id="proyecto-lista-edificios"></ul>
            </div>
            <div class="rd-fila-3">
                <?= Html::dropDownList(null, null, [], ['id' => 'proyecto-area-bloque', 'class' => 'form-select', 'prompt' => 'Bloque (opcional)', 'disabled' => true]) ?>
                <?= Html::dropDownList(null, null, [], ['id' => 'proyecto-area-nivel', 'class' => 'form-select', 'prompt' => 'Piso (opcional)', 'disabled' => true]) ?>
                <div class="rd-combo">
                    <?= Html::hiddenInput(null, '', ['id' => 'proyecto-area-ambiente']) ?>
                    <?= Html::textInput(null, '', [
                        'id' => 'proyecto-area-ambiente-texto',
                        'class' => 'form-control',
                        'placeholder' => 'Espacio (opcional)',
                        'autocomplete' => 'off',
                        'disabled' => true,
                    ]) ?>
                    <ul class="rd-resultados-ambiente d-none" id="proyecto-lista-ambientes"></ul>
                </div>
            </div>
            <button type="button" class="btn btn-outline btn-sm mt-2" id="proyecto-agregar-area" disabled>+ Agregar área</button>
            <div class="hint">Dónde se hace el trabajo. Puede agregar varias (ej. tres laboratorios). Los edificios y espacios
                salen de la infraestructura de la universidad (UsfxGEO): si falta alguno, lo registra su responsable, no este sistema.</div>
            <div class="help-block"><?= Html::encode($model->getFirstError('areas')) ?></div>
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
