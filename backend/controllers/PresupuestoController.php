<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\components\PresupuestoPdfGenerator;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controlador CRUD para redes.Presupuesto.
 */
class PresupuestoController extends Controller
{
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
        $dataProvider = new ActiveDataProvider([
            'query' => Presupuesto::find()->with('proyecto')->orderBy(['id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
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

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if (!$model->puedeEditarse()) {
            Yii::$app->session->setFlash('error', 'Un presupuesto en estado "' . $model->estadoLabel() . '" no se puede editar directamente. Debe crearse una nueva versión.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete(int $id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Cambia el estado de un presupuesto, pero solo si la transicion pedida
     * esta en la lista de transiciones validas del modelo (ver
     * Presupuesto::transicionesPermitidas()). Cualquier otra combinacion se
     * rechaza sin tocar la base de datos.
     */
    public function actionCambiarEstado(int $id)
    {
        $model = $this->findModel($id);
        $nuevoEstado = (string) Yii::$app->request->post('estado');

        if (!$model->puedeTransicionarA($nuevoEstado)) {
            Yii::$app->session->setFlash('error', 'No se puede pasar de "' . $model->estadoLabel() . '" a ese estado.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        $model->estado = $nuevoEstado;
        $model->save(false);

        Yii::$app->session->setFlash('success', 'Estado actualizado a "' . $model->estadoLabel() . '".');

        return $this->redirect(['view', 'id' => $model->id]);
    }

    /**
     * Crea una nueva version de un presupuesto aprobado/cancelado (ver
     * Presupuesto::crearNuevaVersion()) y redirige directamente a la
     * pantalla de lineas de la version nueva para que el usuario pueda
     * empezar a editarla ahi mismo.
     */
    public function actionCrearNuevaVersion(int $id)
    {
        $model = $this->findModel($id);

        if ($model->puedeEditarse()) {
            Yii::$app->session->setFlash('error', 'Solo se puede crear una nueva versión de un presupuesto aprobado o cancelado.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        $nuevaVersion = Presupuesto::crearNuevaVersion($model->id);

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

        $nuevaLinea = new PresupuestoDetalle();
        $nuevaLinea->idPresupuesto = $presupuesto->id;

        if (Yii::$app->request->isPost) {
            if (!$presupuesto->puedeEditarse()) {
                Yii::$app->session->setFlash('error', 'Un presupuesto en estado "' . $presupuesto->estadoLabel() . '" no se puede modificar directamente. Debe crearse una nueva versión.');

                return $this->redirect(['detalle', 'id' => $presupuesto->id]);
            }

            if ($nuevaLinea->load(Yii::$app->request->post())) {
                $nuevaLinea->idPresupuesto = $presupuesto->id;

                if ($nuevaLinea->save()) {
                    Yii::$app->session->setFlash('success', 'Línea agregada al presupuesto.');

                    return $this->redirect(['detalle', 'id' => $presupuesto->id]);
                }
            }
        }

        return $this->render('detalle', [
            'presupuesto' => $presupuesto,
            'nuevaLinea' => $nuevaLinea,
            'grupos' => $this->agruparPorCategoria($presupuesto),
        ]);
    }

    /**
     * Genera y descarga el PDF consolidado del presupuesto: encabezado
     * institucional, datos generales, lineas agrupadas por categoria (misma
     * agrupacion que actionDetalle(), via agruparPorCategoria()) y un
     * espacio de firma. Disponible en cualquier estado del presupuesto, no
     * solo aprobado -- tambien sirve para generar el PDF de un presupuesto
     * en revision.
     */
    public function actionPdf(int $id): Response
    {
        $presupuesto = $this->findModel($id);
        $grupos = $this->agruparPorCategoria($presupuesto);
        $totalGeneral = array_sum(array_column($grupos, 'subtotal'));

        $html = $this->renderPartial('pdf', [
            'presupuesto' => $presupuesto,
            'grupos' => $grupos,
            'totalGeneral' => $totalGeneral,
        ]);

        $generador = new PresupuestoPdfGenerator();
        $contenidoPdf = $generador->generarDesdeHtml($html);

        return Yii::$app->response->sendContentAsFile(
            $contenidoPdf,
            $generador->nombreArchivo($presupuesto),
            ['mimeType' => 'application/pdf']
        );
    }

    public function actionEliminarLinea(int $id)
    {
        $linea = PresupuestoDetalle::findOne($id);

        if ($linea === null) {
            throw new NotFoundHttpException('La línea solicitada no existe.');
        }

        $presupuesto = $linea->presupuesto;

        if (!$presupuesto->puedeEditarse()) {
            Yii::$app->session->setFlash('error', 'Un presupuesto en estado "' . $presupuesto->estadoLabel() . '" no se puede modificar directamente.');

            return $this->redirect(['detalle', 'id' => $presupuesto->id]);
        }

        $linea->delete();

        return $this->redirect(['detalle', 'id' => $presupuesto->id]);
    }

    /**
     * Agrupa las lineas del presupuesto por el nombre de la categoria de su
     * item de subcatalogo. Devuelve un arreglo:
     *   ['Cable redes' => ['lineas' => PresupuestoDetalle[], 'subtotal' => float], ...]
     * ordenado segun el campo "orden" de Categoria, mas un elemento especial
     * '__total__' con la suma de todos los subtotales.
     */
    private function agruparPorCategoria(Presupuesto $presupuesto): array
    {
        $lineas = $presupuesto->getPresupuestoDetalles()
            ->with(['subcatalogoItem.categoria'])
            ->all();

        $grupos = [];

        foreach ($lineas as $linea) {
            $categoria = $linea->subcatalogoItem->categoria ?? null;
            $nombreCategoria = $categoria->nombre ?? 'Sin categoría';
            $ordenCategoria = $categoria->orden ?? 999;

            if (!isset($grupos[$nombreCategoria])) {
                $grupos[$nombreCategoria] = [
                    'orden' => $ordenCategoria,
                    'lineas' => [],
                    'subtotal' => 0.0,
                ];
            }

            $grupos[$nombreCategoria]['lineas'][] = $linea;
            $grupos[$nombreCategoria]['subtotal'] += (float) $linea->precioTotal;
        }

        uasort($grupos, static fn ($a, $b) => $a['orden'] <=> $b['orden']);

        return $grupos;
    }

    protected function findModel(int $id): Presupuesto
    {
        $model = Presupuesto::findOne($id);

        if ($model === null) {
            throw new NotFoundHttpException('El presupuesto solicitado no existe.');
        }

        return $model;
    }
}
