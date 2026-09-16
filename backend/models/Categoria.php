<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Categoria".
 *
 * @property int $id
 * @property string $nombre
 * @property int $orden
 */
class Categoria extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.Categoria';
    }

    public function rules(): array
    {
        return [
            [['nombre', 'orden'], 'required'],
            [['orden'], 'integer'],
            [['nombre'], 'string', 'max' => 100],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'nombre' => 'Nombre',
            'orden' => 'Orden',
        ];
    }
}
