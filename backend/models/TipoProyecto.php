<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.TipoProyecto" (videovigilancia, cableado
 * estructurado...). Lo usan Proyecto y Plantilla por idTipoProyecto.
 *
 * Un tipo en uso no se elimina: se desactiva. Uno inactivo ya no se ofrece
 * para elegir, pero los proyectos y plantillas que lo tenian lo conservan.
 *
 * @property int $id
 * @property string $nombre ej. "Videovigilancia"
 * @property bool $activo
 */
class TipoProyecto extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.TipoProyecto';
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
            [['nombre'], 'unique', 'message' => 'Ya existe un tipo de proyecto con este nombre.'],
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

    /**
     * Regla para el idTipoProyecto de Proyecto y Plantilla: el tipo debe
     * existir y estar activo, salvo que ya lo tuviera guardado (asi un
     * proyecto antiguo se sigue editando aunque su tipo se haya desactivado).
     *
     * isAttributeChanged(..., false) compara sin tipo estricto: el "3" que
     * llega del formulario es igual al 3 guardado.
     */
    public static function reglaElegible(): array
    {
        return [
            ['idTipoProyecto'], 'exist',
            'targetClass' => self::class,
            'targetAttribute' => ['idTipoProyecto' => 'id'],
            'filter' => ['activo' => true],
            'when' => static fn (ActiveRecord $model) => $model->isAttributeChanged('idTipoProyecto', false),
            'message' => 'El tipo de proyecto elegido no existe o está inactivo.',
        ];
    }
}
