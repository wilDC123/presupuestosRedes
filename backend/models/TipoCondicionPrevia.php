<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.TipoCondicionPrevia": los tipos de condicion
 * o requerimiento previo del proyecto (Instalaciones electricas, Obras
 * civiles, Apertura de ductos, Despeje del ambiente...). Revision del
 * 2026-10-29: "que haya algo para crear" mas tipos.
 *
 * Un tipo en uso no se elimina: se desactiva. Uno inactivo ya no se ofrece,
 * pero los proyectos que lo tenian lo conservan.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 */
class TipoCondicionPrevia extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.TipoCondicionPrevia';
    }

    public function rules(): array
    {
        return [
            [['nombre'], 'trim'],
            [['nombre'], 'required'],
            [['nombre'], 'string', 'max' => 150],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
            // 'unique' consulta la base, asi que solo se valida en el servidor.
            [['nombre'], 'unique', 'message' => 'Ya existe un tipo con este nombre.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'nombre' => 'Nombre',
            'activo' => 'Activo',
        ];
    }
}
