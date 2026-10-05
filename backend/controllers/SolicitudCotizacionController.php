<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Proyecto;
use backend\models\SolicitudCotizacion;
use backend\services\ProyectoService;
use backend\services\SolicitudCotizacionService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * "Solicitudes de cotizacion" de un proyecto (capa de Presentacion): repartir
 * los items de la lista en grupos por rubro de proveedor e imprimir el
 * documento de cada grupo. Las reglas viven en SolicitudCotizacionService.
 */
class SolicitudCotizacionController extends Controller
{
    private SolicitudCotizacionService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new SolicitudCotizacionService();
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
                    'eliminar' => ['POST'],
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
            'solicitudes' => $this->service->listar($proyecto),
            'sinAsignar' => $this->service->sinAsignar($proyecto),
            'editable' => $this->service->puedeEditarse($proyecto),
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

        if (!$this->service->quitarItem($proyecto, $item)) {
            Yii::$app->session->setFlash('error', 'No se pudo quitar el ítem: las solicitudes de este proyecto ya no se modifican.');
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
     * @param int $id id de la solicitud
     */
    public function actionEliminar(int $id)
    {
        $solicitud = $this->findSolicitud($id);

        if ($this->service->eliminar($solicitud)) {
            Yii::$app->session->setFlash('success', $solicitud->codigoCorto() . ' eliminada. Sus ítems volvieron a "sin asignar".');
        } else {
            Yii::$app->session->setFlash('error', 'No se pudo eliminar: las solicitudes de este proyecto ya no se modifican.');
        }

        return $this->redirect(['index', 'id' => $solicitud->idProyecto]);
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
            'especificaciones' => $this->service->especificacionesPorItem($solicitud),
        ]);

        $pdf = $this->service->generarPdf($solicitud, $html);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf', 'inline' => true]
        );
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
}
