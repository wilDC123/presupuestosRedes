<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ProyectoItem": una linea de la LISTA DE ITEMS
 * del proyecto (item + cantidad, SIN precio).
 *
 * La lista no es el presupuesto: el presupuesto es otro reporte, que nace de
 * esta lista mas los precios de las cotizaciones.
 *
 * @property int $id
 * @property int $idProyecto
 * @property int $idSubcatalogo
 * @property int $idCategoria Clasificacion del item en este proyecto.
 * @property string $cantidad
 * @property string|null $unidadMedida
 * @property string|null $observacion Detalle de la linea ("de 1 metro", "segun especificacion").
 * @property int $orden
 *
 * @property-read Proyecto $proyecto
 * @property-read SubcatalogoItem $subcatalogoItem
 * @property-read Categoria $categoria
 */
class ProyectoItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.ProyectoItem';
    }

    public function rules(): array
    {
        return [
            [['idProyecto', 'idSubcatalogo', 'idCategoria', 'cantidad'], 'required'],
            [['idProyecto', 'idSubcatalogo', 'idCategoria', 'orden'], 'integer'],
            [['cantidad'], 'number', 'min' => 0, 'max' => 99999999.99],
            [['unidadMedida'], 'string', 'max' => 20],
            [['observacion'], 'trim'],
            [['observacion'], 'string', 'max' => 300],
            [['idSubcatalogo'], 'exist', 'skipOnError' => true, 'targetClass' => SubcatalogoItem::class, 'targetAttribute' => ['idSubcatalogo' => 'id']],
            [['idCategoria'], 'exist', 'skipOnError' => true, 'targetClass' => Categoria::class, 'targetAttribute' => ['idCategoria' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'idSubcatalogo' => 'Ítem',
            'idCategoria' => 'Clasificación',
            'cantidad' => 'Cantidad',
            'unidadMedida' => 'Unidad',
            'observacion' => 'Observación',
        ];
    }

    public function getProyecto(): ActiveQuery
    {
        return $this->hasOne(Proyecto::class, ['id' => 'idProyecto']);
    }

    public function getSubcatalogoItem(): ActiveQuery
    {
        return $this->hasOne(SubcatalogoItem::class, ['id' => 'idSubcatalogo']);
    }

    public function getCategoria(): ActiveQuery
    {
        return $this->hasOne(Categoria::class, ['id' => 'idCategoria']);
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        foreach (['unidadMedida', 'observacion'] as $atributo) {
            if ($this->$atributo === '') {
                $this->$atributo = null;
            }
        }

        return true;
    }
}
