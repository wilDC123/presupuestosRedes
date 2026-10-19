<?php

declare(strict_types=1);

namespace backend\models;

use RuntimeException;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ConfiguracionSolicitudCotizacion": UNA sola
 * fila (id = 1, lo garantiza un CHECK en la base) con los textos fijos del
 * documento de la solicitud de cotizacion, editables por el encargado.
 *
 * A diferencia del formulario NET, la solicitud no guarda copia de los
 * textos: el PDF se arma al imprimir, asi que un cambio aqui se ve tambien al
 * reimprimir solicitudes anteriores.
 *
 * @property int $id
 * @property string $textoCabecera Una linea por renglon; la primera sale en negrita.
 * @property string $textoInstrucciones
 * @property string|null $textoNotaFinal
 */
class ConfiguracionSolicitudCotizacion extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.ConfiguracionSolicitudCotizacion';
    }

    /**
     * La unica fila. La crea la migracion; si falta, la base esta incompleta
     * y es mejor detenerse con un mensaje claro que inventar valores.
     */
    public static function obtener(): self
    {
        $configuracion = self::findOne(1);

        if ($configuracion === null) {
            throw new RuntimeException('Falta la configuración de la solicitud de cotización (redes.ConfiguracionSolicitudCotizacion). Ejecute "php yii migrate".');
        }

        return $configuracion;
    }

    /**
     * Los renglones de la cabecera, sin lineas vacias.
     *
     * @return string[]
     */
    public function lineasCabecera(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->textoCabecera)), static fn (string $linea) => $linea !== ''));
    }

    public function rules(): array
    {
        return [
            [['textoCabecera', 'textoInstrucciones', 'textoNotaFinal'], 'trim'],
            [['textoNotaFinal'], 'default', 'value' => null],
            // Cabecera e instrucciones son obligatorias: el documento nunca
            // sale sin ellas.
            [['textoCabecera', 'textoInstrucciones'], 'required'],
            [['textoCabecera', 'textoNotaFinal'], 'string', 'max' => 500],
            [['textoInstrucciones'], 'string', 'max' => 1000],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'textoCabecera' => 'Cabecera',
            'textoInstrucciones' => 'Instrucciones al proveedor',
            'textoNotaFinal' => 'Nota final',
        ];
    }
}
