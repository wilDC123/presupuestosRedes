<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Cotizacion;
use backend\models\CotizacionDetalle;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * Controlador CRUD para redes.Cotizacion.
 */
class CotizacionController extends Controller
{
    private const UPLOAD_SUBPATH = 'uploads/cotizaciones';

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
        $dataProvider = new ActiveDataProvider([
            'query' => Cotizacion::find()->with('proveedor')->orderBy(['id' => SORT_DESC]),
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
        $model = new Cotizacion();

        if ($model->load(Yii::$app->request->post())) {
            $model->documentoFile = UploadedFile::getInstance($model, 'documentoFile');

            if ($model->validate()) {
                $this->saveUploadedFile($model);

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
        $modelsDetalle = $model->cotizacionDetalles;

        if ($model->load(Yii::$app->request->post())) {
            $model->documentoFile = UploadedFile::getInstance($model, 'documentoFile');

            $oldIDs = ArrayHelper::map($modelsDetalle, 'id', 'id');
            $modelsDetalle = CotizacionDetalle::createMultiple(CotizacionDetalle::class, $modelsDetalle);
            Model::loadMultiple($modelsDetalle, Yii::$app->request->post());
            $deletedIDs = array_diff($oldIDs, array_filter(ArrayHelper::map($modelsDetalle, 'id', 'id')));

            $valid = $model->validate();
            $valid = Model::validateMultiple($modelsDetalle) && $valid;

            if ($valid) {
                $this->saveUploadedFile($model);

                $transaction = Yii::$app->db->beginTransaction();
                try {
                    $saved = $model->save(false);

                    if ($saved && !empty($deletedIDs)) {
                        CotizacionDetalle::deleteAll(['id' => $deletedIDs]);
                    }

                    if ($saved) {
                        foreach ($modelsDetalle as $modelDetalle) {
                            $modelDetalle->idCotizacion = $model->id;
                            if (!$modelDetalle->save(false)) {
                                $saved = false;
                                break;
                            }
                        }
                    }

                    if ($saved) {
                        $transaction->commit();

                        return $this->redirect(['view', 'id' => $model->id]);
                    }

                    $transaction->rollBack();
                } catch (\Throwable $e) {
                    $transaction->rollBack();
                    throw $e;
                }
            }
        }

        if (empty($modelsDetalle)) {
            $modelsDetalle = [new CotizacionDetalle()];
        }

        return $this->render('update', [
            'model' => $model,
            'modelsDetalle' => $modelsDetalle,
        ]);
    }

    public function actionDelete(int $id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    protected function findModel(int $id): Cotizacion
    {
        $model = Cotizacion::findOne($id);

        if ($model === null) {
            throw new NotFoundHttpException('La cotización solicitada no existe.');
        }

        return $model;
    }

    /**
     * Si el usuario adjuntó un PDF nuevo, lo guarda en backend/web/uploads/cotizaciones
     * y actualiza $model->documentoRuta con la ruta relativa (la que se guarda en la BD).
     * Si no adjuntó nada, $model->documentoRuta queda tal como estaba.
     */
    private function saveUploadedFile(Cotizacion $model): void
    {
        if ($model->documentoFile === null) {
            return;
        }

        $uploadDir = Yii::getAlias('@backend/web/' . self::UPLOAD_SUBPATH);
        FileHelper::createDirectory($uploadDir);

        $fileName = uniqid('cot_', true) . '.' . $model->documentoFile->extension;
        $model->documentoFile->saveAs($uploadDir . '/' . $fileName);

        $model->documentoRuta = self::UPLOAD_SUBPATH . '/' . $fileName;
    }
}
