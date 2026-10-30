<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Personal": las personas que se pueden poner en
 * el equipo de un proyecto (revision del 2026-10-29; antes
 * redes.Proyectista). Solo una lista de nombres: no son usuarios ni tienen
 * permisos. El rol no es de la persona sino de su participacion en cada
 * proyecto (redes.ProyectoParticipante).
 *
 * El proyecto guarda una COPIA del nombre, asi que corregir o desactivar a
 * una persona no cambia los proyectos que ya la tienen. Una en uso no se
 * elimina: se desactiva.
 *
 * @property int $id
 * @property string $nombre ej. "Ing. Paola Rojas"
 * @property bool $activo
 */
class Personal extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.Personal';
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
            [['nombre'], 'unique', 'message' => 'Ya existe una persona con este nombre.'],
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
