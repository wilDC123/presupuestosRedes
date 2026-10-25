<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\UnidadMedida;
use backend\services\UnidadMedidaService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.UnidadMedida (capa de Presentacion).
 *
 * No consulta la base de datos directamente: todo lo delega a
 * UnidadMedidaService (capa de Servicio).
 */
class UnidadMedidaController extends Controller
{
    private UnidadMedidaService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new UnidadMedidaService();
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
            'nueva' => new UnidadMedida(),
        ]);
    }

    public function actionCreate()
    {
        $model = new UnidadMedida();

        if ($this->service->crear($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Unidad "' . $model->etiqueta() . '" creada.');

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
            Yii::$app->session->setFlash('success', 'Unidad actualizada.');

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

    protected function findModel(int $id): UnidadMedida
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('La unidad de medida solicitada no existe.');
        }

        return $model;
    }
}
