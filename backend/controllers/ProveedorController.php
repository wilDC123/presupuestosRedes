<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Proveedor;
use backend\services\ProveedorService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.Proveedor (capa de Presentacion).
 *
 * No consulta la base de datos directamente: todo lo delega a
 * ProveedorService (capa de Servicio).
 */
class ProveedorController extends Controller
{
    private ProveedorService $service;

    /**
     * init() es el "constructor" recomendado en Yii2 para componentes: se
     * ejecuta una vez al crear el controlador, antes de cualquier accion.
     */
    public function init(): void
    {
        parent::init();
        $this->service = new ProveedorService();
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

    /**
     * Listado con panel lateral: ?buscar= filtra la tabla y ?id= elige el
     * proveedor que se muestra en "Proveedor seleccionado".
     */
    public function actionIndex(?int $id = null): string
    {
        $buscar = trim((string) Yii::$app->request->get('buscar', ''));
        $seleccionado = $id === null ? null : $this->service->obtener($id);

        return $this->render('index', [
            'filas' => $this->service->listar($buscar),
            'buscar' => $buscar,
            'seleccionado' => $seleccionado,
            'resumen' => $seleccionado === null ? null : $this->service->resumen($seleccionado),
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
        $model = new Proveedor();

        if ($this->service->crear($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Proveedor registrado.');

            return $this->redirect(['index', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($this->service->actualizar($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Datos del proveedor actualizados.');

            return $this->redirect(['index', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete(int $id)
    {
        $model = $this->findModel($id);

        if (!$this->service->eliminar($model)) {
            Yii::$app->session->setFlash('error', 'No se puede eliminar "' . $model->razonSocial . '" porque tiene cotizaciones registradas. Puede marcarlo como inactivo desde "Editar datos".');

            return $this->redirect(['index', 'id' => $model->id]);
        }

        return $this->redirect(['index']);
    }

    protected function findModel(int $id): Proveedor
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El proveedor solicitado no existe.');
        }

        return $model;
    }
}
