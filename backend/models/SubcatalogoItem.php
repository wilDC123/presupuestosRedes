<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.SubcatalogoItem".
 *
 * @property int $id
 * @property string|null $idSigma
 * @property int $idCategoria
 * @property string $descripcion
 * @property string|null $unidadDefecto
 * @property bool $activo
 * @property string $fechaRegistro
 *
 * @property-read Categoria $categoria
 */
class SubcatalogoItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.SubcatalogoItem';
    }

    public function rules(): array
    {
        return [
            [['idCategoria', 'descripcion'], 'required'],
            [['idCategoria'], 'integer'],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
            [['idSigma'], 'string', 'max' => 36],
            [['descripcion'], 'string', 'max' => 300],
            [['unidadDefecto'], 'string', 'max' => 20],
            [['idCategoria'], 'exist', 'skipOnError' => true, 'targetClass' => Categoria::class, 'targetAttribute' => ['idCategoria' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idSigma' => 'ID Sigma',
            'idCategoria' => 'Categoría',
            'descripcion' => 'Descripción',
            'unidadDefecto' => 'Unidad por defecto',
            'activo' => 'Activo',
            'fechaRegistro' => 'Fecha de registro',
        ];
    }

    public function getCategoria(): ActiveQuery
    {
        return $this->hasOne(Categoria::class, ['id' => 'idCategoria']);
    }

    /**
     * idSigma es uniqueidentifier y permite NULL, pero el formulario envia
     * cadena vacia '' cuando se deja en blanco. SQL Server no puede convertir
     * '' a uniqueidentifier (a diferencia de una columna varchar), asi que
     * hay que normalizar a null antes de guardar.
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->idSigma === '') {
            $this->idSigma = null;
        }

        return true;
    }
}
