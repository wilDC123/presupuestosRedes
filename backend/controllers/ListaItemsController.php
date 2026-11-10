<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\components\UsuarioActual;
use backend\models\ConfiguracionEspecificacion;
use backend\models\ConfiguracionPresupuesto;
use backend\models\CorrelativoDocumento;
use backend\models\OrdenCambio;
use backend\models\Proceso;
use backend\models\Proyecto;
use backend\services\CategoriaService;
use backend\services\CodificacionService;
use backend\services\CondicionPreviaService;
use backend\services\EspecificacionEmisionService;
use backend\services\EspecificacionService;
use backend\services\ListaItemsService;
use backend\services\MaterialDticService;
use backend\services\OrdenCambioService;
use backend\services\ProyectoService;
use backend\services\SubcatalogoItemService;
use backend\services\TipoCondicionPreviaService;
use backend\services\UnidadMedidaService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * "Lista de items del proyecto" (capa de Presentacion): caso de uso separado
 * de "Gestionar Proyecto", del que solo usa el id.
 *
 * La lista son items y cantidades, SIN precios. Las reglas (cuando se puede
 * editar, estados, que no se quita) viven en ListaItemsService; las ordenes
 * de cambio, en OrdenCambioService.
 */
class ListaItemsController extends Controller
{
    private ListaItemsService $service;
    private OrdenCambioService $ordenes;

    public function init(): void
    {
        parent::init();
        $this->service = new ListaItemsService();
        $this->ordenes = new OrdenCambioService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'solicitar-cambio' => ['POST'],
                    'reemitir' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * GET muestra la lista. POST la guarda completa; si se pulso "Guardar y
     * enviar a cotizacion" (accion=enviar), ademas la deja en cotizacion.
     *
     * @param int $id id del proyecto
     */
    public function actionIndex(int $id)
    {
        $proyecto = $this->findProyecto($id);
        $peticion = Yii::$app->request;
        $errores = [];
        $enviado = [];

        if ($peticion->isPost) {
            $items = [];
            // Sin filas, "condiciones" y "materiales" llegan como '' (campo oculto): se borran todas.
            $condiciones = $peticion->post('condiciones', []);
            $materiales = $peticion->post('materiales', []);

            // Lugar de entrega por defecto (2026-11-10: se escribe aqui, ya no al crear el proyecto); null = no vino.
            $lugarEntrega = $peticion->post('lugarEntregaProyecto');

            if ($this->service->guardar($proyecto, $peticion->post('items', []), $errores, $items, UsuarioActual::nombre(), $condiciones, $materiales, $lugarEntrega)) {
                if ($peticion->post('accion') !== 'enviar') {
                    Yii::$app->session->setFlash('success', 'Lista guardada.');
                } elseif ($this->service->enviarACotizacion($proyecto)) {
                    Yii::$app->session->setFlash('success', 'Lista guardada y enviada a cotización. Desde ahora, para modificarla hay que reabrirla.');

                    $sinCantidad = $this->service->contarSinCantidad($proyecto);

                    if ($sinCantidad > 0) {
                        Yii::$app->session->setFlash('warning', "Atención: $sinCantidad ítem(s) de la lista tienen cantidad 0.");
                    }
                } else {
                    Yii::$app->session->setFlash('error', 'La lista se guardó, pero no se envió a cotización: todavía no tiene ítems.');
                }

                return $this->redirect(['index', 'id' => $proyecto->id]);
            }

            // No se guardo: se vuelve a mostrar lo que el usuario envio.
            $enviado = [
                'items' => $items,
                'condiciones' => array_values(array_filter((array) $condiciones, 'is_array')),
                'materiales' => array_values(array_filter((array) $materiales, 'is_array')),
                'lugarEntrega' => is_string($lugarEntrega) ? $lugarEntrega : null,
            ];
        }

        return $this->render('index', $this->datosPantalla($proyecto, $errores, $enviado));
    }

    /**
     * Todo lo que necesita la vista "index". Se separa de actionIndex() para
     * que la accion solo atienda el formulario (como en ElaborarPresupuestoController).
     *
     * @param string[] $errores los del guardado que fallo ([] al abrir la pantalla)
     * @param array{items?: array, condiciones?: array, materiales?: array, lugarEntrega?: ?string} $enviado lo que el
     *        usuario envio si no se pudo guardar ([] = mostrar lo guardado)
     */
    private function datosPantalla(Proyecto $proyecto, array $errores, array $enviado): array
    {
        $editable = $this->service->puedeEditarse($proyecto);
        $grupos = $this->service->agrupar($proyecto, $enviado['items'] ?? null);
        $itemsDeLaLista = array_merge([], ...array_column($grupos, 'items'));
        $emisiones = new EspecificacionEmisionService();
        $especificaciones = new EspecificacionService();
        $configuracion = ConfiguracionEspecificacion::obtener();
        // Opciones del selector "Especificación técnica": solo si la lista se edita.
        $selector = $editable
            ? $especificaciones->selector($this->service->especificacionesEnlazadas($proyecto))
            : EspecificacionService::SELECTOR_VACIO;

        return [
            'proyecto' => $proyecto,
            'pasos' => (new ProyectoService())->pasos($proyecto),
            'grupos' => $grupos,
            // Formulario de especificación vigente y avisos de cada item con especificacion.
            'emisiones' => $emisiones->estadoPorItem($itemsDeLaLista),
            'puedeReemitir' => $emisiones->puedeReemitir($proyecto),
            // Para el boton "Imprimir especificaciones (N)".
            'totalFormularios' => count($emisiones->vigentesDelProyecto($proyecto)),
            'configuracion' => $configuracion,
            // Texto de ejemplo del "Lugar de entrega" de cada fila y de la ventana "Reemitir": el que se usa si se deja vacio.
            'lugarEntregaPorDefecto' => EspecificacionEmisionService::lugarEntregaDe($proyecto, $configuracion),
            // El campo "Lugar de entrega" de arriba de la lista: lo enviado, el guardado o, si no tiene, la sugerencia
            // ("Administración de la Facultad de ..."); el de la configuracion es el ejemplo de la fila si queda vacio.
            'lugarEntregaProyecto' => $enviado['lugarEntrega'] ?? ($proyecto->lugarEntrega ?? Proyecto::lugarEntregaSugerido($proyecto->unidadDependencia) ?? ''),
            'lugarEntregaConfiguracion' => EspecificacionEmisionService::lugarEntregaPorDefecto($configuracion),
            'proximoCodigo' => (new CodificacionService())->proximo(
                CorrelativoDocumento::TIPO_ESPECIFICACION,
                $proyecto->proceso->codigo ?? CorrelativoDocumento::PROCESO
            ),
            'errores' => $errores,
            // Version del documento "Lista de materiales y condiciones previas" (paso 8b).
            'documento' => $this->service->documento($proyecto),
            'editable' => $editable,
            'subcatalogo' => $editable ? (new SubcatalogoItemService())->activos() : [],
            'especificaciones' => $selector,
            // Las mismas opciones, ya ordenadas para cada fila (sugeridas primero).
            'opcionesPorItem' => $editable ? $especificaciones->opcionesPorItem($selector, $itemsDeLaLista) : [],
            'ordenes' => $this->ordenes->historial($proyecto),
            // Secciones sin precio: lo guardado o lo que se envio si no se pudo guardar.
            'materiales' => $enviado['materiales'] ?? array_map(static fn ($m) => $m->fila(), $proyecto->materialesDtic),
            // Subtitulos para lo escrito a mano (lo del subcatalogo usa la categoria del item,
            // que el buscador toma del panel "Subcatálogo de redes").
            'categorias' => $editable ? array_values((new CategoriaService())->opciones()) : [],
            'unidades' => $editable ? (new UnidadMedidaService())->opcionesPorAbreviatura() : [],
            'condiciones' => $enviado['condiciones'] ?? array_map(static fn ($c) => $c->fila(), $proyecto->condicionesPrevias),
            'tiposCondicion' => (new TipoCondicionPreviaService())->opcionesParaProyecto($proyecto),
            'sugerenciasUnidadEncargada' => $editable ? (new CondicionPreviaService())->sugerenciasUnidad() : [],
        ];
    }

    /**
     * PDF de la lista GUARDADA (sin precios), para imprimir. Se abre en el
     * navegador ('inline') en vez de descargarse.
     */
    public function actionImprimir(int $id): Response
    {
        $proyecto = $this->findProyecto($id);

        $documento = $this->service->documento($proyecto);

        $html = $this->renderPartial('pdf', [
            'proyecto' => $proyecto,
            'grupos' => $this->service->agrupar($proyecto),
            'documento' => $documento,
            'configuracion' => ConfiguracionPresupuesto::obtener(),
            // Secciones sin precio, agrupadas: [subtitulo => materiales] y [tipo => trabajos].
            'materiales' => (new MaterialDticService())->agrupar($proyecto),
            'trabajos' => (new CondicionPreviaService())->agrupar($proyecto),
            // El del codigo de la lista (RD-GR-LM-005 -> GR); PI si no tiene.
            'proceso' => Proceso::nombreDe(CorrelativoDocumento::partesDe(CorrelativoDocumento::TIPO_LISTA, $proyecto->codigoLista)['proceso'] ?? CorrelativoDocumento::PROCESO),
        ]);

        $pdf = $this->service->generarPdf($proyecto, $html, $documento['borrador']);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf', 'inline' => true]
        );
    }

    /**
     * Todos los formularios de especificación vigentes de la lista en UN PDF, cada uno desde
     * una hoja nueva (en el orden de la lista). Es lo mismo que imprimirlos
     * uno por uno desde especificacion/formulario, juntos.
     */
    public function actionImprimirEspecificaciones(int $id): Response
    {
        $proyecto = $this->findProyecto($id);
        $emisiones = new EspecificacionEmisionService();
        $vigentes = $emisiones->vigentesDelProyecto($proyecto);

        if ($vigentes === []) {
            Yii::$app->session->setFlash('error', 'La lista no tiene especificaciones técnicas emitidas (formularios de especificación vigentes).');

            return $this->redirect(['index', 'id' => $proyecto->id]);
        }

        $formularios = array_map(fn ($emision) => [
            'emision' => $emision,
            'html' => $this->renderPartial('/especificacion/formulario-pdf', ['emision' => $emision, 'proceso' => Proceso::nombreDe($emision->codigoProceso())]),
        ], $vigentes);

        $pdf = $emisiones->generarPdfDelProyecto($proyecto, $formularios);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf', 'inline' => true]
        );
    }

    /**
     * Reabre la lista: registra el motivo (redes.OrdenCambio, que en pantalla
     * se llama "reapertura de la lista" desde el 2026-11-09, para no
     * confundirla con la orden de cambio de la parte 6) y la devuelve a "en edicion".
     */
    public function actionSolicitarCambio(int $id)
    {
        $proyecto = $this->findProyecto($id);
        $orden = new OrdenCambio();
        // solicitadoPor no admite vacio en la base. Siempre hay sesion (toda
        // la aplicacion la exige), asi que en la practica nunca es 'desconocido'.
        $usuario = UsuarioActual::nombre() ?? 'desconocido';

        if ($this->ordenes->solicitarCambioLista($proyecto, $orden, Yii::$app->request->post(), $usuario)) {
            Yii::$app->session->setFlash('success', 'Lista reabierta: volvió a edición.');
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $orden->getFirstErrors()));
        }

        return $this->redirect(['index', 'id' => $proyecto->id]);
    }

    /**
     * Emite de nuevo el formulario de especificación de un item (mismo codigo con la version
     * siguiente, ultima version de la ficha, cantidad actual de la lista). Funciona con la lista en
     * edicion o en cotizacion: no cambia la lista.
     *
     * @param int $id id del proyecto
     */
    public function actionReemitir(int $id): Response
    {
        $proyecto = $this->findProyecto($id);
        $peticion = Yii::$app->request;
        $item = $this->service->obtenerItem((int) $peticion->post('idProyectoItem'));

        if ($item === null || (int) $item->idProyecto !== $proyecto->id) {
            throw new NotFoundHttpException('El ítem no pertenece a la lista de este proyecto.');
        }

        $resultado = (new EspecificacionEmisionService())->reemitir($item, [
            'motivo' => $peticion->post('motivo'),
            'lugarEntrega' => $peticion->post('lugarEntrega'),
            'validezOfertaDias' => $peticion->post('validezOfertaDias'),
            'tiempoEntregaDias' => $peticion->post('tiempoEntregaDias'),
        ], UsuarioActual::nombre());

        if ($resultado['error'] !== null) {
            Yii::$app->session->setFlash('error', $resultado['error']);
        } else {
            Yii::$app->session->setFlash('success', 'Se emitió el ' . $resultado['emision']->codigoConVersion() . ' para "' . $resultado['emision']->titulo . '".');
        }

        return $this->redirect(['index', 'id' => $proyecto->id]);
    }

    protected function findProyecto(int $id): Proyecto
    {
        $proyecto = (new ProyectoService())->obtener($id);

        if ($proyecto === null) {
            throw new NotFoundHttpException('El proyecto solicitado no existe.');
        }

        return $proyecto;
    }
}
