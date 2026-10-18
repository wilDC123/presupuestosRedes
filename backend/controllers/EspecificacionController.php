<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\ConfiguracionEspecificacion;
use backend\models\Especificacion;
use backend\services\CategoriaService;
use backend\services\EspecificacionService;
use backend\services\SubcatalogoItemService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Controlador de la LIBRERIA de especificaciones tecnicas (capa de
 * Presentacion).
 *
 * Solo traduce la peticion HTTP (filtros GET, POST, archivo subido) a
 * llamadas a EspecificacionService, y elige que vista/respuesta devolver.
 */
class EspecificacionController extends Controller
{
    private EspecificacionService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new EspecificacionService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'extraer-metadatos' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Libreria con filtros por GET: ?categoria=, ?buscar=, ?activo=.
     */
    public function actionIndex(): string
    {
        $peticion = Yii::$app->request;
        $filtros = [
            'categoria' => trim((string) $peticion->get('categoria', '')),
            'buscar' => trim((string) $peticion->get('buscar', '')),
            'activo' => trim((string) $peticion->get('activo', '')),
        ];

        return $this->render('index', [
            'filas' => $this->service->listar($filtros),
            'filtros' => $filtros,
            'categorias' => (new CategoriaService())->opciones(),
        ]);
    }

    /**
     * Ficha con su version actual. ?version=N muestra una version anterior
     * del historial (solo lectura).
     */
    public function actionView(int $id, ?int $version = null): string
    {
        $model = $this->findModel($id);
        $historial = $this->service->historial($model);
        $actual = $historial[0]['version'] ?? null;
        $mostrada = $version === null ? $actual : $this->service->version($model, $version);

        if ($version !== null && $mostrada === null) {
            throw new NotFoundHttpException('Esa versión de la especificación no existe.');
        }

        return $this->render('view', [
            'model' => $model,
            'version' => $mostrada,
            'esActual' => $mostrada !== null && $actual !== null && $mostrada->id === $actual->id,
            'historial' => $historial,
            'garantiaPorDefecto' => ConfiguracionEspecificacion::obtener()->garantiaPorDefecto,
            'usos' => $this->service->usos($model),
        ]);
    }

    public function actionCreate()
    {
        return $this->formulario(new Especificacion(), 'create', 'Especificación agregada a la librería.');
    }

    public function actionUpdate(int $id)
    {
        return $this->formulario($this->findModel($id), 'update', 'Especificación actualizada.');
    }

    public function actionDelete(int $id)
    {
        $model = $this->findModel($id);
        $motivo = $this->service->eliminar($model);

        if ($motivo !== null) {
            Yii::$app->session->setFlash('error', $motivo);

            return $this->redirect(['view', 'id' => $model->id]);
        }

        Yii::$app->session->setFlash('success', 'Especificación eliminada.');

        return $this->redirect(['index']);
    }

    /**
     * Endpoint AJAX invocado desde el boton "Extraer datos automaticamente"
     * del formulario. Recibe el mismo archivo del input archivoEspecificacion
     * y devuelve los metadatos encontrados (o un mensaje de error) como JSON.
     */
    public function actionExtraerMetadatos(): Response
    {
        $archivo = UploadedFile::getInstanceByName('archivoEspecificacion');

        return $this->asJson($this->service->extraerMetadatos($archivo));
    }

    protected function findModel(int $id): Especificacion
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('La especificación solicitada no existe.');
        }

        return $model;
    }

    /**
     * Crear y editar comparten todo: en GET muestra el formulario con lo
     * guardado; en POST guarda la ficha y su version y, si algo no es
     * valido, vuelve a mostrar lo que escribio el usuario.
     */
    private function formulario(Especificacion $model, string $vista, string $mensajeExito)
    {
        $formulario = $this->service->formulario($model);
        // Numero de la version actual si ya se emitio (el formulario avisa que
        // guardar cambios creara la siguiente). Se toma antes del POST: si el
        // guardado falla, 'version' pasa a ser lo que escribio el usuario.
        $numeroBloqueado = $formulario['emisiones'] > 0 ? (int) $formulario['version']->numero : null;
        $errores = [];

        if (Yii::$app->request->isPost) {
            $resultado = $this->service->guardar(
                $model,
                Yii::$app->request->post(),
                UploadedFile::getInstance($model, 'archivoEspecificacion')
            );

            if ($resultado['guardado']) {
                $version = $resultado['version'];
                $mensaje = $mensajeExito;
                if ($resultado['nueva'] && $version->numero > 1) {
                    $mensaje .= ' Se creó la ' . $version->etiqueta() . ': la versión anterior ya se usó en formularios NET emitidos y no se modifica.';
                }
                Yii::$app->session->setFlash('success', $mensaje);

                return $this->redirect(['view', 'id' => $model->id]);
            }

            $formulario['version'] = $resultado['version'];
            $formulario['filas'] = $resultado['filas'];
            $errores = $resultado['errores'];
        }

        $configuracion = ConfiguracionEspecificacion::obtener();

        return $this->render($vista, [
            'model' => $model,
            'version' => $formulario['version'],
            'filas' => $formulario['filas'],
            'emisiones' => $formulario['emisiones'],
            'numeroBloqueado' => $numeroBloqueado,
            'errores' => $errores,
            'garantiaPorDefecto' => $configuracion->garantiaPorDefecto,
            'categorias' => (new CategoriaService())->opciones(),
            'items' => (new SubcatalogoItemService())->opciones(),
        ]);
    }
}
