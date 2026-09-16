<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ComputoMetrico".
 *
 * REGLA DE NEGOCIO: el sistema JAMAS calcula estos valores (no analiza
 * planos ni relevamientos). Este modelo solo almacena un valor ingresado a
 * mano junto con la referencia del documento que lo sustenta.
 *
 * @property int $id
 * @property int $idProyecto
 * @property string|null $descripcion
 * @property string|null $valor
 * @property string|null $unidad
 * @property string|null $referenciaOrigen
 *
 * @property-read Proyecto $proyecto
 */
class ComputoMetrico extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.ComputoMetrico';
    }

    public function rules(): array
    {
        return [
            [['idProyecto'], 'required'],
            [['idProyecto'], 'integer'],
            [['valor'], 'number'],
            [['descripcion'], 'string', 'max' => 300],
            [['unidad'], 'string', 'max' => 20],
            [['referenciaOrigen'], 'string', 'max' => 500],
            [['idProyecto'], 'exist', 'skipOnError' => true, 'targetClass' => Proyecto::class, 'targetAttribute' => ['idProyecto' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idProyecto' => 'Proyecto',
            'descripcion' => 'Descripción',
            'valor' => 'Valor',
            'unidad' => 'Unidad',
            'referenciaOrigen' => 'Referencia de origen',
        ];
    }

    public function getProyecto(): ActiveQuery
    {
        return $this->hasOne(Proyecto::class, ['id' => 'idProyecto']);
    }
}
