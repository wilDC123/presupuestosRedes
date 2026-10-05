<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\OrdenCambio;
use backend\models\Proyecto;
use backend\services\ListaItemsService;
use backend\services\OrdenCambioService;
use backend\services\ProyectoService;
use backend\services\SubcatalogoItemService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * "Lista de items del proyecto" (capa de Presentacion): caso de uso separado
 * de "Gestionar Proyecto", del que solo usa el id.
 *
 * La lista son items y cantidades, SIN precios. Las reglas (cuando se puede
 * editar, estados, que no se quita) viven en ListaItemsService; las ordenes
 * de cambio, en OrdenCambioService.
 */
class ListaItemsController extends Controller
{
    private ListaItemsService $service;
    private OrdenCambioService $ordenes;

    public function init(): void
    {
        parent::init();
        $this->service = new ListaItemsService();
        $this->ordenes = new OrdenCambioService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'solicitar-cambio' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * GET muestra la lista. POST la guarda completa; si se pulso "Guardar y
     * enviar a cotizacion" (accion=enviar), ademas la deja en cotizacion.
     *
     * @param int $id id del proyecto
     */
    public function actionIndex(int $id)
    {
        $proyecto = $this->findProyecto($id);
        $peticion = Yii::$app->request;
        $errores = [];
        $enviados = null;

        if ($peticion->isPost) {
            $items = [];

            if ($this->service->guardar($proyecto, $peticion->post('items', []), $errores, $items)) {
                if ($peticion->post('accion') !== 'enviar') {
                    Yii::$app->session->setFlash('success', 'Lista guardada.');
                } elseif ($this->service->enviarACotizacion($proyecto)) {
                    Yii::$app->session->setFlash('success', 'Lista guardada y enviada a cotización. Desde ahora solo se modifica con una orden de cambio.');

                    $sinCantidad = $this->service->contarSinCantidad($proyecto);

                    if ($sinCantidad > 0) {
                        Yii::$app->session->setFlash('warning', "Atención: $sinCantidad ítem(s) de la lista tienen cantidad 0.");
                    }
                } else {
                    Yii::$app->session->setFlash('error', 'La lista se guardó, pero no se envió a cotización: todavía no tiene ítems.');
                }

                return $this->redirect(['index', 'id' => $proyecto->id]);
            }

            // No se guardo: se vuelve a mostrar lo que el usuario envio.
            $enviados = $items;
        }

        return $this->render('index', [
            'proyecto' => $proyecto,
            'grupos' => $this->service->agrupar($proyecto, $enviados),
            'errores' => $errores,
            'editable' => $this->service->puedeEditarse($proyecto),
            'subcatalogo' => (new SubcatalogoItemService())->activos(),
            'ordenes' => $this->ordenes->historial($proyecto),
        ]);
    }

    /**
     * PDF de la lista GUARDADA (sin precios), para imprimir. Se abre en el
     * navegador ('inline') en vez de descargarse.
     */
    public function actionImprimir(int $id): Response
    {
        $proyecto = $this->findProyecto($id);

        $html = $this->renderPartial('pdf', [
            'proyecto' => $proyecto,
            'grupos' => $this->service->agrupar($proyecto),
        ]);

        $pdf = $this->service->generarPdf($proyecto, $html);

        return Yii::$app->response->sendContentAsFile(
            $pdf['contenido'],
            $pdf['nombreArchivo'],
            ['mimeType' => 'application/pdf', 'inline' => true]
        );
    }

    /**
     * Registra una orden de cambio y devuelve la lista a "en edicion".
     */
    public function actionSolicitarCambio(int $id)
    {
        $proyecto = $this->findProyecto($id);
        $orden = new OrdenCambio();
        $usuario = (string) (Yii::$app->user->identity->username ?? 'desconocido');

        if ($this->ordenes->solicitarCambioLista($proyecto, $orden, Yii::$app->request->post(), $usuario)) {
            Yii::$app->session->setFlash('success', 'Orden de cambio registrada. La lista volvió a edición.');
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $orden->getFirstErrors()));
        }

        return $this->redirect(['index', 'id' => $proyecto->id]);
    }

    protected function findProyecto(int $id): Proyecto
    {
        $proyecto = (new ProyectoService())->obtener($id);

        if ($proyecto === null) {
            throw new NotFoundHttpException('El proyecto solicitado no existe.');
        }

        return $proyecto;
    }
}
