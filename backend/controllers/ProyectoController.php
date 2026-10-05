<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Proyecto;
use backend\services\ParametrosProyectoService;
use backend\services\ProyectoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador de "Gestionar Proyecto" (capa de Presentacion): solo los datos
 * basicos del proyecto y su pantalla central de navegacion.
 *
 * No consulta la base de datos ni conoce de donde salen facultades, edificios
 * o bloques: delega en ProyectoService y ParametrosProyectoService.
 */
class ProyectoController extends Controller
{
    private ProyectoService $service;
    private ParametrosProyectoService $parametros;

    /**
     * init() es el "constructor" recomendado en Yii2 para componentes: se
     * ejecuta una vez al crear el controlador, antes de cualquier accion.
     *
     * Yii::$container->get() arma el servicio de parametros con la fuente
     * configurada en backend/config/main.php ('container').
     */
    public function init(): void
    {
        parent::init();
        $this->parametros = Yii::$container->get(ParametrosProyectoService::class);
        $this->service = new ProyectoService($this->parametros);
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
            'tipos' => $this->parametros->opcionesTipo(),
        ]);
    }

    /**
     * Pantalla central del proyecto: sus datos, en que paso va y los accesos
     * a cada paso.
     */
    public function actionView(int $id): string
    {
        $model = $this->findModel($id);

        return $this->render('view', [
            'model' => $model,
            'pasos' => $this->service->pasos($model),
            'nombreTipo' => $this->parametros->nombreTipo($model->tipo),
            'monto' => $this->service->montoPresupuesto($model),
            'puedeEditarse' => $this->service->puedeEditarse($model),
            'estaAprobado' => $this->service->estaAprobado($model),
        ]);
    }

    /**
     * ?idAnterior= llega desde el boton "Crear fase siguiente" de un
     * proyecto aprobado: el formulario se abre ya enlazado a el.
     */
    public function actionCreate(?int $idAnterior = null)
    {
        $model = $this->service->nuevo($idAnterior);

        if ($this->service->crear($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Proyecto ' . $model->codigo . ' creado.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', $this->datosFormulario($model));
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if (!$this->service->puedeEditarse($model)) {
            Yii::$app->session->setFlash('error', 'Este proyecto ya tiene su presupuesto ' . mb_strtolower($model->presupuesto->estadoLabel()) . ' y no se modifica. Una ampliación se registra como un proyecto nuevo de fase siguiente.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        if ($this->service->actualizar($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Datos del proyecto actualizados.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        // En GET se muestran los bloques y proyectistas guardados; si el
        // POST fallo la validacion, se conserva lo que el usuario eligio.
        if (!Yii::$app->request->isPost) {
            $this->service->cargarSeleccion($model);
        }

        return $this->render('update', $this->datosFormulario($model));
    }

    public function actionDelete(int $id)
    {
        $model = $this->findModel($id);

        if (!$this->service->eliminar($model)) {
            Yii::$app->session->setFlash('error', 'No se puede eliminar el proyecto porque ya tiene presupuesto, cómputos métricos o una fase siguiente que depende de él.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->redirect(['index']);
    }

    /**
     * Todo lo que necesita _form.php ademas del proyecto. Se arma aqui para
     * que la vista no consulte la base ni tenga listas escritas a mano.
     */
    private function datosFormulario(Proyecto $model): array
    {
        return [
            'model' => $model,
            'tipos' => $this->parametros->opcionesTipo(),
            'facultades' => $this->parametros->opcionesFacultad(),
            'edificios' => $this->parametros->opcionesEdificio($model->codigoFacultad),
            'bloques' => $this->parametros->opcionesBloque($model->codigoFacultad, $model->codigoEdificio),
            'proyectistas' => $this->parametros->proyectistas(),
            'arbolUbicacion' => $this->parametros->arbolUbicacion(),
            'proyectosAprobados' => $this->service->proyectosAprobados($model->isNewRecord ? null : $model->id),
        ];
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
