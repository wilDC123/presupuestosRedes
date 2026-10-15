<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Especificacion;
use backend\services\CategoriaService;
use backend\services\EspecificacionService;
use backend\services\SubcatalogoItemService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Controlador de la LIBRERIA de especificaciones tecnicas (capa de
 * Presentacion).
 *
 * Solo traduce la peticion HTTP (filtros GET, POST, archivo subido) a
 * llamadas a EspecificacionService, y elige que vista/respuesta devolver.
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

    /**
     * Libreria con filtros por GET: ?categoria=, ?buscar=, ?activo=.
     */
    public function actionIndex(): string
    {
        $peticion = Yii::$app->request;
        $filtros = [
            'categoria' => trim((string) $peticion->get('categoria', '')),
            'buscar' => trim((string) $peticion->get('buscar', '')),
            'activo' => trim((string) $peticion->get('activo', '')),
        ];

        return $this->render('index', [
            'filas' => $this->service->listar($filtros),
            'filtros' => $filtros,
            'categorias' => (new CategoriaService())->opciones(),
        ]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);

        return $this->render('view', [
            'model' => $model,
            'usos' => $this->service->usos($model),
        ]);
    }

    public function actionCreate()
    {
        $model = new Especificacion();

        if ($this->guardarDesdeFormulario($model)) {
            Yii::$app->session->setFlash('success', 'Especificación agregada a la librería.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', $this->datosFormulario($model));
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($this->guardarDesdeFormulario($model)) {
            Yii::$app->session->setFlash('success', 'Especificación actualizada.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', $this->datosFormulario($model));
    }

    public function actionDelete(int $id)
    {
        $model = $this->findModel($id);
        $motivo = $this->service->eliminar($model);

        if ($motivo !== null) {
            Yii::$app->session->setFlash('error', $motivo);

            return $this->redirect(['view', 'id' => $model->id]);
        }

        Yii::$app->session->setFlash('success', 'Especificación eliminada.');

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

    /**
     * Lo que necesita el formulario (crear y editar): las opciones de los
     * desplegables llegan listas desde los servicios.
     */
    private function datosFormulario(Especificacion $model): array
    {
        return [
            'model' => $model,
            'categorias' => (new CategoriaService())->opciones(),
            'items' => (new SubcatalogoItemService())->opciones(),
        ];
    }
}
