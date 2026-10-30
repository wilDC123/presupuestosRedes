<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\RolProyecto;
use backend\services\RolProyectoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.RolProyecto (capa de Presentacion): los roles
 * del equipo del proyecto.
 *
 * No consulta la base de datos directamente: todo lo delega a
 * RolProyectoService (capa de Servicio).
 */
class RolProyectoController extends Controller
{
    private RolProyectoService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new RolProyectoService();
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
            'nuevo' => new RolProyecto(),
        ]);
    }

    public function actionCreate()
    {
        $model = new RolProyecto();

        if ($this->service->crear($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Rol "' . $model->nombre . '" agregado.');

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
            Yii::$app->session->setFlash('success', 'Rol actualizado.');

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

    protected function findModel(int $id): RolProyecto
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El rol solicitado no existe.');
        }

        return $model;
    }
}
