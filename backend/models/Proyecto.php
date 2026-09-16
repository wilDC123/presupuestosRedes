<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Proyecto".
 *
 * @property int $id
 * @property string|null $codigo
 * @property string $nombre
 * @property string|null $facultad
 * @property string|null $edificio
 * @property string|null $bloque
 * @property string $fechaCreacion
 */
class Proyecto extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.Proyecto';
    }

    public function rules(): array
    {
        return [
            [['nombre'], 'required'],
            [['codigo'], 'string', 'max' => 50],
            [['nombre'], 'string', 'max' => 300],
            [['facultad', 'edificio'], 'string', 'max' => 200],
            [['bloque'], 'string', 'max' => 100],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'codigo' => 'Código',
            'nombre' => 'Nombre',
            'facultad' => 'Facultad',
            'edificio' => 'Edificio',
            'bloque' => 'Bloque',
            'fechaCreacion' => 'Fecha de creación',
        ];
    }
}
