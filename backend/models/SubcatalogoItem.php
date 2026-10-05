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
 * @property string|null $partida Partida presupuestaria (5 digitos, ej. 39700).
 *
 * @property-read Categoria $categoria
 * @property-read CatalogoSigma|null $catalogoSigma
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
            // Sin esta regla, un ID Sigma mal escrito llega a SQL Server y
            // falla con "Conversion failed ... to uniqueidentifier" (error 500).
            [['idSigma'], 'match', 'pattern' => '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', 'message' => 'El ID Sigma no tiene el formato del catálogo (ej. 82ABD46D-EDFA-4A4C-BDF8-004414DF97EE).'],
            [['descripcion'], 'string', 'max' => 300],
            [['unidadDefecto'], 'string', 'max' => 20],
            // La partida es obligatoria SIEMPRE al guardar. Cuando el item
            // viene de SIGMA, SubcatalogoItemService la copia desde
            // dbo.CatalogoSigma.IdGasto antes de save(), asi que esta regla
            // solo llega a fallar cuando no se pudo copiar (item sin idSigma,
            // ej. mano de obra, o SIGMA sin partida) y el usuario no la
            // escribio a mano.
            [['partida'], 'required', 'message' => 'Ingrese la partida presupuestaria: no se pudo obtener del catálogo SIGMA para este ítem.'],
            [['partida'], 'match', 'pattern' => '/^\d{5}$/', 'message' => 'La partida debe tener exactamente 5 dígitos (ej. 39700).'],
            [['idCategoria'], 'exist', 'skipOnError' => true, 'targetClass' => Categoria::class, 'targetAttribute' => ['idCategoria' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idSigma' => 'ID Sigma',
            'idCategoria' => 'Categoría',
            'descripcion' => 'Nombre DTIC',
            'unidadDefecto' => 'Unidad por defecto',
            'activo' => 'Activo',
            'fechaRegistro' => 'Fecha de registro',
            'partida' => 'Partida',
        ];
    }

    public function getCategoria(): ActiveQuery
    {
        return $this->hasOne(Categoria::class, ['id' => 'idCategoria']);
    }

    /**
     * Fila de dbo.CatalogoSigma de la que proviene este item (null si el
     * item no viene de SIGMA). Sirve para mostrar el nombre oficial SIGMA sin
     * copiarlo a redes.SubcatalogoItem. Es una relacion solo a nivel de Yii:
     * en la base NO hay foreign key (referencia logica).
     */
    public function getCatalogoSigma(): ActiveQuery
    {
        return $this->hasOne(CatalogoSigma::class, ['IdSigma' => 'idSigma']);
    }

    /**
     * idSigma es uniqueidentifier y permite NULL, pero el formulario envia
     * cadena vacia '' cuando se deja en blanco. SQL Server no puede convertir
     * '' a uniqueidentifier (a diferencia de una columna varchar), asi que
     * hay que normalizar a null antes de guardar. Lo mismo con partida, para
     * no guardar '' en una columna que representa "sin partida" con NULL.
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->idSigma === '') {
            $this->idSigma = null;
        }

        if ($this->partida === '') {
            $this->partida = null;
        }

        return true;
    }
}
