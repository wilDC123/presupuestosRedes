<?php

declare(strict_types=1);

namespace backend\models;

use RuntimeException;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ConfiguracionEspecificacion": UNA sola fila
 * (id = 1, lo garantiza un CHECK en la base) con lo que el encargado edita
 * para los formularios NET.
 *
 * Los textos son los valores por defecto de las emisiones NUEVAS: cada
 * emision guarda su propia copia, asi que cambiarlos aqui no altera los
 * documentos ya emitidos.
 *
 * @property int $id
 * @property int|null $siguienteNet NULL = todavia no se fijo el NET inicial (no se puede emitir).
 * @property string|null $textoCabecera
 * @property string|null $textoInstrucciones
 * @property string|null $garantiaPorDefecto
 * @property string|null $textoNotaFinal
 * @property int $vigenciaDias
 * @property int $validezOfertaDias
 * @property int $tiempoEntregaDias
 */
class ConfiguracionEspecificacion extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.ConfiguracionEspecificacion';
    }

    /**
     * La unica fila. La crea la migracion; si falta, la base esta incompleta
     * y es mejor detenerse con un mensaje claro que inventar valores.
     */
    public static function obtener(): self
    {
        $configuracion = self::findOne(1);

        if ($configuracion === null) {
            throw new RuntimeException('Falta la configuración de especificaciones (redes.ConfiguracionEspecificacion). Ejecute "php yii migrate".');
        }

        return $configuracion;
    }

    public function rules(): array
    {
        return [
            [['textoCabecera', 'textoInstrucciones', 'garantiaPorDefecto', 'textoNotaFinal'], 'trim'],
            [['textoCabecera', 'textoInstrucciones', 'garantiaPorDefecto', 'textoNotaFinal'], 'default', 'value' => null],
            [['vigenciaDias', 'validezOfertaDias', 'tiempoEntregaDias'], 'required'],
            [['siguienteNet', 'vigenciaDias', 'validezOfertaDias', 'tiempoEntregaDias'], 'integer', 'min' => 1],
            [['textoCabecera', 'garantiaPorDefecto', 'textoNotaFinal'], 'string', 'max' => 500],
            [['textoInstrucciones'], 'string', 'max' => 1000],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'siguienteNet' => 'Siguiente código NET',
            'textoCabecera' => 'Cabecera',
            'textoInstrucciones' => 'Instrucciones al proveedor',
            'garantiaPorDefecto' => 'Garantía por defecto',
            'textoNotaFinal' => 'Nota final',
            'vigenciaDias' => 'Vigencia del formulario (días)',
            'validezOfertaDias' => 'Validez de la oferta por defecto (días)',
            'tiempoEntregaDias' => 'Tiempo de entrega por defecto (días)',
        ];
    }
}
