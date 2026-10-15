<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.SolicitudCotizacion": un grupo de items de la
 * lista del proyecto que se manda a cotizar a un rubro de proveedores.
 *
 * Solo "nombre" y "nota" llegan del formulario. El proyecto y el numero los
 * pone SolicitudCotizacionService: por eso no tienen regla.
 *
 * @property int $id
 * @property int $idProyecto
 * @property int $numero Correlativo dentro del proyecto.
 * @property string $nombre Rubro al que se envia ("Ferreteria y ducteo").
 * @property string|null $nota Texto libre para el proveedor.
 * @property string $fecha
 *
 * @property-read Proyecto $proyecto
 * @property-read SolicitudCotizacionItem[] $items
 * @property-read Cotizacion[] $cotizaciones respuestas de los proveedores
 */
class SolicitudCotizacion extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.SolicitudCotizacion';
    }

    public function rules(): array
    {
        return [
            [['nombre', 'nota'], 'trim'],
            [['nombre'], 'required', 'message' => 'Escriba el nombre de la solicitud (el rubro al que se envía).'],
            [['nombre'], 'string', 'max' => 200],
            [['nota'], 'string', 'max' => 500],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'numero' => 'N.°',
            'nombre' => 'Rubro',
            'nota' => 'Nota para el proveedor',
            'fecha' => 'Fecha',
        ];
    }

    public function getProyecto(): ActiveQuery
    {
        return $this->hasOne(Proyecto::class, ['id' => 'idProyecto']);
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(SolicitudCotizacionItem::class, ['idSolicitud' => 'id'])->orderBy(['id' => SORT_ASC]);
    }

    public function getCotizaciones(): ActiveQuery
    {
        return $this->hasMany(Cotizacion::class, ['idSolicitudCotizacion' => 'id'])->orderBy(['id' => SORT_ASC]);
    }

    /**
     * "SC-02": numero de la solicitud dentro del proyecto.
     */
    public function codigoCorto(): string
    {
        return 'SC-' . str_pad((string) $this->numero, 2, '0', STR_PAD_LEFT);
    }

    /**
     * "RD-PI-PT-01 / SC-02": identifica la solicitud en el documento impreso.
     */
    public function codigo(): string
    {
        return ($this->proyecto->codigo ?: '#' . $this->idProyecto) . ' / ' . $this->codigoCorto();
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->nota === '') {
            $this->nota = null;
        }

        return true;
    }
}
