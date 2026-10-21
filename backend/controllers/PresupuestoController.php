<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\ConfiguracionPresupuesto;
use backend\models\Presupuesto;
use backend\services\CartaService;
use backend\services\PresupuestoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Menu "Presupuestos" (capa de Presentacion): el listado de los
 * presupuestos de todos los proyectos, el PDF y su configuracion (nota,
 * firmas y banda de pie).
 *
 * El presupuesto se ELABORA en el paso "Presupuesto" de cada proyecto
 * (ElaborarPresupuestoController). Las rutas que antes servian para eso
 * (ver, lineas, armar, crear, editar, cambiar estado) redirigen ahi, para
 * que ningun enlace guardado quede roto.
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
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        return $this->render('index', [
            'dataProvider' => $this->service->listar(),
            'totales' => $this->service->totalesPorPresupuesto(),
        ]);
    }

    /**
     * Rutas anteriores (presupuesto/view, detalle, update, cambiar-estado):
     * llevan al paso Presupuesto del proyecto.
     */
    public function actionView(int $id): Response
    {
        return $this->alProyecto($this->findModel($id)->idProyecto);
    }

    public function actionDetalle(int $id): Response
    {
        return $this->actionView($id);
    }

    public function actionUpdate(int $id): Response
    {
        return $this->actionView($id);
    }

    public function actionCambiarEstado(int $id): Response
    {
        return $this->actionView($id);
    }

    /**
     * Rutas anteriores con ?idProyecto= (presupuesto/create y armar).
     */
    public function actionCreate(?int $idProyecto = null): Response
    {
        if ($idProyecto === null) {
            Yii::$app->session->setFlash('info', 'El presupuesto se elabora desde cada proyecto: abra el proyecto y entre al paso "Presupuesto".');

            return $this->redirect(['/proyecto/index']);
        }

        return $this->alProyecto($idProyecto);
    }

    public function actionArmar(int $idProyecto): Response
    {
        return $this->alProyecto($idProyecto);
    }

    public function actionDelete(int $id): Response
    {
        $presupuesto = $this->findModel($id);

        // La regla (solo "En proceso") vive en PresupuestoService::eliminar().
        if (!$this->service->eliminar($presupuesto)) {
            Yii::$app->session->setFlash('error', 'Solo se elimina un presupuesto "En proceso".');
        } else {
            Yii::$app->session->setFlash('success', 'Presupuesto eliminado. Al volver a entrar al paso Presupuesto del proyecto se crea otro, con los menores precios.');
        }

        return $this->redirect(['index']);
    }

    /**
     * Genera y descarga el PDF consolidado del presupuesto: encabezado
     * institucional, datos generales y las lineas en una tabla numerada,
     * como la solicitud de cotizacion. Disponible en cualquier estado; si
     * no esta aprobado lleva la marca "BORRADOR".
     */
    public function actionPdf(int $id): Response
    {
        $presupuesto = $this->findModel($id);
        $grupos = $this->service->agruparPorCategoria($presupuesto);

        $html = $this->renderPartial('pdf', [
            'presupuesto' => $presupuesto,
            'grupos' => $grupos,
            'totalGeneral' => $this->service->totalGeneral($grupos),
            'configuracion' => ConfiguracionPresupuesto::obtener(),
            // "DTIC Of. Nº 598/2026", con el mismo formato que la carta de entrega.
            'oficio' => (new CartaService())->formatearOficio((string) $presupuesto->oficioDtic, (string) $presupuesto->fecha),
        ]);

        $pdf = $this->service->generarPdf($presupuesto, $html);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf']
        );
    }

    /**
     * Reporte de apoyo (PDF): cada linea del presupuesto con el item SIGMA al
     * que apunta, para saber que item del catalogo es cada uno. No es el
     * documento oficial: no lleva firmas.
     */
    public function actionPdfSigma(int $id): Response
    {
        $presupuesto = $this->findModel($id);
        $grupos = $this->service->agruparPorCategoria($presupuesto);

        $html = $this->renderPartial('pdf-sigma', [
            'presupuesto' => $presupuesto,
            'grupos' => $grupos,
        ]);

        $pdf = $this->service->generarPdfSigma($presupuesto, $html);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf']
        );
    }

    /**
     * Lo que lleva el PDF despues del total: nota, firmas y banda de pie.
     * Vale para todos los presupuestos, tambien al reimprimir los anteriores.
     *
     * @param int|null $proyecto id del proyecto desde el que se entro, solo
     *                           para el boton "volver"
     */
    public function actionConfiguracion(?int $proyecto = null)
    {
        $configuracion = ConfiguracionPresupuesto::obtener();

        if (Yii::$app->request->isPost && $this->service->guardarConfiguracion($configuracion, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Configuración del PDF del presupuesto guardada.');

            return $this->redirect(['configuracion', 'proyecto' => $proyecto]);
        }

        return $this->render('configuracion', [
            'configuracion' => $configuracion,
            'proyecto' => $proyecto,
        ]);
    }

    private function alProyecto(int $idProyecto): Response
    {
        return $this->redirect(['/elaborar-presupuesto/index', 'idProyecto' => $idProyecto]);
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
