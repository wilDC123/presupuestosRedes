<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Plantilla;
use backend\services\CategoriaService;
use backend\services\PlantillaService;
use backend\services\SubcatalogoItemService;
use backend\services\TipoProyectoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Administracion de plantillas de items (capa de Presentacion): crear,
 * editar, activar/desactivar y eliminar. Todo lo delega en PlantillaService.
 */
class PlantillaController extends Controller
{
    private PlantillaService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new PlantillaService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'cambiar-activo' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        return $this->render('index', [
            'filas' => $this->service->listar(),
        ]);
    }

    public function actionCreate()
    {
        return $this->guardar(new Plantilla(), 'create');
    }

    public function actionUpdate(int $id)
    {
        return $this->guardar($this->findModel($id), 'update');
    }

    public function actionCambiarActivo(int $id)
    {
        $model = $this->findModel($id);
        $this->service->cambiarActivo($model, !$model->activo);

        Yii::$app->session->setFlash('success', 'Plantilla "' . $model->nombre . '" ' . ($model->activo ? 'activada' : 'desactivada') . '.');

        return $this->redirect(['index']);
    }

    public function actionDelete(int $id)
    {
        $model = $this->findModel($id);

        if (!$this->service->eliminar($model)) {
            Yii::$app->session->setFlash('error', 'No se puede eliminar "' . $model->nombre . '" porque ya fue usada en algún proyecto. Puede desactivarla para que no se ofrezca en proyectos nuevos.');
        }

        return $this->redirect(['index']);
    }

    /**
     * Crear y editar comparten todo: procesar el POST y, si no se guardo,
     * mostrar el formulario con lo que el usuario envio.
     */
    private function guardar(Plantilla $model, string $vista)
    {
        $peticion = Yii::$app->request;
        $itemsEnviados = [];

        if ($this->service->guardar($model, $peticion->post(), $itemsEnviados)) {
            Yii::$app->session->setFlash('success', 'Plantilla "' . $model->nombre . '" guardada.');

            return $this->redirect(['index']);
        }

        // En GET se muestra lo guardado; si el POST fallo, lo que se envio.
        if ($peticion->isPost) {
            $items = $itemsEnviados;
            $idsCategoria = array_map('intval', array_filter((array) $peticion->post('categorias', []), 'is_string'));
        } else {
            $items = $model->isNewRecord ? [] : $model->items;
            $idsCategoria = $model->isNewRecord ? [] : $this->service->idsCategoria($model);
        }

        return $this->render($vista, [
            'model' => $model,
            'items' => $items,
            'idsCategoria' => $idsCategoria,
            'categorias' => (new CategoriaService())->opciones(),
            'tipos' => (new TipoProyectoService())->opciones($model->getOldAttribute('idTipoProyecto')),
            'subcatalogo' => (new SubcatalogoItemService())->activos(),
        ]);
    }

    protected function findModel(int $id): Plantilla
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('La plantilla solicitada no existe.');
        }

        return $model;
    }
}
