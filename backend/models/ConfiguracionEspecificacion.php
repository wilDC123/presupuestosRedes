<?php

declare(strict_types=1);

namespace backend\models;

use RuntimeException;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ConfiguracionEspecificacion": UNA sola fila
 * (id = 1, lo garantiza un CHECK en la base) con lo que el encargado edita
 * para los formularios de especificacion tecnica.
 *
 * Los textos son los valores por defecto de las emisiones NUEVAS: cada
 * emision guarda su propia copia, asi que cambiarlos aqui no altera los
 * documentos ya emitidos.
 *
 * @property int $id
 * @property string|null $textoCabecera
 * @property string|null $textoInstrucciones
 * @property string|null $garantiaPorDefecto
 * @property string|null $textoNotaFinal
 * @property int $vigenciaDias
 * @property int $validezOfertaDias
 * @property int $tiempoEntregaDias
 * @property string|null $firma1Prefijo Control de elaboracion: firma 1 = elaborado por,
 * @property string|null $firma1Nombre  firma 2 = aprobado por (ver FirmasDeControl).
 * @property string|null $firma1Cargo
 * @property string|null $firma2Prefijo
 * @property string|null $firma2Nombre
 * @property string|null $firma2Cargo
 */
class ConfiguracionEspecificacion extends ActiveRecord
{
    use FirmasDeControl;

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
        return array_merge([
            [['textoCabecera', 'textoInstrucciones', 'garantiaPorDefecto', 'textoNotaFinal'], 'trim'],
            [['textoCabecera', 'textoInstrucciones', 'garantiaPorDefecto', 'textoNotaFinal'], 'default', 'value' => null],
            [['vigenciaDias', 'validezOfertaDias', 'tiempoEntregaDias'], 'required'],
            [['vigenciaDias', 'validezOfertaDias', 'tiempoEntregaDias'], 'integer', 'min' => 1],
            [['textoCabecera', 'garantiaPorDefecto', 'textoNotaFinal'], 'string', 'max' => 500],
            [['textoInstrucciones'], 'string', 'max' => 1000],
        ], $this->reglasFirmas());
    }

    public function attributeLabels(): array
    {
        return [
            'textoCabecera' => 'Cabecera',
            'textoInstrucciones' => 'Instrucciones al proveedor',
            'garantiaPorDefecto' => 'Garantía por defecto',
            'textoNotaFinal' => 'Nota final',
            'vigenciaDias' => 'Vigencia del formulario (días)',
            'validezOfertaDias' => 'Validez de la oferta por defecto (días)',
            'tiempoEntregaDias' => 'Tiempo de entrega por defecto (días)',
        ] + self::etiquetasFirmas();
    }
}
