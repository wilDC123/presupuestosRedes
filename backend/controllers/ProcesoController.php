<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Proceso;
use backend\services\ProcesoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.Proceso (capa de Presentacion).
 *
 * No consulta la base de datos directamente: todo lo delega a
 * ProcesoService (capa de Servicio).
 */
class ProcesoController extends Controller
{
    private ProcesoService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new ProcesoService();
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
            'filas' => $this->service->listarConConteo(),
            'nuevo' => new Proceso(),
        ]);
    }

    public function actionCreate()
    {
        $model = new Proceso();

        if ($this->service->crear($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Proceso "' . $model->etiqueta() . '" creado.');

            return $this->redirect(['index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($this->service->actualizar($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Proceso actualizado.');

            return $this->redirect(['index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete(int $id)
    {
        $error = $this->service->eliminar($this->findModel($id));

        if ($error !== null) {
            Yii::$app->session->setFlash('error', $error);
        }

        return $this->redirect(['index']);
    }

    protected function findModel(int $id): Proceso
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El proceso solicitado no existe.');
        }

        return $model;
    }
}
