<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ProyectoProyectista": una persona asignada como
 * proyectista del proyecto. Un proyecto puede tener varias.
 *
 * Solo guarda el nombre: la gestion de usuarios es del sistema
 * institucional, este modulo no administra personas.
 *
 * @property int $id
 * @property int $idProyecto
 * @property string $nombre
 */
class ProyectoProyectista extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.ProyectoProyectista';
    }

    public function rules(): array
    {
        return [
            [['idProyecto', 'nombre'], 'required'],
            [['idProyecto'], 'integer'],
            [['nombre'], 'string', 'max' => 200],
        ];
    }
}
