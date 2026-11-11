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
 * @property string|null $textoCabecera La universidad y "D.T.I.C.", una linea por renglon: desde el 2026-11-10 sale
 *           DEBAJO de la cabecera del formulario (como el Word v3; antes, del 22-10 al 09-11, dentro de la cabecera).
 *           Cada emision guarda su copia.
 * @property string|null $textoInstrucciones
 * @property string|null $garantiaPorDefecto
 * @property string|null $textoNotaFinal
 * @property int $vigenciaDias
 * @property int $validezOfertaDias
 * @property int $tiempoEntregaDias
 * @property string|null $lugarEntregaPorDefecto Se copia a cada emision nueva (vacio = no sale).
 * @property string|null $firma1Prefijo Control de elaboracion: firma 1 = elaborado por,
 * @property string|null $firma1Nombre  firma 2 = aprobado por (ver FirmasDeControl).
 * @property string|null $firma1Cargo
 * @property string|null $firma2Prefijo
 * @property string|null $firma2Nombre
 * @property string|null $firma2Cargo
 */
class ConfiguracionEspecificacion extends ActiveRecord
{
    /** Nombre de la universidad como en el Word v3 del formulario (2026-11-10): primera linea de textoCabecera. */
    public const UNIVERSIDAD = 'Universidad Mayor, Real y Pontificia de “San Francisco Xavier” de Chuquisaca';

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
            [['textoCabecera', 'textoInstrucciones', 'garantiaPorDefecto', 'textoNotaFinal', 'lugarEntregaPorDefecto'], 'trim'],
            [['textoCabecera', 'textoInstrucciones', 'garantiaPorDefecto', 'textoNotaFinal', 'lugarEntregaPorDefecto'], 'default', 'value' => null],
            [['vigenciaDias', 'validezOfertaDias', 'tiempoEntregaDias'], 'required'],
            [['vigenciaDias', 'validezOfertaDias', 'tiempoEntregaDias'], 'integer', 'min' => 1],
            [['textoCabecera', 'garantiaPorDefecto', 'textoNotaFinal'], 'string', 'max' => 500],
            [['textoInstrucciones'], 'string', 'max' => 1000],
            [['lugarEntregaPorDefecto'], 'string', 'max' => 300],
        ], $this->reglasFirmas());
    }

    public function attributeLabels(): array
    {
        return [
            'textoCabecera' => 'Universidad y unidad (debajo de la cabecera)',
            'textoInstrucciones' => 'Instrucciones al proveedor',
            'garantiaPorDefecto' => 'Garantía por defecto',
            'textoNotaFinal' => 'Nota final',
            'vigenciaDias' => 'Vigencia del formulario (días)',
            'validezOfertaDias' => 'Validez de la oferta por defecto (días)',
            'tiempoEntregaDias' => 'Tiempo de entrega por defecto (días)',
            'lugarEntregaPorDefecto' => 'Lugar de entrega por defecto',
        ] + self::etiquetasFirmas();
    }
}
