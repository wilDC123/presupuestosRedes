<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\ElaborarPresupuestoService;
use backend\services\PresupuestoService;
use backend\services\ProyectoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Paso "Presupuesto" del proyecto (capa de Presentacion): el presupuesto
 * como documento vivo. Una sola pantalla con todos los items de la lista,
 * los precios de cada proveedor y el precio elegido.
 *
 * Las reglas viven en ElaborarPresupuestoService. Aca solo se decide que
 * mostrar, que mensaje dar y que responder al navegador.
 */
class ElaborarPresupuestoController extends Controller
{
    private ElaborarPresupuestoService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new ElaborarPresupuestoService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'elegir' => ['POST'],
                    'adjudicar' => ['POST'],
                    'enviar' => ['POST'],
                    'estado' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Al entrar: si el proyecto no tiene presupuesto se crea, y cada item
     * sin linea recibe el menor precio vigente (ver completarSinLinea()).
     */
    public function actionIndex(int $idProyecto): string
    {
        $proyecto = $this->findProyecto($idProyecto);
        $presupuesto = $this->service->delProyecto($proyecto);
        $this->service->completarSinLinea($presupuesto);

        return $this->render('index', $this->datosPantalla($proyecto, $presupuesto));
    }

    /**
     * Guardado AJAX de UN precio (lo llama web/js/elaborar-presupuesto.js).
     * Responde JSON: {ok, error} y, si se guardo, los totales ya
     * formateados para que la pantalla los reemplace.
     */
    public function actionElegir(int $idProyecto): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $proyecto = $this->findProyecto($idProyecto);
        $presupuesto = $this->service->delProyecto($proyecto);
        $peticion = Yii::$app->request;
        $idProyectoItem = (int) $peticion->post('idProyectoItem');

        $error = $this->service->elegir(
            $presupuesto,
            $idProyectoItem,
            (string) $peticion->post('opcion', ''),
            (string) $peticion->post('precio', ''),
            (string) $peticion->post('justificacion', '')
        );

        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        return ['ok' => true] + $this->totales($presupuesto, $idProyectoItem);
    }

    /**
     * Atajos "Menor precio por item" y "Todo a un proveedor". Es un
     * formulario normal (recarga la pantalla): cambia muchas filas a la vez.
     */
    public function actionAdjudicar(int $idProyecto)
    {
        $proyecto = $this->findProyecto($idProyecto);
        $presupuesto = $this->service->delProyecto($proyecto);
        $peticion = Yii::$app->request;
        $idProveedor = (string) $peticion->post('idProveedor', '');
        $cambiadas = 0;
        $sinCotizar = 0;

        $error = $this->service->adjudicar(
            $presupuesto,
            (string) $peticion->post('modo', ''),
            ctype_digit($idProveedor) ? (int) $idProveedor : null,
            (string) $peticion->post('justificacion', ''),
            $cambiadas,
            $sinCotizar
        );

        if ($error !== null) {
            Yii::$app->session->setFlash('error', $error);
        } else {
            $mensaje = $cambiadas === 1 ? 'Se cambió el precio de 1 ítem.' : "Se cambió el precio de $cambiadas ítems.";
            if ($sinCotizar > 0) {
                $mensaje .= " $sinCotizar ítem(s) no los cotizó ese proveedor y quedaron como estaban.";
            }
            Yii::$app->session->setFlash('success', $mensaje);
        }

        return $this->redirect(['index', 'idProyecto' => $proyecto->id]);
    }

    /**
     * "Enviar a revision" con la cabecera (numero, oficio, area, fecha). Si
     * falta algo, vuelve a la pantalla con el panel abierto y los mensajes.
     */
    public function actionEnviar(int $idProyecto)
    {
        $proyecto = $this->findProyecto($idProyecto);
        $presupuesto = $this->service->delProyecto($proyecto);
        $errores = [];

        if ($this->service->enviarARevision($presupuesto, (array) Yii::$app->request->post('Presupuesto', []), $errores)) {
            Yii::$app->session->setFlash('success', 'Presupuesto enviado a revisión.');
            $this->avisarVencidas($presupuesto);

            return $this->redirect(['index', 'idProyecto' => $proyecto->id]);
        }

        // Con "+" gana la clave de la IZQUIERDA: los errores van primero.
        return $this->render('index', ['erroresEnvio' => $errores] + $this->datosPantalla($proyecto, $presupuesto));
    }

    /**
     * Los demas cambios de estado, desde la misma pantalla: observar,
     * aprobar o cancelar (en revision) y volver a "en proceso" (observado).
     * "En revision" solo se alcanza con actionEnviar(), que exige la
     * cabecera y todos los precios.
     */
    public function actionEstado(int $idProyecto)
    {
        $proyecto = $this->findProyecto($idProyecto);
        $presupuesto = $this->service->delProyecto($proyecto);
        $nuevoEstado = (string) Yii::$app->request->post('estado');

        if ($nuevoEstado === Presupuesto::ESTADO_EN_REVISION || !(new PresupuestoService())->cambiarEstado($presupuesto, $nuevoEstado)) {
            Yii::$app->session->setFlash('error', 'No se puede pasar de "' . $presupuesto->estadoLabel() . '" a ese estado.');

            return $this->redirect(['index', 'idProyecto' => $proyecto->id]);
        }

        Yii::$app->session->setFlash('success', 'Estado actualizado a "' . $presupuesto->estadoLabel() . '".');

        // Aviso que NO bloquea: el presupuesto ya quedo aprobado.
        if ($nuevoEstado === Presupuesto::ESTADO_APROBADO) {
            $this->avisarVencidas($presupuesto);
        }

        return $this->redirect(['index', 'idProyecto' => $proyecto->id]);
    }

    private function avisarVencidas(Presupuesto $presupuesto): void
    {
        $vencidas = (new PresupuestoService())->lineasConCotizacionVencida($presupuesto);

        if ($vencidas !== []) {
            $nombres = array_map(static fn ($linea) => $linea->subcatalogoItem->descripcion ?? '?', $vencidas);
            Yii::$app->session->setFlash('warning', 'Atención: ' . count($vencidas) . ' línea(s) usan una cotización vencida: ' . implode(', ', $nombres) . '.');
        }
    }

    /**
     * Variables de la vista index.php.
     */
    private function datosPantalla(Proyecto $proyecto, Presupuesto $presupuesto): array
    {
        return [
            'erroresEnvio' => [],
            'proyecto' => $proyecto,
            'presupuesto' => $presupuesto,
            'pasos' => (new ProyectoService())->pasos($proyecto),
            'tabla' => $this->service->tabla($presupuesto),
            'control' => $this->service->control($presupuesto),
            'resumenPartidas' => (new PresupuestoService())->resumenPorPartida($presupuesto),
        ];
    }

    /**
     * Lo que la pantalla reemplaza despues de guardar un precio: el total de
     * la fila, los subtotales, el total general, el resumen por partida (como
     * HTML, con la misma vista parcial) y el control antes de enviar.
     */
    private function totales(Presupuesto $presupuesto, int $idProyectoItem): array
    {
        $formato = Yii::$app->formatter;
        $tabla = $this->service->tabla($presupuesto);
        $subtotales = [];
        $fila = null;

        foreach ($tabla['grupos'] as $idCategoria => $grupo) {
            $subtotales[$idCategoria] = $formato->asDecimal($grupo['subtotal'], 2);

            foreach ($grupo['filas'] as $f) {
                if ((int) $f['item']->id === $idProyectoItem) {
                    $fila = $f;
                }
            }
        }

        $linea = $fila['linea'] ?? null;
        $control = $this->service->control($presupuesto);

        return [
            'fila' => [
                'total' => $linea === null ? '—' : $formato->asDecimal($linea->precioTotal, 2),
                'opcion' => $linea === null ? '' : ($linea->idCotizacionDetalle === null ? 'manual' : (string) $linea->idCotizacionDetalle),
            ],
            'subtotales' => $subtotales,
            'total' => $formato->asDecimal($tabla['total'], 2),
            'conPrecio' => $tabla['conPrecio'],
            'partidasHtml' => $this->renderPartial('/presupuesto/_resumen-partidas', [
                'resumenPartidas' => (new PresupuestoService())->resumenPorPartida($presupuesto),
            ]),
            'controlHtml' => $this->renderPartial('_control', ['control' => $control, 'presupuesto' => $presupuesto]),
        ];
    }

    private function findProyecto(int $id): Proyecto
    {
        $proyecto = (new ProyectoService())->obtener($id);

        if ($proyecto === null) {
            throw new NotFoundHttpException('El proyecto solicitado no existe.');
        }

        return $proyecto;
    }
}
