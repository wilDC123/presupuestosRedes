<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\components\ArchivoSubido;
use backend\models\Cotizacion;
use backend\services\CotizacionService;
use backend\services\EspecificacionService;
use backend\services\ProveedorService;
use backend\services\SubcatalogoItemService;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Controlador CRUD para redes.Cotizacion (capa de Presentacion).
 *
 * La validacion conjunta cabecera + lineas y la transaccion viven en
 * CotizacionService; aca solo se recibe la peticion y se elige la vista.
 */
class CotizacionController extends Controller
{
    private CotizacionService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new CotizacionService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(?int $idProveedor = null): string
    {
        return $this->render('index', [
            'dataProvider' => $this->service->listar($idProveedor),
        ]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionCreate()
    {
        $model = new Cotizacion();

        $archivo = UploadedFile::getInstance($model, 'documentoFile');

        if ($this->service->crear($model, Yii::$app->request->post(), $archivo)) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
            'proveedores' => $this->proveedores($model),
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);
        $modelsDetalle = null;

        // Las cotizaciones de una solicitud se corrigen desde la solicitud.
        if ($model->idSolicitudCotizacion !== null) {
            return $this->redirect(['/solicitud-cotizacion/editar-cotizacion', 'id' => $model->id]);
        }

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $modelsDetalle = $this->service->cargarDetallesDesdeFormulario($model, $post);
            $archivo = UploadedFile::getInstance($model, 'documentoFile');

            if ($this->service->actualizar($model, $modelsDetalle, $post, $archivo)) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $modelsDetalle = $this->service->detallesParaFormulario($model, $modelsDetalle);

        return $this->render('update', [
            'model' => $model,
            'proveedores' => $this->proveedores($model),
            'modelsDetalle' => $modelsDetalle,
            // Opciones de los desplegables de cada linea: las vistas no
            // consultan la base. Se incluyen las especificaciones que ya
            // tienen las lineas aunque esten desactivadas.
            'items' => (new SubcatalogoItemService())->opciones(),
            'especificaciones' => (new EspecificacionService())->selector(ArrayHelper::getColumn($modelsDetalle, 'idEspecificacion')),
        ]);
    }

    /**
     * Proveedores del desplegable: solo los ACTIVOS, mas el que ya tiene la
     * cotizacion aunque se haya desactivado (para no perderlo al editarla).
     *
     * @return array<int, string>
     */
    private function proveedores(Cotizacion $model): array
    {
        return (new ProveedorService())->opcionesActivos($model->idProveedor ? (int) $model->idProveedor : null);
    }

    public function actionDelete(int $id)
    {
        $model = $this->findModel($id);
        $motivo = $this->service->eliminar($model);

        if ($motivo !== null) {
            Yii::$app->session->setFlash('error', $motivo);

            return $this->redirect(['view', 'id' => $model->id]);
        }

        Yii::$app->session->setFlash('success', 'Cotización eliminada.');

        return $this->redirect(['index']);
    }

    /**
     * Entrega el PDF de la cotizacion. El archivo esta fuera de la carpeta
     * publica (ver ArchivoSubido): solo se descarga con sesion iniciada.
     * Se abre en el navegador ('inline') en vez de descargarse.
     */
    public function actionDocumento(int $id): Response
    {
        $model = $this->findModel($id);
        $ruta = ArchivoSubido::rutaAbsoluta($model->documentoRuta);

        if ($ruta === null) {
            throw new NotFoundHttpException('La cotización no tiene un documento disponible.');
        }

        return Yii::$app->response->sendFile($ruta, 'cotizacion_' . $model->id . '.' . pathinfo($ruta, PATHINFO_EXTENSION), ['inline' => true]);
    }

    protected function findModel(int $id): Cotizacion
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('La cotización solicitada no existe.');
        }

        return $model;
    }
}
