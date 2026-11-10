<?php

declare(strict_types=1);

use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\services\ProyectoService;
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
 *   materiales[n][...]       otros materiales que proveera la DTIC (_materiales-dtic.php)
 *   condiciones[n][...]      trabajos complementarios (_condiciones.php)
 *
 * Desde la revision del 2026-10-29 (paso 8) debajo de la lista van las dos
 * secciones sin precio que antes estaban en el presupuesto: "Otros materiales
 * y equipamiento que proveera la D.T.I.C." y los trabajos complementarios
 * (condiciones previas). Se guardan con la lista y solo salen en el impreso
 * si tienen filas.
 *
 * web/js/lista-items.js agrega y quita filas y filtra el subcatalogo. Cada
 * clasificacion es un <tbody> (data-categoria); las dos plantillas ocultas
 * (<template>) son la fila y el grupo "modelo" que el JS copia.
 *
 * Al guardar, elegir una especificacion EMITE su formulario de especificación. Cada item
 * muestra su formulario vigente, los avisos y "Reemitir", que abre un panel con su
 * propio formulario (fuera del de la lista: no se pueden anidar formularios).
 * Ese panel lo maneja web/js/especificacion-emision.js.
 *
 * "Ver detalle" (junto al selector de especificacion) abre una ventana con
 * buscador en la libreria y el detalle de cada ficha, para compararlas sin
 * salir de la lista. La maneja web/js/especificacion-vista-previa.js.
 *
 * @var yii\web\View $this
 * @var backend\models\Proyecto $proyecto
 * @var array $pasos barra de pasos del proyecto, ver ProyectoService::pasos()
 * @var array<int, array{nombre: string, items: backend\models\ProyectoItem[]}> $grupos Ver ListaItemsService::agrupar()
 * @var string[] $errores mensajes si el guardado fallo
 * @var array{version: string, borrador: bool, aprobacion: backend\models\ListaMaterialesAprobacion|null, fechaCreacion: string} $documento Ver ListaItemsService::documento()
 * @var bool $editable Ver ListaItemsService::puedeEditarse()
 * @var backend\models\SubcatalogoItem[] $subcatalogo items activos del subcatalogo
 * @var array{grupos: array<int, array{nombre: string, opciones: array<int, string>}>, urls: array<int, string>, subcatalogos: array<int, int>, sugeridasPorItem: array<int, int[]>} $especificaciones Ver EspecificacionService::selector()
 * @var array<string, array{opciones: array, grupos: array}> $opcionesPorItem Ver EspecificacionService::opcionesPorItem() (vacio si la lista no se edita)
 * @var backend\models\OrdenCambio[] $ordenes reaperturas de la lista (en pantalla ya no se llaman "orden de cambio", 2026-11-09)
 * @var array<int, array{emision: ?backend\models\EspecificacionEmision, avisos: string[]}> $emisiones Ver EspecificacionEmisionService::estadoPorItem()
 * @var bool $puedeReemitir Ver EspecificacionEmisionService::puedeReemitir()
 * @var int $totalFormularios formularios de especificación vigentes de la lista (boton "Imprimir especificaciones")
 * @var backend\models\ConfiguracionEspecificacion $configuracion
 * @var string|null $lugarEntregaPorDefecto texto de ejemplo del "Lugar de entrega" de cada fila y al reemitir (null = no hay)
 * @var string $lugarEntregaProyecto valor del campo "Lugar de entrega" de arriba de la lista (2026-11-10)
 * @var string|null $lugarEntregaConfiguracion el de la configuracion del formulario (si el de arriba queda vacio)
 * @var string $proximoCodigo codigo que recibira la proxima especificacion emitida (RD-PI-ET-001/26)
 * @var array<int, array<string, string>> $materiales otros materiales de la DTIC (los guardados o lo enviado)
 * @var string[] $categorias nombres de las categorias, subtitulos de los materiales escritos a mano
 * @var array<string, string> $unidades [abreviatura => "Metro (m)"]
 * @var array<int, array{idTipo: string, descripcion: string, unidadEncargada: string}> $condiciones trabajos complementarios (los guardados o lo enviado)
 * @var array<int, string> $tiposCondicion [id => nombre] tipos activos (y los que ya tiene el proyecto)
 * @var string[] $sugerenciasUnidadEncargada para el <datalist> de "Unidad encargada"
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
$this->params['breadcrumbs'][] = ['label' => $proyecto->identificador(), 'url' => ['/proyecto/view', 'id' => $proyecto->id]];
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
 * Formulario de especificación del item: codigo, estado, avisos y "Reemitir" (o "Emitir" si
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
        $html .= 'Formulario <strong class="mono">' . Html::encode($emision->codigoConVersion()) . '</strong> '
            . '<span class="' . $emision->estadoBadgeClass() . '">' . Html::encode($emision->estadoLabel()) . '</span> '
            . Html::encode('ficha ' . $emision->version->etiqueta() . ' · emitido el ' . $formato->asDate($emision->fechaEmision, 'php:d/m/Y'))
            . ' ' . Html::a('Ver formulario', ['/especificacion/formulario', 'id' => $emision->id], ['class' => 'btn btn-outline btn-sm', 'target' => '_blank']);
    }

    if ($puedeReemitir) {
        $html .= ' ' . Html::button($emision !== null ? 'Reemitir' : 'Emitir formulario de especificación', [
            'class' => 'btn btn-outline btn-sm esp-reemitir',
            'data' => [
                'item' => $item->id,
                'titulo' => ($item->especificacion?->nombre() ?? '') . ' — ' . ($item->subcatalogoItem->descripcion ?? ''),
                'net' => $emision?->codigoConVersion() ?? '',
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
$fila = static function (string $n, string $id, string $idSubcatalogo, string $nombre, string $unidad, string $cantidad, string $observacion, string $idEspecificacion = '', string $idCategoria = '', string $emision = '', string $lugar = '') use ($selectorEspecificacion, $lugarEntregaPorDefecto): string {
    return '<tr class="lista-item">'
        . '<td>'
        . Html::hiddenInput("items[$n][id]", $id)
        . Html::hiddenInput("items[$n][idSubcatalogo]", $idSubcatalogo, ['class' => 'lista-item-subcatalogo'])
        . '<div class="d-flex align-items-baseline gap-2">'
        . '<div class="lista-item-nombre">' . Html::encode($nombre) . '</div>'
        // "Cambiar ítem" (solo filas ya guardadas): otro item del subcatalogo en la MISMA fila,
        // para corregir un error sin anular su formulario de especificacion (lista-items.js).
        . ($id !== '' ? Html::button('Cambiar ítem', [
            'class' => 'btn btn-link btn-sm p-0 text-nowrap lista-item-cambiar',
            'title' => 'Elegir otro ítem del subcatálogo para esta fila (ej. el mismo cable en cajas en vez de metros). Su formulario de especificación se corrige con el mismo código.',
        ]) : '')
        . '</div>'
        . '<div class="rd-celda-sub text-warning-emphasis lista-item-cambio d-none"></div>'
        . Html::textInput("items[$n][observacion]", $observacion, ['class' => 'form-control lista-item-obs', 'maxlength' => 300, 'placeholder' => 'Observación (opcional): de 1 metro, 12 mm x 4 m...'])
        . $selectorEspecificacion($n, $idEspecificacion, $idCategoria, $idSubcatalogo)
        // Lugar de entrega de ESTA especificacion (revision del 2026-11-05: cada una puede tener uno
        // distinto). Solo con especificacion elegida (lista-items.js). Vacio = el del proyecto; cambiarlo
        // en un formulario ya emitido lo reemite al guardar (ListaItemsService).
        . '<div class="d-flex align-items-center gap-2 mt-1 lista-item-lugar' . ($idEspecificacion === '' ? ' d-none' : '') . '">'
        . '<label class="rd-celda-sub text-nowrap mb-0" for="lista-lugar-' . $n . '">Lugar de entrega</label>'
        . Html::textInput("items[$n][lugarEntrega]", $lugar, [
            'id' => 'lista-lugar-' . $n,
            'class' => 'form-control form-control-sm',
            'maxlength' => 300,
            'placeholder' => (string) ($lugarEntregaPorDefecto ?? 'Sin lugar de entrega'),
            'title' => 'Si se cambia en un formulario ya emitido, al guardar se reemite (mismo código, versión siguiente).',
        ])
        . '</div>'
        . $emision
        . '</td>'
        . '<td class="lista-item-unidad">' . Html::encode($unidad) . '</td>'
        . '<td class="rd-num">' . Html::textInput("items[$n][cantidad]", $cantidad, ['class' => 'qty', 'type' => 'number', 'step' => 'any', 'min' => '0', 'required' => true, 'aria-label' => 'Cantidad']) . '</td>'
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
            <?php // El documento impreso lleva su codigo (RD-PI-LM-NNN) y su version; no se aprueba (2026-11-05). ?>
            <div class="rd-nota mt-1">
                Lista de materiales <?= Html::encode($proyecto->codigoLista ?: '') ?> versión <?= Html::encode($documento['version']) ?>:
                <?php if ($documento['borrador']): ?>
                    borrador; se emite al enviar a cotización.
                <?php elseif ($documento['aprobacion']->fechaAprobacion !== null): ?>
                    enviada a cotización el <?= Html::encode($documento['aprobacion']->fechaAprobacionFormateada()) ?><?= $documento['aprobacion']->usuario ? ' por ' . Html::encode($documento['aprobacion']->usuario) : '' ?>.
                <?php else: ?>
                    enviada a cotización (antes de que el sistema registrara la fecha).
                <?php endif; ?>
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('Imprimir lista de materiales', ['imprimir', 'id' => $proyecto->id], ['class' => 'btn btn-outline', 'target' => '_blank', 'id' => 'lista-imprimir']) ?>
            <?php if ($totalFormularios > 0): ?>
                <?= Html::a('Imprimir especificaciones (' . $totalFormularios . ')', ['imprimir-especificaciones', 'id' => $proyecto->id], [
                    'class' => 'btn btn-outline',
                    'target' => '_blank',
                    'id' => 'lista-imprimir-esp',
                    'title' => 'Todos los formularios de especificación vigentes de la lista en un PDF, cada uno en una hoja nueva',
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
                    'data' => ['mensaje' => 'Al enviar a cotización la lista queda bloqueada: para modificarla después habrá que reabrirla, explicando el motivo. ¿Continuar?'],
                ]) ?>
                <?= Html::submitButton('Guardar lista', ['class' => 'btn btn-primary', 'name' => 'accion', 'value' => 'guardar']) ?>
            <?php endif; ?>
        </div>
    </div>

    <?= $this->render('/proyecto/_pasos', ['pasos' => $pasos, 'actual' => ProyectoService::PASO_LISTA]) ?>

    <div class="notice mb-3">
        Sin precios todavía — se completan al registrar cotizaciones.
        <span class="d-none lista-sin-guardar" id="lista-sin-guardar"><strong>Hay cambios sin guardar.</strong></span>
    </div>

    <?php if ($errores !== []): ?>
        <div class="alert alert-danger">
            <?php foreach ($errores as $error): ?>
                <div><?= Html::encode($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($editable): ?>
        <p class="rd-nota">
            Al guardar, elegir una especificación técnica para un ítem emite su formulario
            (el próximo será el <?= Html::encode($proximoCodigo) ?>). Cambiarla o quitarla anula el anterior.
        </p>
    <?php endif; ?>

    <?php // Lugar de entrega por defecto (2026-11-10: aqui y no al crear el proyecto). Cada fila puede poner otro. ?>
    <div class="card rd-card-pad mb-3">
        <?php if ($editable): ?>
            <label for="lista-lugar-entrega"><strong>Lugar de entrega</strong> <span class="rd-nota">(para las especificaciones que no digan otro)</span></label>
            <?= Html::textInput('lugarEntregaProyecto', $lugarEntregaProyecto, [
                'id' => 'lista-lugar-entrega',
                'class' => 'form-control',
                'maxlength' => 300,
                'placeholder' => (string) ($lugarEntregaConfiguracion ?? 'Ej. Administración de la Facultad de Ciencias Agrarias'),
                'data-configuracion' => (string) ($lugarEntregaConfiguracion ?? ''),
            ]) ?>
            <div class="rd-nota mt-1">
                Si los materiales se entregan en lugares distintos, escriba el de cada especificación en su fila, debajo
                del selector. Cambiar este lugar no cambia los formularios ya emitidos.
                <?php if ($proyecto->lugarEntrega === null && $lugarEntregaProyecto !== ''): ?>
                    Sugerido por la unidad / dependencia: se guarda al guardar la lista.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <strong>Lugar de entrega:</strong> <?= Html::encode($proyecto->lugarEntrega ?: ($lugarEntregaConfiguracion ?? '—')) ?>
            <span class="rd-nota">(el de las especificaciones que no dicen otro)</span>
        <?php endif; ?>
    </div>
    <div class="alert alert-danger d-none" id="lista-error-cliente">Revise las cantidades marcadas: en la lista, números mayores o iguales a 0; en los materiales de la DTIC, mayores que 0.</div>

    <div class="rd-cols">

        <?php if ($editable): ?>
            <div class="card rd-col-1">
                <div class="rd-card-pad">
                    <div class="rd-card-titulo mb-2">Subcatálogo de redes</div>
                    <input type="text" class="form-control" id="lista-buscar" placeholder="Buscar ítem...">
                    <div class="kicker mt-2"><?= count($subcatalogo) ?> ítems activos en el subcatálogo</div>
                    <?php // Aviso de "Cambiar ítem": mientras se ve, el "+" reemplaza el item de esa fila en vez de agregar uno. ?>
                    <div class="notice mt-2 mb-0 d-none" id="lista-cambiando">
                        Elija con <strong>+</strong> el ítem que reemplaza a <strong id="lista-cambiando-nombre"></strong>.
                        <button type="button" class="btn btn-link btn-sm p-0" id="lista-cambiando-cancelar">Cancelar</button>
                    </div>
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
                            <?php
                            // Linea SIGMA: a que item generico de SIGMA apunta y su partida, para
                            // elegir sin ir al subcatalogo. Solo se muestra: el buscador sigue
                            // buscando por el nombre DTIC. Nombre largo = recortado con "…" (CSS),
                            // completo en el title.
                            $sigma = $itemSubcatalogo->catalogoSigma;
                            $lineaSigma = $sigma !== null ? 'SIGMA: ' . $sigma->Descripcion : 'Sin ítem SIGMA';
                            if ($itemSubcatalogo->partida) {
                                $lineaSigma .= ' · Partida ' . $itemSubcatalogo->partida;
                            }
                            ?>
                            <div class="rd-row-item-texto">
                                <div class="rd-celda-titulo"><?= Html::encode($itemSubcatalogo->descripcion) ?></div>
                                <div class="rd-celda-sub">
                                    <?= Html::encode($itemSubcatalogo->categoria->nombre ?? '') ?>
                                    <?= $itemSubcatalogo->unidad ? ' · Unidad: ' . Html::encode($itemSubcatalogo->unidad->abreviatura) : '' ?>
                                </div>
                                <div class="rd-celda-sub rd-linea-sigma" title="<?= Html::encode($lineaSigma) ?>"><?= Html::encode($lineaSigma) ?></div>
                            </div>
                            <button type="button" class="addbtn" aria-label="Agregar a la lista">+</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card rd-col-2">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Ítems del proyecto — <?= Html::encode($proyecto->identificador()) ?></div>
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
                                $infoEmision($item),
                                // Lo enviado (si el guardado fallo) o el del formulario vigente.
                                (string) ($item->lugarEntregaPedido ?? ($emisiones[$item->id]['emision'] ?? null)->lugarEntrega ?? '')
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

    <?php // Secciones sin precio (2026-10-29, antes en el presupuesto): dentro del mismo formulario, se guardan con la lista. ?>
    <?= $this->render('_materiales-dtic', [
        'materiales' => $materiales,
        'categorias' => $categorias,
        'unidades' => $unidades,
        'editable' => $editable,
    ]) ?>

    <?= $this->render('_condiciones', [
        'condiciones' => $condiciones,
        'tipos' => $tiposCondicion,
        'sugerencias' => $sugerenciasUnidadEncargada,
        'editable' => $editable,
    ]) ?>

    <?php if ($editable): ?>
        <?php // Segundo boton, junto a las secciones: el de arriba queda lejos. Guarda todo el formulario. ?>
        <div class="d-flex justify-content-end align-items-center gap-3 mt-3">
            <span class="rd-nota">Estas secciones se guardan con la lista.</span>
            <span class="d-none lista-sin-guardar text-warning-emphasis">● Cambios sin guardar</span>
            <?= Html::submitButton('Guardar lista', ['class' => 'btn btn-primary', 'name' => 'accion', 'value' => 'guardar']) ?>
        </div>
    <?php endif; ?>

    <?= $editable ? Html::endForm() : '' ?>

    <?php if ($puedeReemitir): ?>
        <div class="card rd-card-pad mt-3 d-none" id="reemitir-panel">
            <?= Html::beginForm(['reemitir', 'id' => $proyecto->id], 'post', ['id' => 'reemitir-form']) ?>
                <div class="rd-card-titulo mb-1" id="reemitir-titulo">Reemitir formulario de especificación</div>
                <p class="rd-nota">
                    Se emite la versión siguiente del formulario, con el mismo código, la última versión de la ficha y la
                    cantidad actual de la lista. La versión anterior queda "reemplazada" y se conserva en el historial. Si la cantidad está mal, corríjala
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
                        <?= Html::textInput('lugarEntrega', '', ['id' => 'reemitir-lugar', 'class' => 'form-control', 'maxlength' => 300, 'placeholder' => (string) $lugarEntregaPorDefecto]) ?>
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
                La lista está bloqueada para que lo cotizado coincida con lo pedido. Para corregirla, reábrala con el
                motivo: vuelve a edición hasta que la envíe otra vez a cotización (y se emite la versión siguiente).
            </p>
            <?= Html::beginForm(['solicitar-cambio', 'id' => $proyecto->id], 'post', ['id' => 'orden-cambio-form']) ?>
                <div class="form-group">
                    <label for="orden-motivo">Motivo para reabrir la lista</label>
                    <?= Html::textarea('OrdenCambio[motivo]', '', ['id' => 'orden-motivo', 'class' => 'form-control', 'rows' => 2, 'maxlength' => 500, 'required' => true]) ?>
                </div>
                <?= Html::submitButton('Reabrir lista', ['class' => 'btn btn-primary']) ?>
            <?= Html::endForm() ?>
        </div>
    <?php else: ?>
        <div class="card rd-card-pad mt-3">
            <p class="rd-nota mb-0">
                Esta lista ya no se modifica. Si piden algo más, desde la vista del proyecto se registra una orden de
                cambio (presupuesto adicional de esta fase aprobada) o se agrega una fase, cada una con su propia lista.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($ordenes !== []): ?>
        <div class="card mt-3">
            <div class="rd-card-head">
                <div class="rd-card-titulo">Reaperturas de la lista</div>
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
