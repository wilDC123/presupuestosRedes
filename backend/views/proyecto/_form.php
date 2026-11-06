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
 * por edificio y bloque. Cada una lleva dentro sus <input type="hidden">,
 * que es lo que realmente se envia. El equipo del proyecto es una tabla:
 * una fila por persona (persona, rol y si es la responsable). web/js/proyecto-form.js
 * agrega y quita areas y filas del equipo y, al
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
 * @var array<int, string> $procesos [id => "PI – nombre"] procesos activos (y el que ya tenia)
 * @var string[] $sugerenciasUnidad
 * @var array<int, array{id: string, nombre: string, usos: int}> $edificios vigentes de UsfxGEO, con cuantos proyectos los usan
 * @var array<int, array{idEdificio: string, idBloqueArea: ?int, numeroNivel: ?string, idEspacio: ?string, texto: string, grupo: string, detalle: string}> $areas Ver ProyectoService::areasDelFormulario()
 * @var string[] $personal nombres para el desplegable "Persona" (activos y los ya elegidos)
 * @var array<int, string> $roles [id => nombre] para el desplegable "Rol" (activos y los ya elegidos)
 * @var array<int, array{etiqueta: string, codigo: string, resumen: string}> $proyectosAprobados
 */

$this->registerJsFile('@web/js/proyecto-form.js', ['depends' => [JqueryAsset::class]]);

$nombreAreas = Html::getInputName($model, 'areas');
$nombreEquipo = Html::getInputName($model, 'equipo');
$nombreResponsable = Html::getInputName($model, 'equipoResponsable');
$equipo = array_filter(is_array($model->equipo) ? $model->equipo : [], 'is_array');
$numericas = array_filter(array_keys($equipo), 'is_int');
$siguienteFila = $numericas === [] ? 0 : max($numericas) + 1;
$opcionesPersona = $personal === [] ? [] : array_combine($personal, $personal);

// Una fila del equipo. $clave es la de $model->equipo ('__n__' en la fila
// modelo que copia el JS); el radio "Responsable" envia esa clave.
$filaEquipo = static function ($clave, string $nombre, string $idRol, bool $responsable) use ($nombreEquipo, $nombreResponsable, $opcionesPersona, $roles): string {
    return '<tr class="rd-equipo-fila">'
        . '<td>' . Html::dropDownList($nombreEquipo . "[$clave][nombre]", $nombre, $opcionesPersona, ['class' => 'form-select', 'prompt' => 'Elija la persona...', 'aria-label' => 'Persona']) . '</td>'
        . '<td>' . Html::dropDownList($nombreEquipo . "[$clave][idRol]", $idRol, $roles, ['class' => 'form-select', 'prompt' => 'Elija el rol...', 'aria-label' => 'Rol']) . '</td>'
        . '<td class="rd-equipo-responsable">' . Html::radio($nombreResponsable, $responsable, ['value' => $clave, 'aria-label' => 'Responsable del proyecto']) . '</td>'
        . '<td><button type="button" class="btn btn-link btn-sm rd-equipo-quitar">Quitar</button></td>'
        . '</tr>';
};

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

        <?php // A todo el ancho: los nombres de los procesos son largos. ?>
        <?= $form->field($model, 'idProceso')
            ->dropDownList($procesos, ['prompt' => 'Seleccione el proceso', 'class' => 'form-select'])
            ->hint('Qué proceso lleva adelante el proyecto. Lo normal es PI; otros procesos (despliegue, gestión de la red…) también registran sus requerimientos como proyecto.') ?>

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

        <?php // web/js/proyecto-form.js lo llena con "Administración de la <unidad>" si la unidad / dependencia es una
        // facultad, mientras el usuario no lo haya cambiado (misma regla que Proyecto::lugarEntregaSugerido()). ?>
        <?= $form->field($model, 'lugarEntrega')->textInput([
            'maxlength' => 300,
            'placeholder' => 'Dónde se entregan los bienes. Ej. Administración de la Facultad de Ciencias Agrarias',
        ])->hint('Cada especificación técnica tiene su propio lugar de entrega: se escribe en la lista de ítems. Este es el que toma la especificación si allí se deja vacío. Si la unidad / dependencia es una facultad, se sugiere su administración. Vacío = el de la configuración del formulario de especificación.') ?>

        <div class="form-group<?= $model->hasErrors('areas') ? ' has-error' : '' ?>">
            <label for="proyecto-buscar-ambiente"><?= Html::encode($model->getAttributeLabel('areas')) ?></label>
            <?php // Campo vacio previo: si se quitan todas las areas, igual llega "areas" (vacio) y se borran las guardadas. ?>
            <?= Html::hiddenInput($nombreAreas, '') ?>
            <?php // Cada area es un .rd-area con sus hidden: los 4 id de UsfxGEO, o "manual" y los 4 nombres si se
            // escribio a mano. web/js/proyecto-form.js las agrupa por edificio y bloque; data-valor evita repetidas
            // (misma regla que valorDe() en el js). ?>
            <div class="rd-areas" id="proyecto-areas" data-nombre="<?= Html::encode($nombreAreas) ?>" data-siguiente="<?= count($areas) ?>">
                <?php foreach ($areas as $i => $area): ?>
                    <?php $campos = $area['manual'] ? ['manual', 'edificio', 'bloque', 'nivel', 'ambiente'] : ['idEdificio', 'idBloqueArea', 'numeroNivel', 'idEspacio']; ?>
                    <?php $valor = $area['manual']
                        ? 'manual|' . mb_strtolower(implode('|', [$area['edificio'], $area['bloque'], $area['nivel'], $area['ambiente']]), 'UTF-8')
                        : implode('|', [$area['idEdificio'], $area['idBloqueArea'], $area['numeroNivel'], $area['idEspacio']]); ?>
                    <div class="rd-area<?= $area['manual'] ? ' rd-area-manual' : '' ?>" title="<?= Html::encode($area['texto']) ?>"
                         data-valor="<?= Html::encode($valor) ?>"
                         data-grupo="<?= Html::encode($area['grupo']) ?>">
                        <span class="rd-area-detalle"><?= Html::encode($area['detalle']) ?></span>
                        <?php if ($area['manual']): ?><span class="rd-area-marca">a mano</span><?php endif; ?>
                        <?php foreach ($campos as $campo): ?>
                            <?= Html::hiddenInput($nombreAreas . "[$i][$campo]", $campo === 'manual' ? '1' : (string) $area[$campo]) ?>
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
            <button type="button" class="btn btn-link btn-sm mt-2" id="proyecto-abrir-manual">¿No está en la lista? Escríbala a mano</button>

            <?php // Para un edificio en construccion o que aun no esta en UsfxGEO (o un bloque o espacio nuevo de uno
            // que si esta). Al abrirlo se copia lo elegido en el paso a paso: solo se completa lo que falta. ?>
            <div class="rd-area-manual-form d-none mt-2" id="proyecto-area-manual">
                <div class="rd-nota mb-1">Escriba el área tal como se conoce. Solo el edificio es obligatorio.</div>
                <?= Html::textInput(null, '', ['id' => 'proyecto-manual-edificio', 'class' => 'form-control mb-2', 'maxlength' => 200,
                    'placeholder' => 'Edificio (ej. Bloque de Ingeniería Civil, en construcción)']) ?>
                <div class="rd-fila-3">
                    <?= Html::textInput(null, '', ['id' => 'proyecto-manual-bloque', 'class' => 'form-control', 'maxlength' => 200, 'placeholder' => 'Bloque (opcional)']) ?>
                    <?= Html::textInput(null, '', ['id' => 'proyecto-manual-nivel', 'class' => 'form-control', 'maxlength' => 100, 'placeholder' => 'Piso (opcional)']) ?>
                    <?= Html::textInput(null, '', ['id' => 'proyecto-manual-ambiente', 'class' => 'form-control', 'maxlength' => 250, 'placeholder' => 'Espacio (opcional)']) ?>
                </div>
                <button type="button" class="btn btn-outline btn-sm mt-2" id="proyecto-agregar-manual">+ Agregar área escrita a mano</button>
                <button type="button" class="btn btn-link btn-sm mt-2" id="proyecto-cerrar-manual">Cancelar</button>
            </div>

            <div class="hint">Dónde se hace el trabajo. Puede agregar varias (ej. tres laboratorios). Los edificios y espacios
                salen de la infraestructura de la universidad (UsfxGEO); si uno no está (ej. en construcción), escríbalo a mano.</div>
            <div class="help-block"><?= Html::encode($model->getFirstError('areas')) ?></div>
        </div>

        <div class="form-group<?= $model->hasErrors('equipo') ? ' has-error' : '' ?>">
            <label for="proyecto-agregar-persona"><?= Html::encode($model->getAttributeLabel('equipo')) ?></label>
            <?php // Sin filas, "equipo" igual llega (vacio) y el servidor avisa que falta el responsable. ?>
            <?= Html::hiddenInput($nombreEquipo, '') ?>
            <table class="table rd-equipo mb-2" id="proyecto-equipo" data-siguiente="<?= $siguienteFila ?>">
                <thead>
                    <tr>
                        <th>Persona</th>
                        <th>Rol</th>
                        <th class="rd-equipo-responsable">Responsable</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipo as $clave => $fila): ?>
                        <?= $filaEquipo(
                            $clave,
                            (string) ($fila['nombre'] ?? ''),
                            (string) ($fila['idRol'] ?? ''),
                            $model->equipoResponsable !== null && (string) $model->equipoResponsable === (string) $clave
                        ) ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <template id="proyecto-equipo-fila-modelo"><?= $filaEquipo('__n__', '', '', false) ?></template>
            <button type="button" class="btn btn-outline btn-sm" id="proyecto-agregar-persona">+ Agregar persona</button>
            <div class="hint">Quiénes trabajan en el proyecto y con qué rol. Marque al responsable del proyecto (normalmente el
                proyectista; en un mantenimiento, quien lo administra). Una persona puede tener dos roles. Las personas y
                los roles se agregan en Configuración → Personal y Roles del proyecto.</div>
            <div class="help-block"><?= Html::encode($model->getFirstError('equipo')) ?></div>
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
