<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.TipoOrdenCambio": los tipos de orden de cambio
 * (2026-11-09). Se cargo con la lista 7.1.1 "Tipos de ordenes de cambio a
 * considerar" del formato RD-GD-FR-006 (Nuevas solicitudes de los usuarios,
 * Errores u omisiones..., Presupuestos imprecisos...) y el encargado la
 * administra en Configuracion -> "Tipos de orden de cambio".
 *
 * Un tipo en uso no se elimina: se desactiva. Uno inactivo ya no se ofrece,
 * pero las ordenes que lo tenian lo conservan.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 */
class TipoOrdenCambio extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.TipoOrdenCambio';
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

    /**
     * Regla para el idTipoOrdenCambio de Proyecto (solo en el escenario de la
     * orden de cambio): el tipo debe existir y estar activo, salvo que la
     * orden ya lo tuviera guardado (como TipoProyecto::reglaElegible()).
     */
    public static function reglaElegible(string $escenario): array
    {
        return [
            ['idTipoOrdenCambio'], 'exist',
            'targetClass' => self::class,
            'targetAttribute' => ['idTipoOrdenCambio' => 'id'],
            'filter' => ['activo' => true],
            'when' => static fn (ActiveRecord $model) => $model->isAttributeChanged('idTipoOrdenCambio', false),
            'message' => 'El tipo de cambio elegido no existe o está inactivo.',
            'on' => $escenario,
        ];
    }
}
