<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Proveedor".
 *
 * @property int $id
 * @property string $razonSocial
 * @property string|null $nit
 * @property string|null $contacto
 * @property string|null $telefono
 * @property string|null $direccion
 */
class Proveedor extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.Proveedor';
    }

    public function rules(): array
    {
        return [
            [['razonSocial'], 'required'],
            [['razonSocial'], 'string', 'max' => 200],
            [['nit'], 'string', 'max' => 50],
            [['contacto'], 'string', 'max' => 200],
            [['telefono'], 'string', 'max' => 100],
            [['direccion'], 'string', 'max' => 300],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'razonSocial' => 'Razón social',
            'nit' => 'NIT',
            'contacto' => 'Contacto',
            'telefono' => 'Teléfono',
            'direccion' => 'Dirección',
        ];
    }
}
