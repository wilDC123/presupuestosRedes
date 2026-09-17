<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\components\EspecificacionMetadataExtractor;
use backend\models\Especificacion;
use Throwable;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\helpers\FileHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Controlador CRUD para redes.Especificacion.
 */
class EspecificacionController extends Controller
{
    private const UPLOAD_SUBPATH = 'uploads/especificaciones';

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

    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Especificacion::find()->with('subcatalogoItem')->orderBy(['id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionCreate()
    {
        $model = new Especificacion();

        if ($model->load(Yii::$app->request->post())) {
            $model->archivoEspecificacion = UploadedFile::getInstance($model, 'archivoEspecificacion');

            if ($model->validate()) {
                $this->guardarArchivoSubido($model);

                if ($model->save(false)) {
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            $model->archivoEspecificacion = UploadedFile::getInstance($model, 'archivoEspecificacion');

            if ($model->validate()) {
                $this->guardarArchivoSubido($model);

                if ($model->save(false)) {
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete(int $id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Endpoint AJAX invocado desde el boton "Extraer datos automaticamente"
     * del formulario. Recibe el mismo archivo del input archivoEspecificacion
     * (todavia no se esta guardando la Especificacion, solo se esta leyendo
     * el archivo para prellenar el formulario), lo procesa con
     * EspecificacionMetadataExtractor y devuelve los metadatos encontrados.
     *
     * La extraccion es una AYUDA: si algo falla o un campo no se encuentra
     * (ej. modeloSugerido, que es opcional), se responde igual con lo que
     * si se pudo encontrar -- nunca se bloquea al usuario para que complete
     * el formulario a mano.
     */
    public function actionExtraerMetadatos(): Response
    {
        $archivo = UploadedFile::getInstanceByName('archivoEspecificacion');

        if ($archivo === null) {
            return $this->asJson(['error' => 'No se recibió ningún archivo.']);
        }

        $extension = strtolower((string) $archivo->extension);

        if (!in_array($extension, ['pdf', 'docx'], true)) {
            return $this->asJson(['error' => 'Solo se aceptan archivos PDF o DOCX.']);
        }

        try {
            $extractor = new EspecificacionMetadataExtractor();
            $metadatos = $extractor->extraerDesdeArchivo($archivo->tempName, $extension);

            return $this->asJson(['metadatos' => $metadatos]);
        } catch (Throwable $e) {
            Yii::error("Error extrayendo metadatos de especificación: {$e->getMessage()}", __METHOD__);

            return $this->asJson(['error' => 'No se pudo leer el archivo. Verifique que no esté dañado y vuelva a intentar.']);
        }
    }

    protected function findModel(int $id): Especificacion
    {
        $model = Especificacion::findOne($id);

        if ($model === null) {
            throw new NotFoundHttpException('La especificación solicitada no existe.');
        }

        return $model;
    }

    /**
     * Si el usuario adjuntó un archivo nuevo, lo guarda en
     * backend/web/uploads/especificaciones y actualiza $model->documentoRuta
     * con la ruta relativa (la que se guarda en la BD). Este es el mismo
     * archivo que, si se usó, ya sirvió para prellenar el formulario via
     * actionExtraerMetadatos -- aca se persiste como el documento definitivo.
     * Si no adjuntó nada, documentoRuta queda tal como el usuario lo haya
     * escrito a mano en el campo de texto.
     */
    private function guardarArchivoSubido(Especificacion $model): void
    {
        if ($model->archivoEspecificacion === null) {
            return;
        }

        $uploadDir = Yii::getAlias('@backend/web/' . self::UPLOAD_SUBPATH);
        FileHelper::createDirectory($uploadDir);

        $fileName = uniqid('esp_', true) . '.' . $model->archivoEspecificacion->extension;
        $model->archivoEspecificacion->saveAs($uploadDir . '/' . $fileName);

        $model->documentoRuta = self::UPLOAD_SUBPATH . '/' . $fileName;
    }

}
