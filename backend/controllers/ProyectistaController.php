<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Proyectista;
use backend\services\ProyectistaService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.Proyectista (capa de Presentacion).
 *
 * No consulta la base de datos directamente: todo lo delega a
 * ProyectistaService (capa de Servicio).
 */
class ProyectistaController extends Controller
{
    private ProyectistaService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new ProyectistaService();
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
            'nuevo' => new Proyectista(),
        ]);
    }

    public function actionCreate()
    {
        $model = new Proyectista();

        if ($this->service->crear($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Proyectista "' . $model->nombre . '" agregado.');

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
            Yii::$app->session->setFlash('success', 'Proyectista actualizado.');

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

    protected function findModel(int $id): Proyectista
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El proyectista solicitado no existe.');
        }

        return $model;
    }
}
