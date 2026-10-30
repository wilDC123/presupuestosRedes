<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\TipoCondicionPrevia;
use backend\services\TipoCondicionPreviaService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.TipoCondicionPrevia (capa de Presentacion):
 * los tipos de condicion previa del proyecto.
 *
 * No consulta la base de datos directamente: todo lo delega a
 * TipoCondicionPreviaService (capa de Servicio).
 */
class TipoCondicionPreviaController extends Controller
{
    private TipoCondicionPreviaService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new TipoCondicionPreviaService();
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
            'nuevo' => new TipoCondicionPrevia(),
        ]);
    }

    public function actionCreate()
    {
        $model = new TipoCondicionPrevia();

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

    protected function findModel(int $id): TipoCondicionPrevia
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El tipo solicitado no existe.');
        }

        return $model;
    }
}
