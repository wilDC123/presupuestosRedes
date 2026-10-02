<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Especificacion;
use backend\services\EspecificacionService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Controlador CRUD para redes.Especificacion (capa de Presentacion).
 *
 * Solo traduce la peticion HTTP (POST, archivo subido) a llamadas a
 * EspecificacionService, y elige que vista/respuesta devolver.
 */
class EspecificacionController extends Controller
{
    private EspecificacionService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new EspecificacionService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'extraer-metadatos' => ['POST'],
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
        $model = new Especificacion();

        if ($this->guardarDesdeFormulario($model)) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($this->guardarDesdeFormulario($model)) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete(int $id)
    {
        $this->service->eliminar($this->findModel($id));

        return $this->redirect(['index']);
    }

    /**
     * Endpoint AJAX invocado desde el boton "Extraer datos automaticamente"
     * del formulario. Recibe el mismo archivo del input archivoEspecificacion
     * y devuelve los metadatos encontrados (o un mensaje de error) como JSON.
     */
    public function actionExtraerMetadatos(): Response
    {
        $archivo = UploadedFile::getInstanceByName('archivoEspecificacion');

        return $this->asJson($this->service->extraerMetadatos($archivo));
    }

    protected function findModel(int $id): Especificacion
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('La especificación solicitada no existe.');
        }

        return $model;
    }

    private function guardarDesdeFormulario(Especificacion $model): bool
    {
        return $this->service->guardar(
            $model,
            Yii::$app->request->post(),
            UploadedFile::getInstance($model, 'archivoEspecificacion')
        );
    }
}
