<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.UnidadMedida".
 *
 * La abreviatura es lo que se muestra en listados, presupuesto y PDF, y lo
 * que se copia como texto a ProyectoItem y PresupuestoDetalle. Una unidad en
 * uso no se elimina: se desactiva.
 *
 * @property int $id
 * @property string $nombre ej. "Metro"
 * @property string $abreviatura ej. "m"
 * @property bool $activo
 */
class UnidadMedida extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.UnidadMedida';
    }

    public function rules(): array
    {
        return [
            [['nombre', 'abreviatura'], 'trim'],
            [['nombre', 'abreviatura'], 'required'],
            [['nombre'], 'string', 'max' => 50],
            [['abreviatura'], 'string', 'max' => 10],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
            // 'unique' consulta la base, asi que solo se valida en el servidor.
            [['nombre'], 'unique', 'message' => 'Ya existe una unidad con este nombre.'],
            [['abreviatura'], 'unique', 'message' => 'Ya existe una unidad con esta abreviatura.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'nombre' => 'Nombre',
            'abreviatura' => 'Abreviatura',
            'activo' => 'Activa',
        ];
    }

    /**
     * Texto de los desplegables: "Metro (m)".
     */
    public function etiqueta(): string
    {
        return $this->nombre . ' (' . $this->abreviatura . ')';
    }
}
