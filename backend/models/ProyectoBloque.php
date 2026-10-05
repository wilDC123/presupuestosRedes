<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ProyectoBloque": un bloque del edificio que el
 * proyecto involucra. Un proyecto puede tener varios.
 *
 * Guarda el codigo y tambien el nombre del bloque (copiado de la fuente de
 * parametros al guardar el proyecto), para no depender de esa fuente al
 * mostrar un proyecto antiguo.
 *
 * @property int $id
 * @property int $idProyecto
 * @property string $codigo
 * @property string $nombre
 */
class ProyectoBloque extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.ProyectoBloque';
    }

    public function rules(): array
    {
        return [
            [['idProyecto', 'codigo', 'nombre'], 'required'],
            [['idProyecto'], 'integer'],
            [['codigo'], 'string', 'max' => 30],
            [['nombre'], 'string', 'max' => 100],
        ];
    }
}
