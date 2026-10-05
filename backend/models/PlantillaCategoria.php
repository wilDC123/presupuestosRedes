<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.PlantillaCategoria": una clasificacion
 * (categoria) incluida en una plantilla.
 *
 * @property int $id
 * @property int $idPlantilla
 * @property int $idCategoria
 * @property int $orden
 */
class PlantillaCategoria extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.PlantillaCategoria';
    }

    public function rules(): array
    {
        return [
            [['idPlantilla', 'idCategoria'], 'required'],
            [['idPlantilla', 'idCategoria', 'orden'], 'integer'],
        ];
    }
}
