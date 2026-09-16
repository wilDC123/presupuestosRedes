<?php

declare(strict_types=1);

namespace backend\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Modelo para la tabla "redes.CotizacionDetalle".
 *
 * @property int $id
 * @property int $idCotizacion
 * @property int $idSubcatalogo
 * @property int|null $idEspecificacion
 * @property string|null $marcaModelo
 * @property string $cantidad
 * @property string $precioUnitario
 * @property string $precioTotal Columna calculada por SQL Server, solo lectura.
 *
 * @property-read Cotizacion $cotizacion
 * @property-read SubcatalogoItem $subcatalogoItem
 * @property-read Especificacion|null $especificacion
 */
class CotizacionDetalle extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.CotizacionDetalle';
    }

    public function rules(): array
    {
        return [
            [['idSubcatalogo', 'cantidad', 'precioUnitario'], 'required'],
            [['idCotizacion', 'idSubcatalogo', 'idEspecificacion'], 'integer'],
            [['cantidad', 'precioUnitario'], 'number', 'min' => 0],
            [['marcaModelo'], 'string', 'max' => 300],
            [['idSubcatalogo'], 'exist', 'skipOnError' => true, 'targetClass' => SubcatalogoItem::class, 'targetAttribute' => ['idSubcatalogo' => 'id']],
            [['idEspecificacion'], 'exist', 'skipOnError' => true, 'targetClass' => Especificacion::class, 'targetAttribute' => ['idEspecificacion' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idCotizacion' => 'Cotización',
            'idSubcatalogo' => 'Ítem de subcatálogo',
            'idEspecificacion' => 'Especificación',
            'marcaModelo' => 'Marca / modelo',
            'cantidad' => 'Cantidad',
            'precioUnitario' => 'Precio unitario',
            'precioTotal' => 'Precio total',
        ];
    }

    public function getCotizacion(): ActiveQuery
    {
        return $this->hasOne(Cotizacion::class, ['id' => 'idCotizacion']);
    }

    public function getSubcatalogoItem(): ActiveQuery
    {
        return $this->hasOne(SubcatalogoItem::class, ['id' => 'idSubcatalogo']);
    }

    public function getEspecificacion(): ActiveQuery
    {
        return $this->hasOne(Especificacion::class, ['id' => 'idEspecificacion']);
    }

    /**
     * idEspecificacion permite NULL, pero el dropDownList con prompt
     * '(ninguna)' envia '' cuando no se elige nada. SQL Server no puede
     * convertir '' a int, asi que hay que normalizar a null antes de
     * guardar (mismo riesgo que idSigma en SubcatalogoItem).
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->idEspecificacion === '') {
            $this->idEspecificacion = null;
        }

        return true;
    }

    /**
     * Helper del patron "multiples modelos en un formulario" de Yii2: empareja
     * cada fila enviada por POST con su modelo existente (si trae id) o crea
     * uno nuevo (si es una fila agregada con el boton "Anadir linea").
     *
     * @param string $modelClass
     * @param static[] $multipleModels
     * @return static[]
     */
    public static function createMultiple(string $modelClass, array $multipleModels = []): array
    {
        $model = new $modelClass();
        $formName = $model->formName();
        $post = Yii::$app->request->post($formName, []);
        $models = [];

        $indexedModels = [];
        if (!empty($multipleModels)) {
            $keys = ArrayHelper::map($multipleModels, 'id', 'id');
            $indexedModels = array_combine($keys, $multipleModels);
        }

        foreach ($post as $item) {
            if (!empty($item['id']) && isset($indexedModels[$item['id']])) {
                $models[] = $indexedModels[$item['id']];
            } else {
                $models[] = new $modelClass();
            }
        }

        return $models;
    }
}
