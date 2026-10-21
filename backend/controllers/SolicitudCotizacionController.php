<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\ConfiguracionEspecificacion;
use backend\models\ConfiguracionSolicitudCotizacion;
use backend\models\Cotizacion;
use backend\models\Proyecto;
use backend\models\SolicitudCotizacion;
use backend\services\CotizacionService;
use backend\services\EspecificacionEmisionService;
use backend\services\ProveedorService;
use backend\services\ProyectoService;
use backend\services\SolicitudCotizacionService;
use Yii;
use yii\helpers\ArrayHelper;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * "Solicitudes de cotizacion" de un proyecto (capa de Presentacion): repartir
 * los items de la lista en grupos por rubro de proveedor, imprimir el
 * documento de cada grupo, y registrar las cotizaciones que responden los
 * proveedores (con su cuadro comparativo).
 *
 * Las reglas viven en SolicitudCotizacionService y CotizacionService.
 */
class SolicitudCotizacionController extends Controller
{
    private SolicitudCotizacionService $service;
    private CotizacionService $cotizaciones;

    public function init(): void
    {
        parent::init();
        $this->service = new SolicitudCotizacionService();
        $this->cotizaciones = new CotizacionService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'mover' => ['POST'],
                    'por-clasificacion' => ['POST'],
                    'quitar-item' => ['POST'],
                    'actualizar' => ['POST'],
                    'referencias' => ['POST'],
                    'eliminar' => ['POST'],
                    'eliminar-cotizacion' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * @param int $id id del proyecto
     */
    public function actionIndex(int $id): string
    {
        $proyecto = $this->findProyecto($id);

        return $this->render('index', [
            'proyecto' => $proyecto,
            'pasos' => (new ProyectoService())->pasos($proyecto),
            'solicitudes' => $this->service->listar($proyecto),
            'sinAsignar' => $this->service->sinAsignar($proyecto),
            'editable' => $this->service->puedeEditarse($proyecto),
            'modelosSugeridos' => $this->service->modelosSugeridos($proyecto),
        ]);
    }

    /**
     * Pone los items marcados en la solicitud elegida, o en una nueva
     * (destino = "nueva", con su nombre).
     *
     * @param int $id id del proyecto
     */
    public function actionMover(int $id)
    {
        $proyecto = $this->findProyecto($id);
        $peticion = Yii::$app->request;
        $destino = (string) $peticion->post('destino');
        $error = null;

        $movidos = $this->service->mover(
            $proyecto,
            $peticion->post('items', []),
            $destino === 'nueva' ? null : (int) $destino,
            (string) $peticion->post('nombreNueva'),
            $error
        );

        if ($movidos === null) {
            Yii::$app->session->setFlash('error', $error);
        } else {
            Yii::$app->session->setFlash('success', "$movidos ítem(s) asignados a la solicitud.");
        }

        return $this->redirect(['index', 'id' => $proyecto->id]);
    }

    /**
     * @param int $id id del proyecto
     */
    public function actionPorClasificacion(int $id)
    {
        $proyecto = $this->findProyecto($id);
        $creadas = $this->service->crearPorClasificacion($proyecto);

        if ($creadas === 0) {
            Yii::$app->session->setFlash('error', 'No se creó ninguna solicitud: no hay ítems sin asignar, o las solicitudes de este proyecto ya no se modifican.');
        } else {
            Yii::$app->session->setFlash('success', "Se crearon $creadas solicitud(es), una por clasificación. Mueva los ítems que no correspondan.");
        }

        return $this->redirect(['index', 'id' => $proyecto->id]);
    }

    /**
     * @param int $id id del proyecto
     * @param int $item id del item de la lista
     */
    public function actionQuitarItem(int $id, int $item)
    {
        $proyecto = $this->findProyecto($id);

        $motivo = $this->service->quitarItem($proyecto, $item);

        if ($motivo !== null) {
            Yii::$app->session->setFlash('error', $motivo);
        }

        return $this->redirect(['index', 'id' => $proyecto->id]);
    }

    /**
     * @param int $id id de la solicitud
     */
    public function actionActualizar(int $id)
    {
        $solicitud = $this->findSolicitud($id);

        if ($this->service->actualizar($solicitud, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', $solicitud->codigoCorto() . ' actualizada.');
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $solicitud->getFirstErrors()) ?: 'No se pudo actualizar la solicitud.');
        }

        return $this->redirect(['index', 'id' => $solicitud->idProyecto]);
    }

    /**
     * Guarda la marca y modelo de referencia de los items de una solicitud.
     *
     * @param int $id id de la solicitud
     */
    public function actionReferencias(int $id)
    {
        $solicitud = $this->findSolicitud($id);
        $error = $this->service->guardarReferencias($solicitud, Yii::$app->request->post('referencias', []));

        if ($error === null) {
            Yii::$app->session->setFlash('success', 'Marcas y modelos de referencia de ' . $solicitud->codigoCorto() . ' guardados.');
        } else {
            Yii::$app->session->setFlash('error', $error);
        }

        return $this->redirect(['index', 'id' => $solicitud->idProyecto]);
    }

    /**
     * @param int $id id de la solicitud
     */
    public function actionEliminar(int $id)
    {
        $solicitud = $this->findSolicitud($id);

        $motivo = $this->service->eliminar($solicitud);

        if ($motivo === null) {
            Yii::$app->session->setFlash('success', $solicitud->codigoCorto() . ' eliminada. Sus ítems volvieron a "sin asignar".');
        } else {
            Yii::$app->session->setFlash('error', $motivo);
        }

        return $this->redirect(['index', 'id' => $solicitud->idProyecto]);
    }

    /**
     * Registra la respuesta de un proveedor a la solicitud: los items ya
     * vienen cargados; solo se escriben marca/modelo y precio.
     *
     * @param int $id id de la solicitud
     */
    public function actionCotizar(int $id)
    {
        $solicitud = $this->findSolicitud($id);

        return $this->formularioCotizacion($solicitud, $this->cotizaciones->nuevaDesdeSolicitud($solicitud));
    }

    /**
     * Corrige una cotizacion ya registrada desde su solicitud.
     *
     * @param int $id id de la cotizacion
     */
    public function actionEditarCotizacion(int $id)
    {
        $cotizacion = $this->findCotizacionDeSolicitud($id);

        return $this->formularioCotizacion($cotizacion->solicitud, $cotizacion);
    }

    /**
     * @param int $id id de la cotizacion
     */
    public function actionEliminarCotizacion(int $id)
    {
        $cotizacion = $this->findCotizacionDeSolicitud($id);
        $solicitud = $cotizacion->solicitud;
        $motivo = $this->cotizaciones->eliminar($cotizacion);

        Yii::$app->session->setFlash(
            $motivo === null ? 'success' : 'error',
            $motivo ?? 'Cotización eliminada de ' . $solicitud->codigoCorto() . '.'
        );

        return $this->redirect(['index', 'id' => $solicitud->idProyecto]);
    }

    /**
     * Cuadro comparativo de precios de la solicitud (item x proveedor).
     *
     * @param int $id id de la solicitud
     */
    public function actionComparativo(int $id): string
    {
        $solicitud = $this->findSolicitud($id);

        return $this->render('comparativo', [
            'solicitud' => $solicitud,
            'pasos' => (new ProyectoService())->pasos($solicitud->proyecto),
            'comparativo' => $this->cotizaciones->comparativo($solicitud),
        ]);
    }

    /**
     * Documento de la solicitud en PDF, para imprimir o enviar al proveedor.
     * Se abre en el navegador ('inline') en vez de descargarse.
     *
     * @param int $id id de la solicitud
     */
    public function actionImprimir(int $id): Response
    {
        $solicitud = $this->findSolicitud($id);

        $html = $this->renderPartial('pdf', [
            'solicitud' => $solicitud,
            'configuracion' => ConfiguracionSolicitudCotizacion::obtener(),
            // Los plazos (validez de la oferta, tiempo de entrega) son los
            // mismos del formulario NET: se configuran en un solo lugar.
            'configuracionNet' => ConfiguracionEspecificacion::obtener(),
        ]);

        // Al final, el formulario NET de cada item que lo tiene: el proveedor
        // recibe en un solo archivo lo que se le pide y como se le pide.
        $logo = (new EspecificacionEmisionService())->rutaLogo();
        $formularios = array_map(fn ($emision) => [
            'emision' => $emision,
            'html' => $this->renderPartial('/especificacion/formulario-pdf', ['emision' => $emision, 'logo' => $logo]),
        ], $this->service->emisionesVigentes($solicitud));

        $pdf = $this->service->generarPdf($solicitud, $html, $formularios);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf', 'inline' => true]
        );
    }

    /**
     * Textos fijos del documento de la solicitud de cotizacion. Valen para
     * todas las solicitudes, tambien al reimprimir las anteriores.
     *
     * @param int|null $proyecto id del proyecto desde el que se entro, solo
     *                           para el boton "volver"
     */
    public function actionConfiguracion(?int $proyecto = null)
    {
        $configuracion = ConfiguracionSolicitudCotizacion::obtener();

        if (Yii::$app->request->isPost && $this->service->guardarConfiguracion($configuracion, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Configuración de la solicitud de cotización guardada.');

            return $this->redirect(['configuracion', 'proyecto' => $proyecto]);
        }

        return $this->render('configuracion', [
            'configuracion' => $configuracion,
            'proyecto' => $proyecto !== null ? (new ProyectoService())->obtener($proyecto) : null,
        ]);
    }

    protected function findProyecto(int $id): Proyecto
    {
        $proyecto = (new ProyectoService())->obtener($id);

        if ($proyecto === null) {
            throw new NotFoundHttpException('El proyecto solicitado no existe.');
        }

        return $proyecto;
    }

    protected function findSolicitud(int $id): SolicitudCotizacion
    {
        $solicitud = $this->service->obtener($id);

        if ($solicitud === null) {
            throw new NotFoundHttpException('La solicitud de cotización no existe.');
        }

        return $solicitud;
    }

    /**
     * Solo cotizaciones registradas desde una solicitud: las sueltas se
     * editan en el menu Cotizaciones.
     */
    protected function findCotizacionDeSolicitud(int $id): Cotizacion
    {
        $cotizacion = $this->cotizaciones->obtener($id);

        if ($cotizacion === null || $cotizacion->solicitud === null) {
            throw new NotFoundHttpException('La cotización no existe o no es de una solicitud de cotización.');
        }

        return $cotizacion;
    }

    /**
     * GET muestra el formulario; POST guarda y vuelve a las solicitudes.
     */
    private function formularioCotizacion(SolicitudCotizacion $solicitud, Cotizacion $cotizacion)
    {
        $peticion = Yii::$app->request;
        $filas = $this->cotizaciones->filasDesdeSolicitud($solicitud, $cotizacion);
        $errores = [];

        if ($peticion->isPost) {
            $archivo = UploadedFile::getInstance($cotizacion, 'documentoFile');

            if ($this->cotizaciones->guardarDesdeSolicitud($solicitud, $cotizacion, $peticion->post(), $archivo, $filas, $errores)) {
                Yii::$app->session->setFlash('success', 'Cotización de ' . ($cotizacion->proveedor->razonSocial ?? 'proveedor') . ' guardada en ' . $solicitud->codigoCorto() . '.');

                // Aviso que NO bloquea, igual que en el presupuesto.
                if ($cotizacion->estaVencida()) {
                    Yii::$app->session->setFlash('warning', 'Atención: según su fecha y días de validez, esta cotización ya está vencida.');
                }

                return $this->redirect(['index', 'id' => $solicitud->idProyecto]);
            }
        }

        return $this->render('cotizar', [
            'solicitud' => $solicitud,
            'pasos' => (new ProyectoService())->pasos($solicitud->proyecto),
            'cotizacion' => $cotizacion,
            'filas' => $filas,
            // Marca y modelo pedidos en la solicitud, para comparar con lo ofertado.
            // array_filter() quita los items sin referencia (null).
            'referencias' => array_filter(ArrayHelper::map($solicitud->items, 'idProyectoItem', 'marcaModeloReferencia')),
            'errores' => $errores,
            'editable' => $this->service->puedeEditarse($solicitud->proyecto),
            'proveedores' => (new ProveedorService())->opcionesActivos($cotizacion->idProveedor ? (int) $cotizacion->idProveedor : null),
        ]);
    }
}
