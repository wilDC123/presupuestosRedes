<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\TipoOrdenCambio;
use backend\services\TipoOrdenCambioService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.TipoOrdenCambio (capa de Presentacion):
 * los tipos de orden de cambio (2026-11-09; formato RD-GD-FR-006, 7.1.1).
 *
 * No consulta la base de datos directamente: todo lo delega a
 * TipoOrdenCambioService (capa de Servicio).
 */
class TipoOrdenCambioController extends Controller
{
    private TipoOrdenCambioService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new TipoOrdenCambioService();
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
            'nuevo' => new TipoOrdenCambio(),
        ]);
    }

    public function actionCreate()
    {
        $model = new TipoOrdenCambio();

        if ($this->service->crear($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Tipo "' . $model->nombre . '" agregado.');

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
            Yii::$app->session->setFlash('success', 'Tipo actualizado.');

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

    protected function findModel(int $id): TipoOrdenCambio
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El tipo solicitado no existe.');
        }

        return $model;
    }
}
