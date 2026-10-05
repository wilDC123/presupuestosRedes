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
 * @property string|null $email
 * @property bool $activo
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
            [['email'], 'string', 'max' => 200],
            [['email'], 'email'],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
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
            'email' => 'Correo electrónico',
            'activo' => 'Activo',
        ];
    }

    /**
     * Hasta dos iniciales de la razon social, para el recuadro que acompana
     * al nombre en los listados ("Tecnored Bolivia SRL" -> "TB").
     */
    public function iniciales(): string
    {
        $palabras = preg_split('/\s+/u', trim($this->razonSocial), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $iniciales = '';

        foreach (array_slice($palabras, 0, 2) as $palabra) {
            $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
        }

        return $iniciales;
    }
}
