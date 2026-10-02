<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Presupuesto;
use backend\services\PresupuestoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controlador CRUD para redes.Presupuesto (capa de Presentacion).
 *
 * Todas las reglas de negocio (editable o no, transiciones de estado,
 * nueva version, precio congelado, totales) viven en PresupuestoService.
 * Aca solo se decide que mensaje mostrar y a donde redirigir.
 */
class PresupuestoController extends Controller
{
    private PresupuestoService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new PresupuestoService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'cambiar-estado' => ['POST'],
                    'crear-nueva-version' => ['POST'],
                    'eliminar-linea' => ['POST'],
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
        $model = new Presupuesto();

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

        if (!$this->service->puedeEditarse($model)) {
            Yii::$app->session->setFlash('error', 'Un presupuesto en estado "' . $model->estadoLabel() . '" no se puede editar directamente. Debe crearse una nueva versión.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

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

    /**
     * Cambia el estado de un presupuesto, pero solo si la transicion pedida
     * es valida (ver PresupuestoService::cambiarEstado()). Cualquier otra
     * combinacion se rechaza sin tocar la base de datos.
     */
    public function actionCambiarEstado(int $id)
    {
        $model = $this->findModel($id);
        $nuevoEstado = (string) Yii::$app->request->post('estado');

        if (!$this->service->cambiarEstado($model, $nuevoEstado)) {
            Yii::$app->session->setFlash('error', 'No se puede pasar de "' . $model->estadoLabel() . '" a ese estado.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        Yii::$app->session->setFlash('success', 'Estado actualizado a "' . $model->estadoLabel() . '".');

        return $this->redirect(['view', 'id' => $model->id]);
    }

    /**
     * Crea una nueva version de un presupuesto aprobado/cancelado (ver
     * PresupuestoService::crearNuevaVersion()) y redirige directamente a la
     * pantalla de lineas de la version nueva para que el usuario pueda
     * empezar a editarla ahi mismo.
     */
    public function actionCrearNuevaVersion(int $id)
    {
        $model = $this->findModel($id);

        if ($this->service->puedeEditarse($model)) {
            Yii::$app->session->setFlash('error', 'Solo se puede crear una nueva versión de un presupuesto aprobado o cancelado.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        $nuevaVersion = $this->service->crearNuevaVersion($model);

        if ($nuevaVersion === null) {
            Yii::$app->session->setFlash('error', 'No se pudo crear la nueva versión del presupuesto.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        Yii::$app->session->setFlash('success', 'Se creó la versión #' . $nuevaVersion->numeroVersion . ' del presupuesto. Ya puede agregar o modificar líneas.');

        return $this->redirect(['detalle', 'id' => $nuevaVersion->id]);
    }

    /**
     * Pantalla principal de trabajo de un presupuesto: lista sus lineas
     * agrupadas por categoria del item (con subtotal por grupo y total
     * general), y procesa el formulario para agregar una linea nueva.
     */
    public function actionDetalle(int $id)
    {
        $presupuesto = $this->findModel($id);
        $nuevaLinea = $this->service->nuevaLinea($presupuesto);

        if (Yii::$app->request->isPost) {
            if (!$this->service->puedeEditarse($presupuesto)) {
                Yii::$app->session->setFlash('error', 'Un presupuesto en estado "' . $presupuesto->estadoLabel() . '" no se puede modificar directamente. Debe crearse una nueva versión.');

                return $this->redirect(['detalle', 'id' => $presupuesto->id]);
            }

            if ($this->service->agregarLinea($presupuesto, $nuevaLinea, Yii::$app->request->post())) {
                Yii::$app->session->setFlash('success', 'Línea agregada al presupuesto.');

                return $this->redirect(['detalle', 'id' => $presupuesto->id]);
            }
        }

        return $this->render('detalle', [
            'presupuesto' => $presupuesto,
            'nuevaLinea' => $nuevaLinea,
            'grupos' => $this->service->agruparPorCategoria($presupuesto),
        ]);
    }

    /**
     * Genera y descarga el PDF consolidado del presupuesto: encabezado
     * institucional, datos generales, lineas agrupadas por categoria y un
     * espacio de firma. Disponible en cualquier estado del presupuesto, no
     * solo aprobado -- tambien sirve para generar el PDF de un presupuesto
     * en revision.
     */
    public function actionPdf(int $id): Response
    {
        $presupuesto = $this->findModel($id);
        $grupos = $this->service->agruparPorCategoria($presupuesto);

        $html = $this->renderPartial('pdf', [
            'presupuesto' => $presupuesto,
            'grupos' => $grupos,
            'totalGeneral' => $this->service->totalGeneral($grupos),
        ]);

        $pdf = $this->service->generarPdf($presupuesto, $html);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf']
        );
    }

    public function actionEliminarLinea(int $id)
    {
        $linea = $this->service->obtenerLinea($id);

        if ($linea === null) {
            throw new NotFoundHttpException('La línea solicitada no existe.');
        }

        $presupuesto = $linea->presupuesto;

        if (!$this->service->eliminarLinea($linea)) {
            Yii::$app->session->setFlash('error', 'Un presupuesto en estado "' . $presupuesto->estadoLabel() . '" no se puede modificar directamente.');
        }

        return $this->redirect(['detalle', 'id' => $presupuesto->id]);
    }

    protected function findModel(int $id): Presupuesto
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El presupuesto solicitado no existe.');
        }

        return $model;
    }
}
