<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.RolProyecto": con que rol participa una
 * persona en el equipo de un proyecto (Proyectista, Director del proyecto,
 * Coordinador de infraestructura...). Revision del 2026-10-29.
 *
 * Un rol en uso no se elimina: se desactiva. Uno inactivo ya no se ofrece,
 * pero los proyectos que lo tenian lo conservan.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 */
class RolProyecto extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.RolProyecto';
    }

    public function rules(): array
    {
        return [
            [['nombre'], 'trim'],
            [['nombre'], 'required'],
            [['nombre'], 'string', 'max' => 100],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
            // 'unique' consulta la base, asi que solo se valida en el servidor.
            [['nombre'], 'unique', 'message' => 'Ya existe un rol con este nombre.'],
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
