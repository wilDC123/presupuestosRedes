<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ProyectoCondicionPrevia": un TRABAJO
 * COMPLEMENTARIO (condicion o requerimiento previo) del proyecto: lo que debe
 * hacer OTRA unidad para que la DTIC pueda instalar (instalacion electrica,
 * apertura de ductos, despejar el ambiente...). No tiene precio: esa unidad
 * hace su propio presupuesto.
 *
 * Del 2026-10-23 al 29 era redes.PresupuestoComplemento (tipo trabajo), al
 * final del presupuesto, con dos subtitulos fijos. Desde la revision del Ing.
 * de DTIC del 2026-10-29 (paso 8) es del PROYECTO, se escribe debajo de la
 * lista (CondicionPreviaService) y el tipo sale de un catalogo que se puede
 * ampliar (TipoCondicionPrevia).
 *
 * @property int $id
 * @property int $idProyecto
 * @property int $idTipo
 * @property string $descripcion
 * @property string|null $unidadEncargada Texto libre ("Infraestructura", "Facultad"...).
 * @property int $orden
 *
 * @property-read TipoCondicionPrevia $tipo
 */
class ProyectoCondicionPrevia extends ActiveRecord
{
    /** Titulo de la seccion, tal como sale en pantalla y en el impreso (el de antes en el presupuesto). */
    public const TITULO = 'Trabajos complementarios que deberá ser realizado por la unidad y/o unidades encargadas';

    /** Titulo corto en la pantalla de la lista (2026-10-30); el oficial va en el impreso. */
    public const TITULO_PANTALLA = 'Trabajos complementarios';

    /** Se sugieren al escribir la unidad encargada (es texto libre). */
    public const UNIDADES_ENCARGADAS_SUGERIDAS = ['Infraestructura', 'Mantenimiento eléctrico', 'Facultad'];

    public static function tableName(): string
    {
        return 'redes.ProyectoCondicionPrevia';
    }

    public function rules(): array
    {
        return [
            [['descripcion', 'unidadEncargada'], 'trim'],
            [['idTipo', 'descripcion'], 'required'],
            [['idProyecto', 'idTipo', 'orden'], 'integer'],
            [['descripcion'], 'string', 'max' => 300],
            [['unidadEncargada'], 'string', 'max' => 150],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'idTipo' => 'Tipo',
            'descripcion' => 'Descripción',
            'unidadEncargada' => 'Unidad encargada',
        ];
    }

    public function getTipo(): ActiveQuery
    {
        return $this->hasOne(TipoCondicionPrevia::class, ['id' => 'idTipo']);
    }

    /**
     * Como llega del formulario y como se le devuelve.
     *
     * @return array{idTipo: string, descripcion: string, unidadEncargada: string}
     */
    public function fila(): array
    {
        return [
            'idTipo' => (string) $this->idTipo,
            'descripcion' => (string) $this->descripcion,
            'unidadEncargada' => (string) $this->unidadEncargada,
        ];
    }
}
