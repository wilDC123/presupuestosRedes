<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.SolicitudCotizacionItem": un item de la lista
 * del proyecto dentro de una solicitud de cotizacion.
 *
 * Cada item de la lista va en UNA sola solicitud (indice unico sobre
 * idProyectoItem). No guarda cantidad ni unidad: se leen del item de la lista.
 *
 * @property int $id
 * @property int $idSolicitud
 * @property int $idProyectoItem
 * @property string|null $marcaModeloReferencia Marca y modelo que la DTIC pide como referencia (texto libre).
 *
 * @property-read SolicitudCotizacion $solicitud
 * @property-read ProyectoItem $proyectoItem
 */
class SolicitudCotizacionItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.SolicitudCotizacionItem';
    }

    public function rules(): array
    {
        return [
            [['idSolicitud', 'idProyectoItem'], 'required'],
            [['idSolicitud', 'idProyectoItem'], 'integer'],
            [['marcaModeloReferencia'], 'trim'],
            // trim convierte null en '': se vuelve a null ("sin referencia").
            [['marcaModeloReferencia'], 'default', 'value' => null],
            [['marcaModeloReferencia'], 'string', 'max' => 300],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'marcaModeloReferencia' => 'Marca y modelo de referencia',
        ];
    }

    public function getSolicitud(): ActiveQuery
    {
        return $this->hasOne(SolicitudCotizacion::class, ['id' => 'idSolicitud']);
    }

    public function getProyectoItem(): ActiveQuery
    {
        return $this->hasOne(ProyectoItem::class, ['id' => 'idProyectoItem']);
    }
}
