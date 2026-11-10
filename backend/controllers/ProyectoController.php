<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\components\PresupuestoPdfGenerator;
use backend\components\UsuarioActual;
use backend\models\Proyecto;
use backend\services\HistorialService;
use backend\services\ListaItemsService;
use backend\services\OrdenCambioFaseService;
use backend\services\ParametrosProyectoService;
use backend\services\PlantillaService;
use backend\services\PersonalService;
use backend\services\ProyectoService;
use backend\services\ProcesoService;
use backend\services\RolProyectoService;
use backend\services\TipoOrdenCambioService;
use backend\services\TipoProyectoService;
use backend\services\contracts\InfraestructuraProvider;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controlador de "Gestionar Proyecto" (capa de Presentacion): solo los datos
 * basicos del proyecto y su pantalla central de navegacion.
 *
 * No consulta la base de datos ni conoce de donde salen los edificios y
 * ambientes: delega en ProyectoService, ParametrosProyectoService y la
 * fuente de infraestructura (InfraestructuraProvider).
 */
class ProyectoController extends Controller
{
    private ProyectoService $service;
    private ParametrosProyectoService $parametros;
    private InfraestructuraProvider $infraestructura;

    /**
     * init() es el "constructor" recomendado en Yii2 para componentes: se
     * ejecuta una vez al crear el controlador, antes de cualquier accion.
     *
     * Yii::$container->get() arma el servicio de parametros con la fuente
     * configurada en backend/config/main.php ('container').
     */
    public function init(): void
    {
        parent::init();
        $this->parametros = Yii::$container->get(ParametrosProyectoService::class);
        $this->infraestructura = Yii::$container->get(InfraestructuraProvider::class);
        $this->service = new ProyectoService($this->parametros, $this->infraestructura);
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
        ]);
    }

    /**
     * Pantalla central del proyecto: sus datos, en que paso va y los accesos
     * a cada paso.
     */
    public function actionView(int $id): string
    {
        $model = $this->findModel($id);
        $historial = new HistorialService();

        return $this->render('view', [
            'model' => $model,
            'fechas' => $historial->fechas($model),
            'historial' => $historial->delProyecto($model->id),
            'pasos' => $this->service->pasos($model),
            'urlPresupuesto' => $this->service->urlPresupuesto($model),
            'nombreTipo' => $model->tipoProyecto?->nombre,
            'monto' => $this->service->montoPresupuesto($model),
            'totalItems' => (new ListaItemsService())->contar($model),
            'listaEditable' => (new ListaItemsService())->puedeEditarse($model),
            'puedeEditarse' => $this->service->puedeEditarse($model),
            'estaAprobado' => $this->service->estaAprobado($model),
            // "Agregar fase": en la ultima fase, aprobada o no (varias fases a la vez, 2026-11-09).
            'puedeAgregarFase' => $this->service->puedeAgregarFase($model),
            // "Registrar orden de cambio": en una fase con su presupuesto aprobado (parte 6).
            'puedeRegistrarOrden' => (new OrdenCambioFaseService())->puedeRegistrar($model),
        ]);
    }

    /**
     * Ficha tecnica del proyecto en PDF (boton en "Datos generales" de la
     * vista). Se abre en el navegador ('inline') para imprimirla. Mientras no
     * esta aprobada lleva la marca de agua BORRADOR, como el presupuesto (ver
     * ProyectoService::datosFicha()).
     */
    public function actionFicha(int $id): Response
    {
        $model = $this->findModel($id);
        $datos = $this->service->datosFicha($model);

        $html = $this->renderPartial('ficha-pdf', [
            'model' => $model,
            'datos' => $datos,
            'usuario' => UsuarioActual::nombre(),
        ]);

        return Yii::$app->response->sendContentAsFile(
            (new PresupuestoPdfGenerator())->generarDesdeHtml($html, $datos['aprobada'] ? null : PresupuestoPdfGenerator::MARCA_BORRADOR),
            'ficha_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $datos['codigo'] ?: (string) $model->id) . '.pdf',
            ['mimeType' => 'application/pdf', 'inline' => true]
        );
    }

    /**
     * "Registrar orden de cambio" de una fase aprobada (parte 6): solo
     * descripcion, motivo y tipo; lo demas lo copia OrdenCambioFaseService de
     * la fase. Despues sigue el mismo camino que una fase: plantilla, lista...
     */
    public function actionOrdenCambio(int $idFase)
    {
        $fase = $this->findModel($idFase);
        $ordenes = new OrdenCambioFaseService();

        if (!$ordenes->puedeRegistrar($fase)) {
            Yii::$app->session->setFlash('error', 'Una orden de cambio se registra sobre una fase con su presupuesto aprobado.');

            return $this->redirect(['view', 'id' => $fase->id]);
        }

        $orden = $ordenes->nueva($fase);

        if (Yii::$app->request->isPost && $ordenes->crear($fase, $orden, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', $orden->identificador() . ' registrada. Ahora arme su lista solo con lo que se agrega.');

            return $this->redirect(['plantilla', 'id' => $orden->id]);
        }

        return $this->render('orden-cambio', ['orden' => $orden, 'fase' => $fase, 'tipos' => (new TipoOrdenCambioService())->opciones()]);
    }

    /** Cambiar descripcion, motivo o tipo de una orden de cambio no aprobada. */
    public function actionEditarOrdenCambio(int $id)
    {
        $orden = $this->findModel($id);

        if (!$orden->esOrdenCambio()) {
            return $this->redirect(['update', 'id' => $orden->id]);
        }

        if (!$this->service->puedeEditarse($orden)) {
            Yii::$app->session->setFlash('error', 'Esta orden de cambio ya tiene su presupuesto ' . mb_strtolower($orden->presupuesto->estadoLabel()) . ' y no se modifica.');

            return $this->redirect(['view', 'id' => $orden->id]);
        }

        if (Yii::$app->request->isPost && (new OrdenCambioFaseService())->actualizar($orden, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Orden de cambio actualizada.');

            return $this->redirect(['view', 'id' => $orden->id]);
        }

        // El tipo que ya tenia se conserva en el desplegable aunque se haya desactivado.
        $tipoGuardado = $orden->getOldAttribute('idTipoOrdenCambio');

        return $this->render('orden-cambio', [
            'orden' => $orden,
            'fase' => $orden->faseOrigen,
            'tipos' => (new TipoOrdenCambioService())->opciones($tipoGuardado === null ? null : (int) $tipoGuardado),
        ]);
    }

    /**
     * ?idAnterior= llega desde el boton "Crear fase siguiente" de un
     * proyecto aprobado: el formulario se abre ya enlazado a el.
     */
    public function actionCreate(?int $idAnterior = null)
    {
        $model = $this->service->nuevo($idAnterior);

        if ($this->service->crear($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', $model->identificador() . ' creado. Ahora puede cargar una plantilla de ítems o empezar en blanco.');

            // "Guardar y continuar": el paso siguiente es la plantilla.
            return $this->redirect(['plantilla', 'id' => $model->id]);
        }

        return $this->render('create', $this->datosFormulario($model));
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        // Una orden de cambio solo cambia descripcion, motivo y tipo (parte 6).
        if ($model->esOrdenCambio()) {
            return $this->redirect(['editar-orden-cambio', 'id' => $model->id]);
        }

        if (!$this->service->puedeEditarse($model)) {
            Yii::$app->session->setFlash('error', 'Este proyecto ya tiene su presupuesto ' . mb_strtolower($model->presupuesto->estadoLabel()) . ' y no se modifica. Si piden algo más, registre una orden de cambio o agregue una fase.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        if ($this->service->actualizar($model, Yii::$app->request->post())) {
            Yii::$app->session->setFlash('success', 'Datos del proyecto actualizados.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        // En GET se muestran las areas y el equipo guardados; si el POST
        // fallo la validacion, se conserva lo que el usuario eligio.
        if (!Yii::$app->request->isPost) {
            $this->service->cargarSeleccion($model);
        }

        return $this->render('update', $this->datosFormulario($model));
    }

    /**
     * Paso "Plantilla": GET muestra las plantillas activas con su vista
     * previa; POST copia la elegida a la lista del proyecto (accion=usar) o
     * marca que el proyecto empieza en blanco (accion=omitir).
     */
    public function actionPlantilla(int $id)
    {
        $model = $this->findModel($id);
        $lista = new ListaItemsService();
        $plantillas = new PlantillaService();

        if (!$this->service->puedeEditarse($model)) {
            Yii::$app->session->setFlash('error', 'Este proyecto ya no se modifica: no se le puede cargar una plantilla.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        if (!$lista->puedeEditarse($model)) {
            Yii::$app->session->setFlash('error', 'La lista ya fue enviada a cotización: para cargarle una plantilla reábrala antes.');

            return $this->redirect(['/lista-items/index', 'id' => $model->id]);
        }

        $peticion = Yii::$app->request;

        if ($peticion->isPost) {
            if ($peticion->post('accion') === 'omitir') {
                $lista->empezarEnBlanco($model);
                // Omitir no borra nada: si la lista ya tenia items, se conservan.
                Yii::$app->session->setFlash('success', $lista->contar($model) > 0
                    ? 'No se cargó ninguna plantilla. La lista del proyecto conserva sus ítems.'
                    : 'El proyecto empieza con la lista en blanco.');

                return $this->redirect(['view', 'id' => $model->id]);
            }

            $plantilla = $plantillas->obtener((int) $peticion->post('idPlantilla'));

            if ($plantilla === null || !$plantilla->activo) {
                Yii::$app->session->setFlash('error', 'Seleccione una plantilla activa.');

                return $this->redirect(['plantilla', 'id' => $model->id]);
            }

            $agregados = $lista->usarPlantilla($model, $plantilla);
            Yii::$app->session->setFlash('success', 'Se copiaron ' . $agregados . ' ítems de la plantilla "' . $plantilla->nombre . '" a la lista del proyecto.');

            // El paso siguiente es revisar la lista y definir cantidades.
            return $this->redirect(['/lista-items/index', 'id' => $model->id]);
        }

        // Las plantillas del tipo de este proyecto van primero.
        $filas = $plantillas->listar(true);
        $esDelTipo = static fn ($fila) => $model->idTipoProyecto !== null && (int) $fila['plantilla']->idTipoProyecto === (int) $model->idTipoProyecto;
        usort($filas, static fn ($a, $b) => $esDelTipo($b) <=> $esDelTipo($a));

        return $this->render('plantilla', [
            'model' => $model,
            'pasos' => $this->service->pasos($model),
            'plantillas' => $filas,
            'totalItems' => $lista->contar($model),
        ]);
    }

    /**
     * Las cuatro acciones siguientes responden JSON al formulario
     * (web/js/proyecto-form.js) para elegir el area de intervencion paso a
     * paso. Devuelven listas [{id, nombre}] y no objetos {id: nombre}: el
     * navegador reordena las claves numericas de un objeto, y el orden
     * (por nombre, o de abajo hacia arriba en los niveles) importa.
     */
    public function actionBloques(string $idEdificio): Response
    {
        return $this->asJson(self::lista($this->infraestructura->bloques($idEdificio)));
    }

    public function actionNiveles(int $idBloqueArea): Response
    {
        return $this->asJson(self::lista($this->infraestructura->niveles($idBloqueArea)));
    }

    public function actionAmbientes(int $idBloqueArea, string $numeroNivel): Response
    {
        return $this->asJson(self::lista($this->infraestructura->ambientes($idBloqueArea, $numeroNivel)));
    }

    /**
     * Buscador del area ("ciencias y tecnologia", "lab economicas", "E303"):
     * edificios que coinciden y ambientes con su area completa, listos para
     * agregarse. Ver ProyectoService::buscarAreas().
     */
    public function actionBuscarAmbiente(string $q = ''): Response
    {
        return $this->asJson($this->service->buscarAreas($q));
    }

    public function actionDelete(int $id)
    {
        $model = $this->findModel($id);
        $idFase = $model->idFaseOrigen;

        $error = $this->service->eliminar($model);

        if ($error !== null) {
            Yii::$app->session->setFlash('error', $error);

            return $this->redirect(['view', 'id' => $model->id]);
        }

        // Una orden de cambio eliminada vuelve a su fase.
        if ($idFase !== null) {
            Yii::$app->session->setFlash('success', $model->textoOrdenCambio() . ' eliminada. Sus especificaciones emitidas quedaron anuladas.');

            return $this->redirect(['view', 'id' => $idFase]);
        }

        return $this->redirect(['index']);
    }

    /**
     * Todo lo que necesita _form.php ademas del proyecto. Se arma aqui para
     * que la vista no consulte la base ni tenga listas escritas a mano.
     */
    private function datosFormulario(Proyecto $model): array
    {
        $equipo = array_filter(is_array($model->equipo) ? $model->equipo : [], 'is_array');

        return [
            'model' => $model,
            'tipos' => (new TipoProyectoService())->opciones($model->getOldAttribute('idTipoProyecto')),
            'procesos' => (new ProcesoService())->opciones($model->getOldAttribute('idProceso')),
            'sugerenciasUnidad' => $this->service->sugerenciasUnidad(),
            'edificios' => $this->service->edificiosParaFormulario(),
            'areas' => $this->service->areasDelFormulario($model),
            // Personas y roles activos, mas los que ya estan en el equipo del formulario.
            'personal' => (new PersonalService())->opciones(array_column($equipo, 'nombre')),
            'roles' => (new RolProyectoService())->opciones(array_map('intval', array_filter(array_column($equipo, 'idRol'), 'ctype_digit'))),
            'datosComunesFijos' => $this->service->datosComunesFijos($model),
        ];
    }

    /**
     * @param array<int|string, string> $opciones [id => nombre]
     * @return array<int, array{id: string, nombre: string}>
     */
    private static function lista(array $opciones): array
    {
        $lista = [];

        foreach ($opciones as $id => $nombre) {
            $lista[] = ['id' => (string) $id, 'nombre' => $nombre];
        }

        return $lista;
    }

    protected function findModel(int $id): Proyecto
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El proyecto solicitado no existe.');
        }

        return $model;
    }
}
