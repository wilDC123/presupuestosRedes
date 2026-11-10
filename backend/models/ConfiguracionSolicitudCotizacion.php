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
 * @property string $textoCabecera Ya no se usa (2026-11-09): la cabecera del PDF es la de todos los documentos.
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

    public function rules(): array
    {
        return [
            [['textoCabecera', 'textoInstrucciones', 'textoNotaFinal'], 'trim'],
            [['textoNotaFinal'], 'default', 'value' => null],
            // Las instrucciones son obligatorias: el documento nunca sale sin
            // ellas. textoCabecera ya no sale en el PDF (2026-11-09: la cabecera
            // es la de todos los documentos).
            [['textoInstrucciones'], 'required'],
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
