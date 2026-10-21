<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\ConfiguracionCarta;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\CartaService;
use backend\services\ProyectoService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Carta de entrega del presupuesto (capa de Presentacion): escribirla,
 * imprimirla y editar sus textos por defecto. Se entra desde el paso
 * Presupuesto del proyecto. Las reglas viven en CartaService.
 */
class CartaController extends Controller
{
    private CartaService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new CartaService();
    }

    /**
     * Formulario de la carta: la guardada o, si aun no hay, una nueva ya
     * llena. "Guardar" (POST) la crea o la actualiza.
     */
    public function actionIndex(int $idProyecto)
    {
        $proyecto = $this->findProyecto($idProyecto);
        $presupuesto = $this->findPresupuesto($proyecto);
        $carta = $this->service->delPresupuesto($presupuesto);

        if (Yii::$app->request->isPost && $this->service->guardar($carta, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Carta guardada.');

            return $this->redirect(['index', 'idProyecto' => $proyecto->id]);
        }

        return $this->render('index', [
            'proyecto' => $proyecto,
            'pasos' => (new ProyectoService())->pasos($proyecto),
            'presupuesto' => $presupuesto,
            'carta' => $carta,
            // Lo que falta para imprimir (se recalcula con la carta recien guardada).
            'falta' => $this->service->faltaParaImprimir($presupuesto),
        ]);
    }

    /**
     * PDF de la carta guardada, en hoja oficio, con el membrete de fondo.
     */
    public function actionImprimir(int $idProyecto): Response
    {
        $proyecto = $this->findProyecto($idProyecto);
        $presupuesto = $this->findPresupuesto($proyecto);
        $falta = $this->service->faltaParaImprimir($presupuesto);

        if ($falta !== []) {
            Yii::$app->session->setFlash('error', 'Todavía no se puede imprimir la carta: ' . implode(' ', $falta));

            return $this->redirect(['index', 'idProyecto' => $proyecto->id]);
        }

        $carta = $presupuesto->carta;
        $configuracion = ConfiguracionCarta::obtener();

        $html = $this->renderPartial('pdf', [
            'carta' => $carta,
            'presupuesto' => $presupuesto,
            'service' => $this->service,
            'configuracion' => $configuracion,
            'fondo' => Yii::getAlias('@backend/web/img/fondo-carta.png'),
        ]);

        $pdf = $this->service->generarPdf($presupuesto, $html);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf', 'inline' => true]
        );
    }

    /**
     * Textos por defecto de la carta. Solo se usan al llenar una carta
     * nueva: las ya escritas guardan su propio texto.
     *
     * @param int|null $proyecto id del proyecto desde el que se entro, solo
     *                           para el boton "volver"
     */
    public function actionConfiguracion(?int $proyecto = null)
    {
        $configuracion = ConfiguracionCarta::obtener();

        if (Yii::$app->request->isPost && $this->service->guardarConfiguracion($configuracion, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Configuración de la carta guardada.');

            return $this->redirect(['configuracion', 'proyecto' => $proyecto]);
        }

        return $this->render('configuracion', [
            'configuracion' => $configuracion,
            'proyecto' => $proyecto,
        ]);
    }

    private function findProyecto(int $id): Proyecto
    {
        $proyecto = (new ProyectoService())->obtener($id);

        if ($proyecto === null) {
            throw new NotFoundHttpException('El proyecto solicitado no existe.');
        }

        return $proyecto;
    }

    /**
     * El presupuesto se crea al entrar al paso Presupuesto; la carta se
     * escribe desde ahi, asi que sin presupuesto no hay carta.
     */
    private function findPresupuesto(Proyecto $proyecto): Presupuesto
    {
        if ($proyecto->presupuesto === null) {
            throw new NotFoundHttpException('El proyecto todavía no tiene presupuesto: entre primero al paso Presupuesto.');
        }

        return $proyecto->presupuesto;
    }
}
