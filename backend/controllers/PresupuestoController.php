<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Presupuesto;
use backend\services\ElaborarPresupuestoService;
use backend\services\PresupuestoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Menu "Presupuestos" (capa de Presentacion): el listado de los
 * presupuestos de todos los proyectos y el PDF.
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

        if (!$presupuesto->esBorrador()) {
            Yii::$app->session->setFlash('error', 'Solo se elimina un presupuesto "En proceso".');
        } else {
            $this->service->eliminar($presupuesto);
            Yii::$app->session->setFlash('success', 'Presupuesto eliminado. Al volver a entrar al paso Presupuesto del proyecto se crea otro, con los menores precios.');
        }

        return $this->redirect(['index']);
    }

    /**
     * Genera y descarga el PDF consolidado del presupuesto: encabezado
     * institucional, datos generales, lineas agrupadas por categoria, resumen
     * por partida y, en anexo, el cuadro comparativo con las justificaciones.
     * Disponible en cualquier estado; si no esta aprobado lleva la marca
     * "BORRADOR".
     */
    public function actionPdf(int $id): Response
    {
        $presupuesto = $this->findModel($id);
        $grupos = $this->service->agruparPorCategoria($presupuesto);

        $html = $this->renderPartial('pdf', [
            'presupuesto' => $presupuesto,
            'grupos' => $grupos,
            'totalGeneral' => $this->service->totalGeneral($grupos),
            'resumenPartidas' => $this->service->resumenPorPartida($presupuesto),
            // Anexo: cuadro comparativo de cotizaciones y justificaciones.
            'comparativo' => (new ElaborarPresupuestoService())->tabla($presupuesto),
        ]);

        $pdf = $this->service->generarPdf($presupuesto, $html);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf']
        );
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
