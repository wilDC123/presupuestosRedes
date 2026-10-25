<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\SubcatalogoItem;
use backend\services\CategoriaService;
use backend\services\SubcatalogoItemService;
use backend\services\UnidadMedidaService;
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

    /**
     * Dos busquedas independientes en la misma pantalla: los filtros del
     * subcatalogo propio (buscar, idCategoria, partida, estado) y el texto
     * "q" del buscador del catalogo SIGMA.
     */
    public function actionIndex(): string
    {
        $peticion = Yii::$app->request;

        $filtros = [
            'buscar' => trim((string) $peticion->get('buscar', '')),
            'idCategoria' => trim((string) $peticion->get('idCategoria', '')),
            'partida' => trim((string) $peticion->get('partida', '')),
            'estado' => trim((string) $peticion->get('estado', '')),
        ];

        $textoBusqueda = trim((string) $peticion->get('q', ''));
        $resultadosSigma = $this->service->buscarEnCatalogoSigma($textoBusqueda);
        $totales = $this->service->totales();

        return $this->render('index', [
            'dataProvider' => $this->service->listar($filtros),
            'filtros' => $filtros,
            'categorias' => (new CategoriaService())->opciones(),
            'partidasEnUso' => $this->service->partidasEnUso(),
            'totalItems' => $totales['items'],
            'totalSigma' => $totales['sigma'],
            'textoBusqueda' => $textoBusqueda,
            'resultadosSigma' => $resultadosSigma,
            'limiteAlcanzado' => $this->service->limiteDeBusquedaAlcanzado($resultadosSigma),
            'nombresDticPorIdSigma' => $this->service->nombresDticPorIdSigma(),
        ]);
    }

    /**
     * $q: texto de la ultima busqueda en SIGMA, si se llego aqui desde
     * "Agregar". Yii llena los parametros de la accion con los de la URL
     * (?id=103&q=jack), y $q es opcional. Sirve para que "Agregar otro desde
     * SIGMA" vuelva a los mismos resultados.
     */
    public function actionView(int $id, string $q = ''): string
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
            'textoBusqueda' => trim($q),
        ]);
    }

    public function actionCreate()
    {
        $model = new SubcatalogoItem();

        if ($this->service->crear($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Ítem "' . $model->descripcion . '" agregado al subcatálogo.');

            // El formulario se envia a la misma URL (create?idSigma=...&q=...),
            // asi que el texto de la busqueda sigue disponible en el GET.
            $textoBusqueda = trim((string) Yii::$app->request->get('q', ''));

            return $this->redirect(['view', 'id' => $model->id, 'q' => $textoBusqueda !== '' ? $textoBusqueda : null]);
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
            'categorias' => (new CategoriaService())->opciones(),
            'unidades' => (new UnidadMedidaService())->opcionesPorId($model->idUnidadMedida === null || $model->idUnidadMedida === '' ? null : (int) $model->idUnidadMedida),
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
