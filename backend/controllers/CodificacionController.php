<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\services\CodificacionService;
use Yii;
use yii\web\Controller;

/**
 * Codificacion de documentos (capa de Presentacion): el encargado fija desde
 * que numero siguen los codigos de proyectos (RD-PI-PT), presupuestos
 * (RD-PI-PR) y especificaciones tecnicas (RD-PI-ET), segun el lineamiento de
 * la DTIC RD-GD-LI-001. Las reglas viven en CodificacionService.
 */
class CodificacionController extends Controller
{
    private CodificacionService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new CodificacionService();
    }

    public function actionIndex()
    {
        $correlativos = $this->service->correlativos();

        if (Yii::$app->request->isPost && $this->service->guardar($correlativos, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Codificación de documentos guardada.');

            return $this->redirect(['index']);
        }

        $ultimos = [];
        foreach (array_keys($correlativos) as $tipo) {
            $ultimos[$tipo] = $this->service->ultimoUsado($tipo);
        }

        return $this->render('index', [
            'correlativos' => $correlativos,
            'ultimos' => $ultimos,
        ]);
    }
}
