<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Personal;
use backend\services\PersonalService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.Personal (capa de Presentacion): las personas
 * que se ponen en el equipo de un proyecto.
 *
 * No consulta la base de datos directamente: todo lo delega a
 * PersonalService (capa de Servicio).
 */
class PersonalController extends Controller
{
    private PersonalService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new PersonalService();
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
            'nuevo' => new Personal(),
        ]);
    }

    public function actionCreate()
    {
        $model = new Personal();

        if ($this->service->crear($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', '"' . $model->nombre . '" agregado al personal.');

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
            Yii::$app->session->setFlash('success', 'Persona actualizada.');

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

    protected function findModel(int $id): Personal
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('La persona solicitada no existe.');
        }

        return $model;
    }
}
