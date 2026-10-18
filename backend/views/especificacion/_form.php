<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JqueryAsset;
use yii\widgets\ActiveForm;

/**
 * Formulario de una ficha de la libreria: los datos de la ficha (titulo,
 * categoria, activo) y su contenido tecnico, que se guarda en una VERSION
 * (modelo sugerido, garantia, adjunto y la tabla de caracteristicas).
 *
 * Si la version actual ya se uso en formularios NET emitidos, guardar cambios
 * en el contenido crea la version siguiente (EspecificacionService::guardarVersion()).
 *
 * ActiveForm valida en el navegador (jQuery) las reglas de los modelos; la
 * tabla de caracteristicas la valida el servidor al guardar. El JS de la
 * tabla y de la extraccion esta en web/js/especificacion-form.js.
 *
 * @var yii\web\View $this
 * @var backend\models\Especificacion $model
 * @var backend\models\EspecificacionVersion $version valores del contenido tecnico
 * @var array<int, array{nombre: ?string, valor: ?string}> $filas caracteristicas
 * @var int $emisiones formularios NET emitidos con la version actual
 * @var int|null $numeroBloqueado numero de la version actual si ya se emitio
 * @var string[] $errores errores del contenido tecnico (filas, largos)
 * @var string|null $garantiaPorDefecto Ver ConfiguracionEspecificacion
 * @var array<int, string> $categorias Ver CategoriaService::opciones()
 * @var array<int, string> $items Ver SubcatalogoItemService::opciones()
 */

$this->registerJsFile('@web/js/especificacion-form.js', ['depends' => [JqueryAsset::class]]);

/**
 * Fila de la tabla de caracteristicas. $n es su indice en Caracteristicas[n][...];
 * el JS usa "__n__" como marcador y renumera las filas antes de enviar.
 */
$fila = static fn (string $n, string $nombre, string $valor): string =>
    '<tr class="esp-fila">'
    . '<td>' . Html::textInput("Caracteristicas[$n][nombre]", $nombre, ['class' => 'form-control esp-nombre', 'maxlength' => 200, 'placeholder' => 'Ej. Calibre del conductor', 'aria-label' => 'Característica']) . '</td>'
    . '<td>' . Html::textarea("Caracteristicas[$n][valor]", $valor, ['class' => 'form-control esp-valor', 'rows' => 1, 'placeholder' => 'Ej. 23 AWG', 'aria-label' => 'Valor']) . '</td>'
    . '<td class="rd-num text-nowrap">'
    . '<button type="button" class="rd-quitar esp-subir" aria-label="Subir fila" title="Subir">↑</button>'
    . '<button type="button" class="rd-quitar esp-bajar" aria-label="Bajar fila" title="Bajar">↓</button>'
    . '<button type="button" class="rd-quitar esp-quitar" aria-label="Quitar fila" title="Quitar">×</button>'
    . '</td>'
    . '</tr>';
?>
<div class="especificacion-form">

    <?php $form = ActiveForm::begin([
        'id' => 'especificacion-form',
        'options' => ['enctype' => 'multipart/form-data'],
    ]); ?>

    <?php if ($errores !== []): ?>
        <div class="alert alert-danger">
            <strong>No se guardó. Revise el contenido técnico:</strong>
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?= Html::encode($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="rd-card-head"><div class="rd-card-titulo">Ficha</div></div>
        <div class="rd-card-pad">
            <?= $form->field($model, 'titulo')->textInput(['maxlength' => 300])
                ->hint('Como aparece en el formulario NET, ej. "Cable de red Categoría 6, 100% cobre tipo 23 AWG".') ?>

            <?= $form->field($model, 'idCategoria')->dropDownList($categorias, ['prompt' => 'Seleccione una categoría'])
                ->hint('Las mismas categorías del subcatálogo. Sirve para encontrar la ficha en la librería y para sugerirla primero a los ítems de esa categoría.') ?>

            <?= $form->field($model, 'idSubcatalogo')->dropDownList($items, ['prompt' => '— Ninguno —'])
                ->hint('Solo informativo: el ítem para el que se pensó la ficha. No limita dónde se puede usar; el enlace real se hace en la lista de ítems de cada proyecto.') ?>

            <?= $form->field($model, 'activo')->checkbox() ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="rd-card-head">
            <div class="rd-card-titulo">Contenido técnico</div>
            <?php if (!$model->isNewRecord && $version->numero !== null): ?>
                <div class="kicker">Versión actual: v<?= (int) $version->numero ?></div>
            <?php endif; ?>
        </div>
        <div class="rd-card-pad">

            <?php if ($numeroBloqueado !== null): ?>
                <div class="alert alert-secondary">
                    La v<?= $numeroBloqueado ?> ya se usó en <?= $emisiones ?> formulario(s) NET emitido(s) y no se modifica.
                    Si cambia el contenido técnico, se guardará como <strong>v<?= $numeroBloqueado + 1 ?></strong>; los
                    formularios ya emitidos siguen diciendo lo mismo. Cambiar solo el título, la categoría o "activo" no
                    crea una versión nueva.
                </div>
            <?php endif; ?>

            <div class="card mb-3">
                <div class="rd-card-pad">
                    <?= $form->field($model, 'archivoEspecificacion')->fileInput(['accept' => '.pdf,.docx'])
                        ->hint('Opcional. Suba el documento original (DOCX o PDF, máximo 10 MB) y presione "Extraer datos" para llenar el título, el modelo sugerido, la garantía y la tabla. De un DOCX se leen las características; de un PDF solo los datos de cabecera. El archivo queda guardado como adjunto.') ?>

                    <button type="button" id="btn-extraer-metadatos" class="btn btn-outline"
                            data-url="<?= Html::encode(Url::to(['especificacion/extraer-metadatos'])) ?>">
                        Extraer datos
                    </button>

                    <div id="extraccion-mensaje" class="mt-2"></div>
                </div>
            </div>

            <?= $form->field($version, 'modeloSugerido')->textInput(['maxlength' => 200])
                ->hint('Sale en el formulario como "Se sugiere … o similar/superior". Opcional.') ?>

            <label class="form-label" for="esp-tabla">Características</label>
            <table class="table" id="esp-tabla">
                <thead>
                    <tr>
                        <th style="width: 30%">Característica</th>
                        <th>Valor</th>
                        <th class="rd-num"></th>
                    </tr>
                </thead>
                <tbody id="esp-filas">
                    <?php foreach ($filas as $i => $f): ?>
                        <?= $fila((string) $i, (string) ($f['nombre'] ?? ''), (string) ($f['valor'] ?? '')) ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <template id="esp-fila-modelo"><?= $fila('__n__', '', '') ?></template>

            <div class="rd-acciones mb-2">
                <button type="button" id="esp-agregar" class="btn btn-outline">+ Agregar fila</button>
                <button type="button" id="esp-pegar-abrir" class="btn btn-outline">Pegar varias filas</button>
            </div>
            <p class="rd-nota">
                Las filas de "marca y modelo a ofertar" y "URL del fabricante" las agrega siempre el formulario NET: no hace
                falta escribirlas. Una fila sin característica (solo valor) se imprime como texto suelto; sirve para
                subtítulos como "Administración".
            </p>

            <div id="esp-pegar" class="card mb-3 d-none">
                <div class="rd-card-pad">
                    <label class="form-label" for="esp-pegar-texto">Pegue una característica por línea, con el formato "Nombre: valor"</label>
                    <textarea id="esp-pegar-texto" class="form-control mb-2" rows="6" placeholder="Calibre del conductor: 23 AWG&#10;Color: Azul"></textarea>
                    <button type="button" id="esp-pegar-agregar" class="btn btn-primary">Agregar a la tabla</button>
                    <button type="button" id="esp-pegar-cerrar" class="btn btn-outline">Cancelar</button>
                </div>
            </div>

            <?= $form->field($version, 'garantia')->textarea(['rows' => 2, 'maxlength' => 500, 'placeholder' => (string) $garantiaPorDefecto])
                ->hint('Vacío = se usa la garantía por defecto de la configuración (la que se ve en gris).') ?>

            <?= $form->field($version, 'adjuntoRuta')->textInput(['maxlength' => 500])
                ->hint('Se completa solo si sube un archivo arriba. También puede pegar un enlace (ej. Google Drive, https://...) si el documento ya vive en otro lugar.') ?>

            <?php if (!$model->isNewRecord): ?>
                <?= $form->field($version, 'notaCambio')->textInput(['maxlength' => 300, 'placeholder' => 'Ej. Se exige cubierta LSZH'])
                    ->hint('Opcional: qué cambió. Se ve en el historial de versiones.') ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
