<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.PresupuestoComplemento": una fila SIN PRECIO de
 * las secciones que van al final del PDF del presupuesto, despues del total.
 *
 *  - TIPO_MATERIAL: "Otros materiales y equipamiento que proveerá la
 *    D.T.I.C." (lo que la DTIC ya tiene y aporta).
 *  - TIPO_TRABAJO: "Trabajos complementarios..." (lo que hace OTRA unidad de
 *    la universidad, ej. mantenimiento electrico).
 *
 * No es un item de la lista: no se cotiza, no lleva especificacion ni
 * partida y no suma al total. Se edita en el paso Presupuesto, solo en
 * borrador (ElaborarPresupuestoService::guardarComplementos()).
 *
 * @property int $id
 * @property int $idPresupuesto
 * @property string $tipo Ver TIPOS.
 * @property string $grupo Subtitulo de la seccion en el PDF.
 * @property string $descripcion
 * @property string|null $cantidad Solo materiales.
 * @property string|null $unidadMedida Abreviatura (solo materiales).
 * @property int|null $idSubcatalogo Item del que se tomo el nombre (solo materiales, opcional).
 * @property string|null $unidadEncargada Solo trabajos.
 * @property string|null $observacion
 * @property int $orden
 *
 * @property-read Presupuesto $presupuesto
 * @property-read SubcatalogoItem|null $subcatalogoItem
 */
class PresupuestoComplemento extends ActiveRecord
{
    public const TIPO_MATERIAL = 'material';
    public const TIPO_TRABAJO = 'trabajo';

    /** Titulo de cada seccion, tal como sale en el PDF. */
    public const TIPOS = [
        self::TIPO_MATERIAL => 'Otros materiales y equipamiento que proveerá la D.T.I.C.',
        self::TIPO_TRABAJO => 'Trabajos complementarios que deberá ser realizado por la unidad y/o unidades encargadas',
    ];

    /** Subtitulos fijos de los trabajos (formato de la DTIC). */
    public const GRUPOS_TRABAJO = ['Instalaciones eléctricas', 'Otras obras civiles requeridas'];

    /** Subtitulo sugerido de los materiales (se puede escribir otro). */
    public const GRUPO_MATERIAL_SUGERIDO = 'Cableado y materiales de red';

    /** Unidades encargadas que se sugieren al escribir (texto libre). */
    public const UNIDADES_ENCARGADAS_SUGERIDAS = ['Mantenimiento eléctrico', 'Infraestructura'];

    public static function tableName(): string
    {
        return 'redes.PresupuestoComplemento';
    }

    public function rules(): array
    {
        return [
            [['grupo', 'descripcion', 'unidadMedida', 'unidadEncargada', 'observacion'], 'trim'],
            [['idPresupuesto', 'tipo', 'grupo', 'descripcion'], 'required'],
            [['tipo'], 'in', 'range' => array_keys(self::TIPOS)],
            [['idPresupuesto', 'idSubcatalogo', 'orden'], 'integer'],
            [['grupo', 'unidadEncargada'], 'string', 'max' => 150],
            [['descripcion', 'observacion'], 'string', 'max' => 300],
            [['unidadMedida'], 'string', 'max' => 10],
            // Materiales: cantidad mayor que cero.
            [['cantidad'], 'required', 'when' => fn () => $this->tipo === self::TIPO_MATERIAL],
            [['cantidad'], 'number', 'min' => 0.01, 'max' => 9999999999999999.99, 'tooSmall' => 'La cantidad debe ser mayor que cero.'],
            // Trabajos: solo uno de los subtitulos fijos.
            [['grupo'], 'in', 'range' => self::GRUPOS_TRABAJO, 'when' => fn () => $this->tipo === self::TIPO_TRABAJO],
            [['idSubcatalogo'], 'exist', 'skipOnError' => true, 'targetClass' => SubcatalogoItem::class, 'targetAttribute' => ['idSubcatalogo' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'tipo' => 'Sección',
            'grupo' => 'Subtítulo',
            'descripcion' => 'Descripción',
            'cantidad' => 'Cantidad',
            'unidadMedida' => 'Unidad',
            'idSubcatalogo' => 'Ítem del subcatálogo',
            'unidadEncargada' => 'Unidad encargada',
            'observacion' => 'Observaciones',
        ];
    }

    /**
     * Cada tipo guarda solo sus columnas: un trabajo no tiene cantidad ni
     * unidad de medida, un material no tiene unidad encargada. Asi un dato
     * que quedo escrito en el formulario no aparece donde no corresponde.
     */
    public function beforeValidate(): bool
    {
        $this->limpiarColumnasAjenas();

        return parent::beforeValidate();
    }

    /**
     * Otra vez antes de guardar: el validador "trim" convierte null en '',
     * y SQL Server no acepta '' en una columna decimal o entera.
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->limpiarColumnasAjenas();

        return true;
    }

    private function limpiarColumnasAjenas(): void
    {
        if ($this->tipo === self::TIPO_TRABAJO) {
            $this->cantidad = null;
            $this->unidadMedida = null;
            $this->idSubcatalogo = null;
        } else {
            $this->unidadEncargada = null;
        }

        foreach (['cantidad', 'unidadMedida', 'idSubcatalogo', 'unidadEncargada', 'observacion'] as $atributo) {
            if ($this->$atributo === '') {
                $this->$atributo = null;
            }
        }
    }

    public function getPresupuesto(): ActiveQuery
    {
        return $this->hasOne(Presupuesto::class, ['id' => 'idPresupuesto']);
    }

    public function getSubcatalogoItem(): ActiveQuery
    {
        return $this->hasOne(SubcatalogoItem::class, ['id' => 'idSubcatalogo']);
    }

    public function esMaterial(): bool
    {
        return $this->tipo === self::TIPO_MATERIAL;
    }
}
