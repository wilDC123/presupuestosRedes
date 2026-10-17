<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.EspecificacionCaracteristica": una fila tecnica
 * de una version de la ficha (en el formulario NET, una fila de la seccion
 * "I. Requisitos necesarios").
 *
 * Las filas de "marca y modelo" y "URL" no se guardan aqui: el formulario las
 * agrega siempre.
 *
 * @property int $id
 * @property int $idVersion
 * @property int $orden
 * @property string|null $nombre ej. "Calibre del conductor" (opcional: hay filas que son un solo texto).
 * @property string $valor ej. "23 AWG".
 *
 * @property-read EspecificacionVersion $version
 */
class EspecificacionCaracteristica extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.EspecificacionCaracteristica';
    }

    public function rules(): array
    {
        return [
            [['nombre', 'valor'], 'trim'],
            [['nombre'], 'default', 'value' => null],
            [['idVersion', 'valor'], 'required'],
            [['idVersion', 'orden'], 'integer'],
            [['orden'], 'default', 'value' => 0],
            [['nombre'], 'string', 'max' => 200],
            [['valor'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idVersion' => 'Versión',
            'orden' => 'Orden',
            'nombre' => 'Característica',
            'valor' => 'Valor',
        ];
    }

    public function getVersion(): ActiveQuery
    {
        return $this->hasOne(EspecificacionVersion::class, ['id' => 'idVersion']);
    }

    /**
     * Como se imprime: "Calibre del conductor: 23 AWG", o solo el valor si la
     * fila no tiene nombre.
     */
    public function texto(): string
    {
        return $this->nombre !== null && $this->nombre !== ''
            ? $this->nombre . ': ' . $this->valor
            : $this->valor;
    }
}
