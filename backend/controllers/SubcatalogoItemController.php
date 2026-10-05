<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\SubcatalogoItem;
use backend\services\SubcatalogoItemService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.SubcatalogoItem (capa de Presentacion).
 *
 * No consulta la base de datos (ni dbo.CatalogoSigma) directamente: todo lo
 * delega a SubcatalogoItemService (capa de Servicio).
 */
class SubcatalogoItemController extends Controller
{
    private SubcatalogoItemService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new SubcatalogoItemService();
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
        $textoBusqueda = trim((string) Yii::$app->request->get('q', ''));

        $partida = trim((string) Yii::$app->request->get('partida', ''));

        return $this->render('index', [
            'dataProvider' => $this->service->listar($partida),
            'partida' => $partida,
            'partidasEnUso' => $this->service->partidasEnUso(),
            'textoBusqueda' => $textoBusqueda,
            'resultadosSigma' => $this->service->buscarEnCatalogoSigma($textoBusqueda),
            'nombresDticPorIdSigma' => $this->service->nombresDticPorIdSigma(),
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
        $model = new SubcatalogoItem();

        if ($this->service->crear($model, Yii::$app->request->post())) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        // Prellenado desde "Buscar en catalogo institucional": llega por GET
        // solo el idSigma de la fila de dbo.CatalogoSigma que el usuario
        // eligio; el servicio busca el resto en la base.
        if (Yii::$app->request->isGet && Yii::$app->request->get('idSigma') !== null) {
            $this->service->prellenarDesdeSigma($model, (string) Yii::$app->request->get('idSigma'));
        }

        return $this->render('create', $this->datosFormulario($model));
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($this->service->actualizar($model, Yii::$app->request->post())) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', $this->datosFormulario($model));
    }

    public function actionDelete(int $id)
    {
        $this->service->eliminar($this->findModel($id));

        return $this->redirect(['index']);
    }

    /**
     * Variables que necesita _form.php (via create.php / update.php): ademas
     * del item, lo que SIGMA dice de su idSigma, para mostrarlo en solo
     * lectura. Se arma aqui para que la vista no consulte la base.
     */
    private function datosFormulario(SubcatalogoItem $model): array
    {
        return [
            'model' => $model,
            'sigma' => $this->service->sigmaDe($model),
            'partidaSigma' => $this->service->partidaDesdeSigma($model),
            'usadoPor' => $this->service->otrosItemsConMismoSigma($model),
        ];
    }

    protected function findModel(int $id): SubcatalogoItem
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El ítem de subcatálogo solicitado no existe.');
        }

        return $model;
    }
}
