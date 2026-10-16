<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Presupuesto;
use backend\services\ListaItemsService;
use backend\services\PresupuestoService;
use backend\services\ProyectoService;
use backend\services\UnidadMedidaService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controlador CRUD para redes.Presupuesto (capa de Presentacion).
 *
 * Todas las reglas de negocio (editable o no, transiciones de estado,
 * precio congelado, totales) viven en PresupuestoService.
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
        $model = $this->findModel($id);

        return $this->render('view', [
            'model' => $model,
            'resumenPartidas' => $this->service->resumenPorPartida($model),
        ]);
    }

    /**
     * ?idProyecto= llega desde "Iniciar presupuesto" en la pantalla del
     * proyecto: el formulario se abre con ese proyecto ya elegido.
     */
    public function actionCreate(?int $idProyecto = null)
    {
        $model = new Presupuesto();
        $model->idProyecto = $idProyecto;

        if ($this->service->crear($model, Yii::$app->request->post())) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
            // Solo proyectos sin presupuesto: cada proyecto tiene uno.
            'proyectos' => (new ProyectoService())->opcionesParaPresupuesto(),
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if (!$this->service->puedeEditarse($model)) {
            Yii::$app->session->setFlash('error', 'Un presupuesto en estado "' . $model->estadoLabel() . '" no se puede editar. Una ampliación se registra como un proyecto nuevo de fase siguiente.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        if ($this->service->actualizar($model, Yii::$app->request->post())) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
            'proyectos' => (new ProyectoService())->opcionesParaPresupuesto($model->idProyecto),
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

        // "En revision" exige la cabecera y todos los precios: solo se envia
        // desde el paso Presupuesto del proyecto.
        if ($nuevoEstado === Presupuesto::ESTADO_EN_REVISION) {
            Yii::$app->session->setFlash('error', 'Para enviar a revisión complete la cabecera en el paso Presupuesto del proyecto.');

            return $this->redirect(['/elaborar-presupuesto/index', 'idProyecto' => $model->idProyecto]);
        }

        if (!$this->service->cambiarEstado($model, $nuevoEstado)) {
            Yii::$app->session->setFlash('error', 'No se puede pasar de "' . $model->estadoLabel() . '" a ese estado.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        Yii::$app->session->setFlash('success', 'Estado actualizado a "' . $model->estadoLabel() . '".');

        // Advertencia que NO bloquea: el presupuesto ya quedo aprobado.
        if ($nuevoEstado === Presupuesto::ESTADO_APROBADO) {
            $vencidas = $this->service->lineasConCotizacionVencida($model);

            if ($vencidas !== []) {
                $nombres = array_map(static fn ($linea) => $linea->subcatalogoItem->descripcion ?? '?', $vencidas);
                Yii::$app->session->setFlash('warning', 'Atención: ' . count($vencidas) . ' línea(s) usan una cotización vencida: ' . implode(', ', $nombres) . '.');
            }
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }

    /**
     * Pantalla principal de trabajo de un presupuesto: lista sus lineas
     * agrupadas por categoria del item (con subtotal por grupo y total
     * general), los items de la lista del proyecto que faltan presupuestar,
     * y procesa el formulario "Presupuestar item".
     *
     * ?idProyectoItem= llega desde el boton "Presupuestar" de un item de la
     * lista: el formulario se abre con ese item ya cargado.
     */
    public function actionDetalle(int $id, ?int $idProyectoItem = null)
    {
        $presupuesto = $this->findModel($id);
        $nuevaLinea = $this->service->nuevaLinea($presupuesto, $idProyectoItem);

        if (Yii::$app->request->isPost) {
            if (!$this->service->puedeEditarse($presupuesto)) {
                Yii::$app->session->setFlash('error', 'Un presupuesto en estado "' . $presupuesto->estadoLabel() . '" no se puede modificar. Una ampliación se registra como un proyecto nuevo de fase siguiente.');

                return $this->redirect(['detalle', 'id' => $presupuesto->id]);
            }

            if ($this->service->agregarLinea($presupuesto, $nuevaLinea, Yii::$app->request->post())) {
                Yii::$app->session->setFlash('success', 'Línea agregada al presupuesto.');

                if ($this->service->lineaUsaCotizacionVencida($nuevaLinea)) {
                    Yii::$app->session->setFlash('warning', 'Atención: la cotización elegida para esta línea ya está vencida. La línea se agregó igual, con el precio de esa cotización.');
                }

                return $this->redirect(['detalle', 'id' => $presupuesto->id]);
            }
        }

        return $this->render('detalle', [
            'presupuesto' => $presupuesto,
            'nuevaLinea' => $nuevaLinea,
            'pendientes' => (new ListaItemsService())->pendientesDePresupuestar($presupuesto->proyecto),
            // Solo cotizaciones de ESTA fila de la lista (y las sueltas del mismo item).
            'cotizaciones' => empty($nuevaLinea->idSubcatalogo) ? [] : $this->service->opcionesCotizacion(
                (int) $nuevaLinea->idSubcatalogo,
                empty($nuevaLinea->idProyectoItem) ? null : (int) $nuevaLinea->idProyectoItem
            ),
            'unidades' => (new UnidadMedidaService())->opcionesPorAbreviatura($nuevaLinea->unidadMedida),
            'grupos' => $this->service->agruparPorCategoria($presupuesto),
            'resumenPartidas' => $this->service->resumenPorPartida($presupuesto),
        ]);
    }

    /**
     * "Armar presupuesto": todos los items pendientes de la lista en una
     * tabla, con el menor precio cotizado ya marcado, y un solo boton que
     * crea todas las lineas. Si el proyecto aun no tiene presupuesto, se
     * crea aqui mismo (ver PresupuestoService::armar()).
     */
    public function actionArmar(int $idProyecto)
    {
        $proyecto = (new ProyectoService())->obtener($idProyecto);

        if ($proyecto === null) {
            throw new NotFoundHttpException('El proyecto solicitado no existe.');
        }

        $presupuesto = $proyecto->presupuesto;

        if ($presupuesto !== null && !$this->service->puedeEditarse($presupuesto)) {
            Yii::$app->session->setFlash('error', 'El presupuesto está "' . $presupuesto->estadoLabel() . '" y ya no se le agregan líneas.');

            return $this->redirect(['view', 'id' => $presupuesto->id]);
        }

        $errores = [];
        $elecciones = [];

        if (Yii::$app->request->isPost) {
            $elecciones = Yii::$app->request->post('lineas', []);
            $agregadas = 0;
            $presupuesto = $this->service->armar($proyecto, $elecciones, $errores, $agregadas);

            if ($presupuesto !== null) {
                Yii::$app->session->setFlash('success', $agregadas === 1 ? 'Se agregó 1 línea al presupuesto.' : "Se agregaron $agregadas líneas al presupuesto.");

                $vencidas = count($this->service->lineasConCotizacionVencida($presupuesto));
                if ($vencidas > 0) {
                    Yii::$app->session->setFlash('warning', "Atención: el presupuesto tiene $vencidas línea(s) con el precio de una cotización vencida. Se agregaron igual.");
                }

                return $this->redirect(['view', 'id' => $presupuesto->id]);
            }
        }

        return $this->render('armar', [
            'proyecto' => $proyecto,
            'presupuesto' => $presupuesto,
            'grupos' => $this->service->filasParaArmar($proyecto),
            'elecciones' => is_array($elecciones) ? $elecciones : [],
            'errores' => $errores,
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
            'resumenPartidas' => $this->service->resumenPorPartida($presupuesto),
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
