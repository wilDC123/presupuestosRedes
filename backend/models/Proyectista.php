<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Proyectista": los nombres que se pueden
 * asignar como proyectistas de un proyecto. Solo una lista de nombres: no
 * son usuarios ni tienen permisos.
 *
 * El proyecto guarda una COPIA del nombre (redes.ProyectoProyectista), asi
 * que corregir o desactivar un proyectista no cambia los proyectos que ya lo
 * tienen. Uno en uso no se elimina: se desactiva.
 *
 * @property int $id
 * @property string $nombre ej. "Ing. Paola Rojas"
 * @property bool $activo
 */
class Proyectista extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.Proyectista';
    }

    public function rules(): array
    {
        return [
            [['nombre'], 'trim'],
            [['nombre'], 'required'],
            [['nombre'], 'string', 'max' => 200],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
            // 'unique' consulta la base, asi que solo se valida en el servidor.
            [['nombre'], 'unique', 'message' => 'Ya existe un proyectista con este nombre.'],
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
