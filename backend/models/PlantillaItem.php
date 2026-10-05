<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.PlantillaItem": un item del subcatalogo dentro
 * de una plantilla.
 *
 * idCategoria es la clasificacion del item DENTRO de la plantilla; puede ser
 * distinta de la categoria que el item tiene en el subcatalogo.
 *
 * @property int $id
 * @property int $idPlantilla
 * @property int $idSubcatalogo
 * @property int $idCategoria
 * @property string|null $cantidadSugerida
 * @property string|null $observacion
 * @property int $orden
 *
 * @property-read SubcatalogoItem $subcatalogoItem
 * @property-read Categoria $categoria
 */
class PlantillaItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.PlantillaItem';
    }

    public function rules(): array
    {
        return [
            [['idSubcatalogo', 'idCategoria'], 'required'],
            [['idPlantilla', 'idSubcatalogo', 'idCategoria', 'orden'], 'integer'],
            [['cantidadSugerida'], 'number', 'min' => 0, 'max' => 99999999.99],
            [['observacion'], 'trim'],
            [['observacion'], 'string', 'max' => 300],
            [['idSubcatalogo'], 'exist', 'skipOnError' => true, 'targetClass' => SubcatalogoItem::class, 'targetAttribute' => ['idSubcatalogo' => 'id']],
            [['idCategoria'], 'exist', 'skipOnError' => true, 'targetClass' => Categoria::class, 'targetAttribute' => ['idCategoria' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'idSubcatalogo' => 'Ítem',
            'idCategoria' => 'Clasificación',
            'cantidadSugerida' => 'Cantidad sugerida',
            'observacion' => 'Observación',
        ];
    }

    public function getSubcatalogoItem(): ActiveQuery
    {
        return $this->hasOne(SubcatalogoItem::class, ['id' => 'idSubcatalogo']);
    }

    public function getCategoria(): ActiveQuery
    {
        return $this->hasOne(Categoria::class, ['id' => 'idCategoria']);
    }

    /**
     * '' -> NULL: SQL Server no convierte '' a decimal.
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        foreach (['cantidadSugerida', 'observacion'] as $atributo) {
            if ($this->$atributo === '') {
                $this->$atributo = null;
            }
        }

        return true;
    }
}
