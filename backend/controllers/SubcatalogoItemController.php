<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\CatalogoSigma;
use backend\models\SubcatalogoItem;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controlador CRUD para redes.SubcatalogoItem.
 */
class SubcatalogoItemController extends Controller
{
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
            'query' => SubcatalogoItem::find()->with('categoria')->orderBy(['descripcion' => SORT_ASC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        $textoBusqueda = trim((string) Yii::$app->request->get('q', ''));
        $resultadosSigma = $textoBusqueda !== '' ? CatalogoSigma::buscar($textoBusqueda) : [];

        // idSigma ya usados en el subcatalogo, para saber que fila del
        // catalogo institucional ya fue agregada y no ofrecer duplicarla.
        $idsSigmaExistentes = SubcatalogoItem::find()
            ->select('idSigma')
            ->andWhere(['not', ['idSigma' => null]])
            ->column();

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'textoBusqueda' => $textoBusqueda,
            'resultadosSigma' => $resultadosSigma,
            'idsSigmaExistentes' => $idsSigmaExistentes,
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
        $model = new SubcatalogoItem();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        // Prellenado desde "Buscar en catalogo institucional": llega por GET
        // con el idSigma y la descripcion del item de dbo.CatalogoSigma que
        // el usuario eligio agregar. El usuario solo confirma/ajusta
        // categoria y unidad por defecto antes de guardar.
        if (Yii::$app->request->isGet && Yii::$app->request->get('idSigma') !== null) {
            $model->idSigma = Yii::$app->request->get('idSigma');
            $model->descripcion = (string) Yii::$app->request->get('descripcion', '');
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
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

    protected function findModel(int $id): SubcatalogoItem
    {
        $model = SubcatalogoItem::findOne($id);

        if ($model === null) {
            throw new NotFoundHttpException('El ítem de subcatálogo solicitado no existe.');
        }

        return $model;
    }
}
