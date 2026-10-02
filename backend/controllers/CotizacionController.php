<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Cotizacion;
use backend\services\CotizacionService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
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

    public function actionIndex(): string
    {
        return $this->render('index', [
            'dataProvider' => $this->service->listar(),
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
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);
        $modelsDetalle = null;

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $modelsDetalle = $this->service->cargarDetallesDesdeFormulario($model, $post);
            $archivo = UploadedFile::getInstance($model, 'documentoFile');

            if ($this->service->actualizar($model, $modelsDetalle, $post, $archivo)) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
            'modelsDetalle' => $this->service->detallesParaFormulario($model, $modelsDetalle),
        ]);
    }

    public function actionDelete(int $id)
    {
        $this->service->eliminar($this->findModel($id));

        return $this->redirect(['index']);
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
