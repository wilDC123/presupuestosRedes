<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Proyecto;
use backend\services\ProyectoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.Proyecto (capa de Presentacion).
 *
 * No consulta la base de datos directamente: todo lo delega a
 * ProyectoService (capa de Servicio).
 */
class ProyectoController extends Controller
{
    private ProyectoService $service;

    /**
     * init() es el "constructor" recomendado en Yii2 para componentes: se
     * ejecuta una vez al crear el controlador, antes de cualquier accion.
     */
    public function init(): void
    {
        parent::init();
        $this->service = new ProyectoService();
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
        $model = new Proyecto();

        if ($this->service->crear($model, Yii::$app->request->post())) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($this->service->actualizar($model, Yii::$app->request->post())) {
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

    protected function findModel(int $id): Proyecto
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El proyecto solicitado no existe.');
        }

        return $model;
    }
}
