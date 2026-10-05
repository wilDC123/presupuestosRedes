<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Plantilla": una lista reutilizable de items
 * del subcatalogo (videovigilancia, cableado completo, solo equipamiento...).
 *
 * Cada plantilla elige que clasificaciones (categorias) incluye y en cual va
 * cada item. Al usarla en un proyecto, sus items se COPIAN a la lista del
 * proyecto: no queda enlazada.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $tipoProyecto Codigo del tipo de proyecto sugerido.
 * @property bool $activo
 * @property string $fechaCreacion
 *
 * @property-read PlantillaCategoria[] $plantillaCategorias
 * @property-read Categoria[] $categorias
 * @property-read PlantillaItem[] $items
 */
class Plantilla extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.Plantilla';
    }

    public function rules(): array
    {
        return [
            [['nombre'], 'trim'],
            [['nombre'], 'required'],
            [['nombre'], 'string', 'max' => 200],
            [['descripcion'], 'string', 'max' => 500],
            [['tipoProyecto'], 'string', 'max' => 50],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'nombre' => 'Nombre de la plantilla',
            'descripcion' => 'Descripción',
            'tipoProyecto' => 'Tipo de proyecto sugerido',
            'activo' => 'Activa',
            'fechaCreacion' => 'Fecha de creación',
        ];
    }

    public function getPlantillaCategorias(): ActiveQuery
    {
        return $this->hasMany(PlantillaCategoria::class, ['idPlantilla' => 'id'])->orderBy(['orden' => SORT_ASC]);
    }

    /**
     * Las categorias incluidas, pasando por la tabla intermedia
     * redes.PlantillaCategoria (relacion "muchos a muchos": via()).
     */
    public function getCategorias(): ActiveQuery
    {
        return $this->hasMany(Categoria::class, ['id' => 'idCategoria'])->via('plantillaCategorias');
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(PlantillaItem::class, ['idPlantilla' => 'id'])->orderBy(['orden' => SORT_ASC, 'id' => SORT_ASC]);
    }

    /**
     * '' -> NULL en las columnas que permiten NULL (el desplegable con
     * "(ninguno)" envia cadena vacia).
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        foreach (['descripcion', 'tipoProyecto'] as $atributo) {
            if ($this->$atributo === '') {
                $this->$atributo = null;
            }
        }

        return true;
    }
}
