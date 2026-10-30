<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ProyectoMaterialDtic": una fila de "Otros
 * materiales y equipamiento que proveerá la D.T.I.C." (lo que la DTIC ya
 * tiene y aporta). Sin precio: no se cotiza ni va al presupuesto.
 *
 * Del 2026-10-23 al 29 era redes.PresupuestoComplemento (tipo material), al
 * final del presupuesto. Desde la revision del Ing. de DTIC del 2026-10-29
 * (paso 8) es del PROYECTO y se escribe debajo de la lista de items, porque
 * el presupuesto es solo financiero. Mismas columnas que antes, sin
 * observaciones (quitadas el 2026-10-30). Las reglas
 * de la seccion completa estan en MaterialDticService.
 *
 * @property int $id
 * @property int $idProyecto
 * @property string $grupo Subtitulo: COPIA del nombre de la categoria del item
 *           ("Otros" o la elegida si se escribio a mano).
 * @property int|null $idSubcatalogo Item del que se tomo el nombre (opcional).
 * @property string $descripcion
 * @property string $cantidad
 * @property string|null $unidadMedida Abreviatura, como en ProyectoItem.
 * @property int $orden
 *
 * @property-read SubcatalogoItem|null $subcatalogoItem
 */
class ProyectoMaterialDtic extends ActiveRecord
{
    /** Titulo de la seccion, tal como sale en pantalla y en el impreso. */
    public const TITULO = 'Otros materiales y equipamiento que proveerá la D.T.I.C.';

    /** Titulo corto en la pantalla de la lista (2026-10-30); el oficial va en el impreso. */
    public const TITULO_PANTALLA = 'Materiales que aporta la DTIC';

    /**
     * Subtitulo de lo escrito a mano si no se elige categoria. Lo del
     * subcatalogo toma la categoria del item (MaterialDticService::armar()).
     */
    public const GRUPO_OTROS = 'Otros';

    public static function tableName(): string
    {
        return 'redes.ProyectoMaterialDtic';
    }

    public function rules(): array
    {
        return [
            [['grupo', 'descripcion', 'unidadMedida'], 'trim'],
            [['grupo', 'descripcion', 'cantidad'], 'required'],
            [['idProyecto', 'idSubcatalogo', 'orden'], 'integer'],
            [['grupo'], 'string', 'max' => 150],
            [['descripcion'], 'string', 'max' => 300],
            [['unidadMedida'], 'string', 'max' => 10],
            [['cantidad'], 'number', 'min' => 0.01, 'max' => 9999999999999999.99, 'tooSmall' => 'La cantidad debe ser mayor que cero.'],
            [['idSubcatalogo'], 'exist', 'skipOnError' => true, 'targetClass' => SubcatalogoItem::class, 'targetAttribute' => ['idSubcatalogo' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'grupo' => 'Subtítulo',
            'idSubcatalogo' => 'Ítem del subcatálogo',
            'descripcion' => 'Descripción',
            'cantidad' => 'Cantidad',
            'unidadMedida' => 'Unidad',
        ];
    }

    public function getSubcatalogoItem(): ActiveQuery
    {
        return $this->hasOne(SubcatalogoItem::class, ['id' => 'idSubcatalogo']);
    }

    /**
     * Como llega del formulario y como se le devuelve.
     *
     * @return array<string, string>
     */
    public function fila(): array
    {
        return array_map(static fn ($valor) => (string) $valor, $this->getAttributes(['grupo', 'idSubcatalogo', 'descripcion', 'cantidad', 'unidadMedida']));
    }
}
