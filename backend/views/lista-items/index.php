<?php

declare(strict_types=1);

use backend\models\Proyecto;
use backend\models\ProyectoItem;
use yii\bootstrap5\BootstrapPluginAsset;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JqueryAsset;

/**
 * Lista de items del proyecto: items y cantidades, SIN precios.
 *
 * Mientras la lista es editable todo es un solo formulario POST:
 *   items[n][id]             id del item ya guardado ('' si es nuevo)
 *   items[n][idSubcatalogo]  item del subcatalogo
 *   items[n][cantidad]
 *   items[n][observacion]
 *   items[n][idEspecificacion] especificacion de la libreria ('' = ninguna)
 *
 * web/js/lista-items.js agrega y quita filas y filtra el subcatalogo. Cada
 * clasificacion es un <tbody> (data-categoria); las dos plantillas ocultas
 * (<template>) son la fila y el grupo "modelo" que el JS copia.
 *
 * Al guardar, elegir una especificacion EMITE su formulario NET. Cada item
 * muestra su NET vigente, los avisos y "Reemitir", que abre un panel con su
 * propio formulario (fuera del de la lista: no se pueden anidar formularios).
 * Ese panel lo maneja web/js/especificacion-emision.js.
 *
 * "Ver detalle" (junto al selector de especificacion) abre una ventana con
 * buscador en la libreria y el detalle de cada ficha, para compararlas sin
 * salir de la lista. La maneja web/js/especificacion-vista-previa.js.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * @var array<int, array{nombre: string, items: backend\models\ProyectoItem[]}> $grupos Ver ListaItemsService::agrupar()
 * @var string[] $errores mensajes si el guardado fallo
 * @var bool $editable Ver ListaItemsService::puedeEditarse()
 * @var backend\models\SubcatalogoItem[] $subcatalogo items activos del subcatalogo
 * @var array{grupos: array<int, array{nombre: string, opciones: array<int, string>}>, urls: array<int, string>, subcatalogos: array<int, int>, sugeridasPorItem: array<int, int[]>} $especificaciones Ver EspecificacionService::selector()
 * @var array<string, array{opciones: array, grupos: array}> $opcionesPorItem Ver EspecificacionService::opcionesPorItem() (vacio si la lista no se edita)
 * @var backend\models\OrdenCambio[] $ordenes ordenes de cambio del proyecto
 * @var array<int, array{emision: ?backend\models\EspecificacionEmision, avisos: string[]}> $emisiones Ver EspecificacionEmisionService::estadoPorItem()
 * @var bool $puedeReemitir Ver EspecificacionEmisionService::puedeReemitir()
 * @var int $totalFormularios formularios NET vigentes de la lista (boton "Imprimir especificaciones")
 * @var backend\models\ConfiguracionEspecificacion $configuracion
 */

if ($editable) {
    $this->registerJsFile('@web/js/lista-items.js', ['depends' => [JqueryAsset::class]]);
    // La ventana "Ver detalle" usa el modal de Bootstrap, que necesita su JS
    // (el layout solo carga el CSS). Se registra solo en esta pantalla.
    BootstrapPluginAsset::register($this);
    $this->registerJsFile('@web/js/especificacion-vista-previa.js', ['depends' => [JqueryAsset::class, BootstrapPluginAsset::class]]);
}
$this->registerJsFile('@web/js/especificacion-emision.js', ['depends' => [JqueryAsset::class]]);

$this->title = 'Lista de ítems del proyecto';
$this->params['breadcrumbs'][] = ['label' => 'Proyectos', 'url' => ['/proyecto/index']];
$this->params['breadcrumbs'][] = ['label' => $proyecto->codigo ?: '#' . $proyecto->id, 'url' => ['/proyecto/view', 'id' => $proyecto->id]];
$this->params['breadcrumbs'][] = 'Lista de ítems';

$formato = Yii::$app->formatter;
$totalItems = array_sum(array_map(static fn ($grupo) => count($grupo['items']), $grupos));
$conEspecificacion = 0;
foreach ($grupos as $grupo) {
    foreach ($grupo['items'] as $item) {
        $conEspecificacion += $item->idEspecificacion ? 1 : 0;
    }
}
$n = 0;

// Cada opcion lleva el enlace a su documento (data-url) y si la ficha ya
// tiene item de referencia (data-con-referencia): el JS los usa para el
// enlace "ver documento" y para ofrecer "Recordar como sugerida" solo en
// las que no lo tienen, sin volver a consultar al servidor.
$atributosOpciones = [];
foreach ($especificaciones['urls'] as $idEspecificacion => $url) {
    $atributosOpciones[$idEspecificacion]['data-url'] = $url;
}
foreach (array_keys($especificaciones['subcatalogos']) as $idEspecificacion) {
    $atributosOpciones[$idEspecificacion]['data-con-referencia'] = 1;
}

/**
 * Formulario NET del item: codigo, estado, avisos y "Reemitir" (o "Emitir" si
 * la ficha se enlazo antes de que existieran las emisiones). Vacio si el item
 * no tiene especificacion o todavia no se guardo.
 */
$infoEmision = static function (ProyectoItem $item) use ($emisiones, $puedeReemitir, $formato): string {
    $estado = $emisiones[$item->id] ?? null;

    if ($estado === null) {
        return '';
    }

    $emision = $estado['emision'];
    $html = '<div class="rd-celda-sub mt-1 lista-item-net">';

    if ($emision !== null) {
        $html .= 'Formulario <strong class="mono">' . Html::encode($emision->codigoNet()) . '</strong> '
            . '<span class="' . $emision->estadoBadgeClass() . '">' . Html::encode($emision->estadoLabel()) . '</span> '
            . Html::encode($emision->version->etiqueta() . ' · emitido el ' . $formato->asDate($emision->fechaEmision, 'php:d/m/Y'))
            . ' ' . Html::a('Ver formulario', ['/especificacion/formulario', 'id' => $emision->id], ['class' => 'btn btn-outline btn-sm', 'target' => '_blank']);
    }

    if ($puedeReemitir) {
        $html .= ' ' . Html::button($emision !== null ? 'Reemitir' : 'Emitir formulario NET', [
            'class' => 'btn btn-outline btn-sm esp-reemitir',
            'data' => [
                'item' => $item->id,
                'titulo' => ($item->especificacion?->nombre() ?? '') . ' — ' . ($item->subcatalogoItem->descripcion ?? ''),
                'net' => $emision?->codigoNet() ?? '',
                'lugar' => $emision?->lugarEntrega ?? '',
                'validez' => $emision?->validezOfertaDias ?? '',
                'tiempo' => $emision?->tiempoEntregaDias ?? '',
            ],
        ]);
    }

    foreach ($estado['avisos'] as $aviso) {
        $html .= '<div class="text-warning-emphasis">⚠ ' . Html::encode($aviso) . '</div>';
    }

    return $html . '</div>';
};

/**
 * Selector "Especificación técnica" de una fila. Las opciones llegan YA
 * ordenadas (sugeridas, categoria del item, resto) en $opcionesPorItem, con
 * clave "idCategoria|idSubcatalogo"; la regla vive en
 * EspecificacionService::opcionesPorItem(). La fila modelo usa la clave ''.
 */
$selectorEspecificacion = static function (string $n, string $seleccionada, string $idCategoria, string $idSubcatalogo) use ($especificaciones, $opcionesPorItem, $atributosOpciones): string {
    $opcionesFila = $opcionesPorItem[$idCategoria . '|' . $idSubcatalogo] ?? $opcionesPorItem[''];
    $opciones = $opcionesFila['opciones'];
    $atributosGrupos = $opcionesFila['grupos'];

    $url = $especificaciones['urls'][(int) $seleccionada] ?? '';

    return '<div class="d-flex align-items-center gap-2 mt-1">'
        . Html::dropDownList("items[$n][idEspecificacion]", $seleccionada, $opciones, [
            'class' => 'form-control lista-item-esp',
            'prompt' => '— Sin especificación técnica —',
            'aria-label' => 'Especificación técnica (opcional)',
            'options' => $atributosOpciones,
            'groups' => $atributosGrupos,
        ])
        . Html::button('Ver detalle', [
            'class' => 'btn btn-outline btn-sm text-nowrap lista-item-ver-ficha',
            'title' => 'Buscar en la librería y ver las características de cada especificación',
        ])
        . Html::a('Ver documento', $url ?: '#', [
            'class' => 'lista-item-doc text-nowrap' . ($url === '' ? ' d-none' : ''),
            'target' => '_blank',
            'rel' => 'noopener',
        ])
        . '</div>'
        // "Recordar como sugerida para este ítem": lo marca la ventana "Ver
        // detalle" y se aplica al guardar (ListaItemsService::guardar()).
        . Html::hiddenInput("items[$n][recordarReferencia]", '', ['class' => 'lista-item-recordar'])
        . '<div class="rd-celda-sub lista-item-recordar-nota d-none">Al guardar, esta especificación quedará como sugerida para este ítem.</div>';
};

/**
 * Fila editable de un item. $n es su indice en items[n][...]; el JS usa
 * "__n__" como marcador y lo reemplaza por un numero nuevo.
 */
$fila = static function (string $n, string $id, string $idSubcatalogo, string $nombre, string $unidad, string $cantidad, string $observacion, string $idEspecificacion = '', string $idCategoria = '', string $emision = '') use ($selectorEspecificacion): string {
    return '<tr class="lista-item">'
        . '<td>'
        . Html::hiddenInput("items[$n][id]", $id)
        . Html::hiddenInput("items[$n][idSubcatalogo]", $idSubcatalogo, ['class' => 'lista-item-subcatalogo'])
        . '<div class="lista-item-nombre">' . Html::encode($nombre) . '</div>'
        . Html::textInput("items[$n][observacion]", $observacion, ['class' => 'form-control lista-item-obs', 'maxlength' => 300, 'placeholder' => 'Observación (opcional): de 1 metro, 12 mm x 4 m...'])
        . $selectorEspecificacion($n, $idEspecificacion, $idCategoria, $idSubcatalogo)
        . $emision
        . '</td>'
        . '<td class="lista-item-unidad">' . Html::encode($unidad) . '</td>'
        . '<td class="rd-num">' . Html::textInput("items[$n][cantidad]", $cantidad, ['class' => 'qty', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => true, 'aria-label' => 'Cantidad']) . '</td>'
        . '<td class="rd-num"><button type="button" class="rd-quitar" aria-label="Quitar ítem">×</button></td>'
        . '</tr>';
};

/**
 * Cabecera y pie de un grupo (clasificacion). El JS reemplaza "__cat__" y
 * "__nombre__" al crear un grupo nuevo.
 */
$grupoAbre = static fn (string $idCategoria, string $nombre): string =>
    '<tbody class="lista-grupo" data-categoria="' . Html::encode($idCategoria) . '">'
    . '<tr class="cat-row"><td colspan="4">' . Html::encode($nombre) . '</td></tr>';
$grupoCierra = static fn (int $cantidad): string =>
    '<tr class="subtotal-row"><td colspan="4">Subtotal: <span class="lista-subtotal">' . $cantidad . '</span> ítem(s)</td></tr></tbody>';
?>
<div class="lista-items">

    <?= $editable ? Html::beginForm(['index', 'id' => $proyecto->id], 'post', ['id' => 'lista-items-form']) : '' ?>

    <div class="rd-pagehead">
        <div>
            <h1>
                <?= Html::encode($this->title) ?>
                <span class="<?= $proyecto->estadoListaBadgeClass() ?>"><?= Html::encode($proyecto->estadoListaLabel()) ?></span>
            </h1>
            <div class="rd-pagehead-sub"><?= Html::encode($proyecto->etiqueta()) ?></div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('Imprimir lista', ['imprimir', 'id' => $proyecto->id], ['class' => 'btn btn-outline', 'target' => '_blank', 'id' => 'lista-imprimir']) ?>
            <?php if ($totalFormularios > 0): ?>
                <?= Html::a('Imprimir especificaciones (' . $totalFormularios . ')', ['imprimir-especificaciones', 'id' => $proyecto->id], [
                    'class' => 'btn btn-outline',
                    'target' => '_blank',
                    'id' => 'lista-imprimir-esp',
                    'title' => 'Todos los formularios NET vigentes de la lista en un PDF, cada uno en una hoja nueva',
                ]) ?>
            <?php endif; ?>
            <?php if ($proyecto->estadoLista === Proyecto::LISTA_EN_COTIZACION): ?>
                <?= Html::a('Solicitudes de cotización →', ['/solicitud-cotizacion/index', 'id' => $proyecto->id], ['class' => 'btn btn-primary']) ?>
            <?php endif; ?>
            <?php if ($editable): ?>
                <?= Html::submitButton('Guardar y enviar a cotización', [
                    'class' => 'btn btn-outline',
                    'name' => 'accion',
                    'value' => 'enviar',
                    'id' => 'lista-enviar',
                    // La confirmacion la pide lista-items.js (no data-confirm de Yii, que
                    // envia el formulario por su cuenta y pierde el name/value del boton).
                    'data' => ['mensaje' => 'Al enviar a cotización la lista queda bloqueada: para modificarla después hará falta una orden de cambio. ¿Continuar?'],
                ]) ?>
                <?= Html::submitButton('Guardar lista', ['class' => 'btn btn-primary', 'name' => 'accion', 'value' => 'guardar']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="notice mb-3">
        Sin precios todavía — se completan al registrar cotizaciones.
        <span class="d-none" id="lista-sin-guardar"><strong>Hay cambios sin guardar.</strong></span>
    </div>

    <?php if ($errores !== []): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $error): ?>
                <div><?= Html::encode($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($editable && $configuracion->siguienteNet === null): ?>
        <div class="alert alert-warning">
            Todavía no se fijó el código NET inicial: hasta entonces no se pueden enlazar especificaciones técnicas
            (enlazar una emite su formulario NET).
            <?= Html::a('Fijarlo en la configuración del formulario NET', ['/especificacion/configuracion']) ?>.
        </div>
    <?php elseif ($editable): ?>
        <p class="rd-nota">
            Al guardar, elegir una especificación técnica para un ítem emite su formulario NET
            (el próximo será el NET:<?= sprintf('%03d', $configuracion->siguienteNet) ?>/<?= date('y') ?>). Cambiarla o quitarla anula el anterior.
        </p>
    <?php endif; ?>
    <div class="alert alert-danger d-none" id="lista-error-cliente">Revise las cantidades marcadas: deben ser números mayores o iguales a 0.</div>

    <div class="rd-cols">

        <?php if ($editable): ?>
            <div class="card rd-col-1">
                <div class="rd-card-pad">
                    <div class="rd-card-titulo mb-2">Subcatálogo de redes</div>
                    <input type="text" class="form-control" id="lista-buscar" placeholder="Buscar ítem...">
                    <div class="kicker mt-2"><?= count($subcatalogo) ?> ítems activos en el subcatálogo</div>
                </div>
                <div class="rd-lista-scroll" id="lista-subcatalogo">
                    <?php if ($subcatalogo === []): ?>
                        <div class="rd-vacio">
                            No hay ítems activos en el subcatálogo. Agréguelos en
                            <?= Html::a('Subcatálogo', ['/subcatalogo-item/index']) ?>.
                        </div>
                    <?php endif; ?>
                    <?php foreach ($subcatalogo as $itemSubcatalogo): ?>
                        <div class="row-item"
                             data-id="<?= $itemSubcatalogo->id ?>"
                             data-nombre="<?= Html::encode($itemSubcatalogo->descripcion) ?>"
                             data-unidad="<?= Html::encode((string) $itemSubcatalogo->abreviaturaUnidad()) ?>"
                             data-categoria="<?= $itemSubcatalogo->idCategoria ?>"
                             data-categoria-nombre="<?= Html::encode($itemSubcatalogo->categoria->nombre ?? 'Sin clasificación') ?>">
                            <div>
                                <div class="rd-celda-titulo"><?= Html::encode($itemSubcatalogo->descripcion) ?></div>
                                <div class="rd-celda-sub">
                                    <?= Html::encode($itemSubcatalogo->categoria->nombre ?? '') ?>
                                    <?= $itemSubcatalogo->unidad ? ' · Unidad: ' . Html::encode($itemSubcatalogo->unidad->abreviatura) : '' ?>
                                </div>
                            </div>
                            <button type="button" class="addbtn" aria-label="Agregar a la lista">+</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Ítems del proyecto — <?= Html::encode($proyecto->codigo ?: '#' . $proyecto->id) ?></div>
                <div class="kicker">
                    <span id="lista-total"><?= $totalItems ?></span> ítems en
                    <span id="lista-total-grupos"><?= count($grupos) ?></span> clasificaciones ·
                    <span id="lista-total-esp"><?= $conEspecificacion ?></span> con especificación técnica
                </div>
            </div>

            <table class="table" id="lista-tabla">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Unidad</th>
                        <th class="rd-num">Cantidad</th>
                        <th></th>
                    </tr>
                </thead>
                <?php foreach ($grupos as $idCategoria => $grupo): ?>
                    <?php if ($editable): ?>
                        <?= $grupoAbre((string) $idCategoria, $grupo['nombre']) ?>
                        <?php foreach ($grupo['items'] as $item): ?>
                            <?= $fila(
                                (string) $n++,
                                (string) $item->id,
                                (string) $item->idSubcatalogo,
                                $item->subcatalogoItem->descripcion ?? 'Ítem no disponible',
                                (string) $item->unidadMedida,
                                // Sin ceros de mas: 25.00 se muestra 25; lo enviado con error se muestra tal cual.
                                is_numeric($item->cantidad) ? (string) (float) $item->cantidad : (string) $item->cantidad,
                                (string) $item->observacion,
                                (string) $item->idEspecificacion,
                                (string) $item->idCategoria,
                                $infoEmision($item)
                            ) ?>
                        <?php endforeach; ?>
                        <?= $grupoCierra(count($grupo['items'])) ?>
                    <?php else: ?>
                        <tbody>
                            <tr class="cat-row"><td colspan="4"><?= Html::encode($grupo['nombre']) ?></td></tr>
                            <?php foreach ($grupo['items'] as $item): ?>
                                <tr>
                                    <td>
                                        <?= Html::encode($item->subcatalogoItem->descripcion ?? 'Ítem no disponible') ?>
                                        <?php if (!empty($item->observacion)): ?>
                                            <div class="rd-celda-sub"><?= Html::encode($item->observacion) ?></div>
                                        <?php endif; ?>
                                        <?php if ($item->especificacion !== null): ?>
                                            <?php $url = $item->especificacion->urlDocumento(); ?>
                                            <div class="rd-celda-sub">
                                                Especificación técnica:
                                                <?= $url === null
                                                    ? Html::encode($item->especificacion->nombre())
                                                    : Html::a(Html::encode($item->especificacion->nombre()), $url, ['target' => '_blank', 'rel' => 'noopener']) ?>
                                            </div>
                                            <?= $infoEmision($item) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Html::encode((string) $item->unidadMedida) ?></td>
                                    <td class="rd-num mono"><?= $formato->asDecimal($item->cantidad, 2) ?></td>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="subtotal-row"><td colspan="4">Subtotal: <?= count($grupo['items']) ?> ítem(s)</td></tr>
                        </tbody>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>

            <div class="rd-vacio<?= $totalItems === 0 ? '' : ' d-none' ?>" id="lista-vacia">
                <?= $editable
                    ? 'La lista está vacía. Agregue ítems con el botón + del subcatálogo, o ' . Html::a('cargue una plantilla', ['/proyecto/plantilla', 'id' => $proyecto->id]) . '.'
                    : 'La lista no tiene ítems.' ?>
            </div>
        </div>

    </div>

    <?= $editable ? Html::endForm() : '' ?>

    <?php if ($puedeReemitir): ?>
        <div class="card rd-card-pad mt-3 d-none" id="reemitir-panel">
            <?= Html::beginForm(['reemitir', 'id' => $proyecto->id], 'post', ['id' => 'reemitir-form']) ?>
                <div class="rd-card-titulo mb-1" id="reemitir-titulo">Reemitir formulario NET</div>
                <p class="rd-nota">
                    Se emite un formulario con NET nuevo, la última versión de la ficha y la cantidad actual de la lista.
                    El anterior queda "reemplazado" y se conserva en el historial. Si la cantidad está mal, corríjala
                    primero en la lista.
                </p>
                <?= Html::hiddenInput('idProyectoItem', '', ['id' => 'reemitir-item']) ?>
                <div class="form-group mb-2" id="reemitir-motivo-grupo">
                    <label for="reemitir-motivo">Motivo</label>
                    <?= Html::textInput('motivo', '', ['id' => 'reemitir-motivo', 'class' => 'form-control', 'maxlength' => 300, 'placeholder' => 'Ej. facultad equivocada, venció, cambió la ficha']) ?>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group mb-2">
                        <label for="reemitir-lugar">Lugar de entrega</label>
                        <?= Html::textInput('lugarEntrega', '', ['id' => 'reemitir-lugar', 'class' => 'form-control', 'maxlength' => 300, 'placeholder' => (string) $proyecto->facultad]) ?>
                    </div>
                    <div class="col-md-3 form-group mb-2">
                        <label for="reemitir-validez">Validez de la oferta (días)</label>
                        <?= Html::textInput('validezOfertaDias', '', ['id' => 'reemitir-validez', 'class' => 'form-control', 'type' => 'number', 'min' => 1, 'placeholder' => (string) $configuracion->validezOfertaDias]) ?>
                    </div>
                    <div class="col-md-3 form-group mb-2">
                        <label for="reemitir-tiempo">Tiempo de entrega (días)</label>
                        <?= Html::textInput('tiempoEntregaDias', '', ['id' => 'reemitir-tiempo', 'class' => 'form-control', 'type' => 'number', 'min' => 1, 'placeholder' => (string) $configuracion->tiempoEntregaDias]) ?>
                    </div>
                </div>
                <?= Html::submitButton('Emitir', ['class' => 'btn btn-primary']) ?>
                <button type="button" class="btn btn-outline" id="reemitir-cancelar">Cancelar</button>
            <?= Html::endForm() ?>
        </div>
    <?php endif; ?>

    <?php if ($editable): ?>
        <?php // Modelos que el JS copia; el navegador no los muestra ni los envia. ?>
        <template id="lista-fila-modelo"><?= $fila('__n__', '', '', '', '', '', '') ?></template>
        <template id="lista-grupo-modelo"><?= $grupoAbre('__cat__', '__nombre__') . $grupoCierra(0) ?></template>
        <span class="d-none" id="lista-siguiente" data-siguiente="<?= $n ?>"></span>
        <?php // Fichas sugeridas para cada item del subcatalogo (EspecificacionService::selector()): las usa el JS en las filas nuevas y en "Ver detalle". Yii escribe el arreglo como JSON. ?>
        <?= Html::tag('span', '', ['class' => 'd-none', 'id' => 'lista-sugeridas', 'data' => ['mapa' => $especificaciones['sugeridasPorItem']]]) ?>

        <?php // Ventana "Ver detalle" (modal de Bootstrap). El contenido de la ficha lo trae el JS por AJAX. ?>
        <div class="modal fade" id="esp-previa" tabindex="-1" aria-labelledby="esp-previa-titulo" aria-hidden="true"
             data-url="<?= Html::encode(Url::to(['/especificacion/vista-previa'])) ?>"
             data-url-buscar="<?= Html::encode(Url::to(['/especificacion/buscar'])) ?>">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="esp-previa-titulo">Especificación técnica</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="search" class="form-control mb-2" id="esp-previa-buscar" autocomplete="off"
                               placeholder="Buscar en la librería por título, modelo o característica (ej. 24 poe cisco)"
                               aria-label="Buscar especificación técnica">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="kicker mb-1" id="esp-previa-resumen"></div>
                                <div class="list-group esp-previa-lista" id="esp-previa-lista" role="listbox" aria-label="Especificaciones"></div>
                            </div>
                            <div class="col-md-7">
                                <div class="esp-previa-detalle" id="esp-previa-contenido"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="form-check me-auto d-none" id="esp-previa-recordar-grupo">
                            <input type="checkbox" class="form-check-input" id="esp-previa-recordar">
                            <label class="form-check-label" for="esp-previa-recordar">Recordar como sugerida para este ítem</label>
                        </div>
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" id="esp-previa-usar">Usar esta especificación</button>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($proyecto->estadoLista === Proyecto::LISTA_EN_COTIZACION): ?>
        <div class="card rd-card-pad mt-3">
            <div class="rd-card-titulo mb-2">Lista enviada a cotización</div>
            <p class="rd-nota">
                La lista está bloqueada para que lo cotizado coincida con lo pedido. Para modificarla registre una
                orden de cambio: la lista vuelve a edición y la orden queda abierta hasta que la envíe otra vez a cotización.
            </p>
            <?= Html::beginForm(['solicitar-cambio', 'id' => $proyecto->id], 'post', ['id' => 'orden-cambio-form']) ?>
                <div class="form-group">
                    <label for="orden-motivo">Motivo del cambio</label>
                    <?= Html::textarea('OrdenCambio[motivo]', '', ['id' => 'orden-motivo', 'class' => 'form-control', 'rows' => 2, 'maxlength' => 500, 'required' => true]) ?>
                </div>
                <?= Html::submitButton('Solicitar cambio', ['class' => 'btn btn-primary']) ?>
            <?= Html::endForm() ?>
        </div>
    <?php else: ?>
        <div class="card rd-card-pad mt-3">
            <p class="rd-nota mb-0">
                Esta lista ya no se modifica. Una ampliación se registra como un proyecto nuevo de fase siguiente,
                con su propia lista.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($ordenes !== []): ?>
        <div class="card mt-3">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Órdenes de cambio</div>
                <div class="kicker"><?= count($ordenes) ?> registradas</div>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Solicitado por</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordenes as $orden): ?>
                        <tr>
                            <td class="mono"><?= $formato->asDate($orden->fecha, 'php:d/m/Y') ?></td>
                            <td><?= Html::encode($orden->solicitadoPor) ?></td>
                            <td><?= Html::encode($orden->motivo) ?></td>
                            <td>
                                <span class="badge <?= $orden->estaAbierta() ? 'b-proceso' : 'b-aprobado' ?>">
                                    <?= $orden->estaAbierta() ? 'Abierta' : 'Cerrada' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>
