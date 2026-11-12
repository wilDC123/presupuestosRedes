<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\ConfiguracionPresupuesto;
use backend\models\Presupuesto;
use backend\models\Proceso;
use backend\services\PresupuestoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Menu "Presupuestos" (capa de Presentacion): el listado de los
 * presupuestos de todos los proyectos, el PDF y su configuracion (nota y
 * firmas).
 *
 * El presupuesto se ELABORA en el paso "Presupuesto" de cada proyecto
 * (ElaborarPresupuestoController). Las rutas que antes servian para eso (ver,
 * lineas, armar, crear, editar, cambiar estado) solo redirigian ahi; se
 * quitaron el 2026-11-12 (auditoria, hallazgo 19), antes de instalar en la
 * DTIC: nadie tiene enlaces guardados a ellas.
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
     * Genera el PDF consolidado del presupuesto y lo abre en el navegador
     * ('inline', como los demas impresos) en vez de descargarlo: encabezado
     * institucional, datos generales y las lineas en una tabla numerada,
     * como la solicitud de cotizacion. Disponible en cualquier estado; si
     * no esta aprobado lleva la marca "BORRADOR".
     */
    public function actionPdf(int $id): Response
    {
        $presupuesto = $this->findModel($id);
        $grupos = $this->service->ubicarManoDeObra($presupuesto, $this->service->agruparPorCategoria($presupuesto));

        $html = $this->renderPartial('pdf', [
            'presupuesto' => $presupuesto,
            'grupos' => $grupos,
            'totalGeneral' => $this->service->totalGeneral($grupos),
            'configuracion' => ConfiguracionPresupuesto::obtener(),
            'proceso' => Proceso::nombreDe($presupuesto->codigoProceso()),
        ]);

        $pdf = $this->service->generarPdf($presupuesto, $html);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf', 'inline' => true]
        );
    }

    /**
     * Reporte de apoyo (PDF): cada linea del presupuesto con el item SIGMA al
     * que apunta, para saber que item del catalogo es cada uno; se abre en el
     * navegador ('inline'). No es el documento oficial: no lleva firmas.
     */
    public function actionPdfSigma(int $id): Response
    {
        $presupuesto = $this->findModel($id);
        $grupos = $this->service->agruparPorCategoria($presupuesto);

        $html = $this->renderPartial('pdf-sigma', [
            'presupuesto' => $presupuesto,
            'grupos' => $grupos,
            'resumen' => $this->service->resumenSigma($grupos),
        ]);

        $pdf = $this->service->generarPdfSigma($presupuesto, $html);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf', 'inline' => true]
        );
    }

    /**
     * Lo que lleva el PDF despues del total: nota y firmas.
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

    protected function findModel(int $id): Presupuesto
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El presupuesto solicitado no existe.');
        }

        return $model;
    }
}
