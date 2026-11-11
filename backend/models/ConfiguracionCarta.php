<?php

declare(strict_types=1);

namespace backend\models;

use RuntimeException;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ConfiguracionCarta": UNA sola fila (id = 1,
 * lo garantiza un CHECK en la base) con los textos POR DEFECTO de la carta
 * de entrega del presupuesto. Editable por el encargado (carta/configuracion).
 *
 * Solo sirven para llenar una carta NUEVA (CartaService::nueva()): cada
 * carta guarda su propia copia, asi que cambiar esto no altera las cartas
 * ya escritas. Excepcion: ciudad y formatoOficio son formato, no contenido,
 * y se leen al imprimir.
 *
 * Marcadores que se reemplazan (ver CartaService::MARCADORES):
 *  - al llenar la carta nueva: {proyecto}, {unidad} (la unidad solicitante;
 *    {facultad} es el nombre de antes y se reemplaza igual) y {dependencia}
 *    (la unidad beneficiaria)
 *  - al imprimir: {oficio}, {numeroPresupuesto} (y {anio} en formatoOficio)
 *
 * @property int $id
 * @property string|null $ciudad
 * @property string|null $formatoOficio ej. "DTIC Of. Nº {oficio}/{anio}"
 * @property string|null $saludo
 * @property string|null $plantillaReferencia
 * @property string|null $plantillaCuerpo Parrafos separados por una linea en blanco.
 * @property string|null $despedida
 * @property string|null $copia
 * @property string|null $plantillaAdjunto
 * @property string|null $firma1Prefijo
 * @property string|null $firma1Nombre
 * @property string|null $firma1Cargo
 * @property string|null $firma2Prefijo
 * @property string|null $firma2Nombre
 * @property string|null $firma2Cargo
 * @property string|null $firma3Prefijo
 * @property string|null $firma3Nombre
 * @property string|null $firma3Cargo
 */
class ConfiguracionCarta extends ActiveRecord
{
    use FirmasDeCarta;

    /**
     * Filas de la tabla "Tratamientos" del formulario (no es una columna:
     * van a redes.TratamientoCarta). null = no llegaron y no se tocan; si
     * llegan, la validacion las deja como lista de
     * ['nombre' => string, 'femenino' => bool], sin las filas en blanco.
     * Ver CartaService::guardarConfiguracion().
     *
     * Sin tipo en PHP: load() le puede poner '' (la tabla sin filas).
     *
     * @var array<int|string, mixed>|string|null
     */
    public $tratamientos = null;

    private const TEXTOS = ['ciudad', 'formatoOficio', 'saludo', 'plantillaReferencia', 'plantillaCuerpo', 'despedida', 'copia', 'plantillaAdjunto'];

    public static function tableName(): string
    {
        return 'redes.ConfiguracionCarta';
    }

    /**
     * La unica fila. La crea la migracion; si falta, la base esta incompleta.
     */
    public static function obtener(): self
    {
        $configuracion = self::findOne(1);

        if ($configuracion === null) {
            throw new RuntimeException('Falta la configuración de la carta (redes.ConfiguracionCarta). Ejecute "php yii migrate".');
        }

        return $configuracion;
    }

    public function rules(): array
    {
        return array_merge([
            [self::TEXTOS, 'trim'],
            [self::TEXTOS, 'default', 'value' => null],
            // La referencia sale en mayusculas, como en la carta modelo, sin
            // tocar los marcadores ({proyecto} sigue siendo {proyecto}).
            [['plantillaReferencia'], 'filter', 'filter' => [CartaPresupuesto::class, 'mayusculas']],
            [['ciudad'], 'string', 'max' => 60],
            [['formatoOficio'], 'string', 'max' => 100],
            [['saludo', 'despedida'], 'string', 'max' => 150],
            [['plantillaReferencia'], 'string', 'max' => 500],
            [['plantillaCuerpo'], 'string', 'max' => 4000],
            [['copia'], 'string', 'max' => 200],
            [['plantillaAdjunto'], 'string', 'max' => 300],
            [['tratamientos'], 'validarTratamientos', 'skipOnEmpty' => false],
        ], $this->reglasFirmas());
    }

    public function attributeLabels(): array
    {
        return [
            'ciudad' => 'Ciudad',
            'formatoOficio' => 'Número de oficio',
            'saludo' => 'Saludo',
            'plantillaReferencia' => 'Referencia',
            'plantillaCuerpo' => 'Cuerpo de la carta',
            'despedida' => 'Despedida',
            'copia' => 'Copia',
            'plantillaAdjunto' => 'Adjunto',
            'tratamientos' => 'Tratamientos',
        ] + self::etiquetasFirmas();
    }

    /**
     * Las filas actuales de redes.TratamientoCarta, para llenar el formulario.
     */
    public function cargarTratamientos(): void
    {
        $this->tratamientos = [];
        foreach (TratamientoCarta::lista() as $tratamiento) {
            $this->tratamientos[] = ['nombre' => $tratamiento->nombre, 'femenino' => (bool) $tratamiento->femenino];
        }
    }

    /**
     * Normaliza las filas (sin espacios de mas, sin las vacias, en el orden
     * en que llegaron) y exige al menos uno, sin repetidos (sin importar
     * mayusculas) y de hasta 20 caracteres (el largo de
     * CartaPresupuesto.tratamiento).
     */
    public function validarTratamientos(string $atributo): void
    {
        if ($this->tratamientos === null) {
            return;
        }

        $filas = [];
        $vistos = [];
        foreach (is_array($this->tratamientos) ? $this->tratamientos : [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $nombre = trim(preg_replace('/\s+/u', ' ', (string) ($fila['nombre'] ?? '')));
            if ($nombre === '') {
                continue;
            }
            if (mb_strlen($nombre, 'UTF-8') > 20) {
                $this->addError($atributo, "\"{$nombre}\" es muy largo: un tratamiento tiene hasta 20 caracteres.");

                return;
            }
            $clave = mb_strtolower($nombre, 'UTF-8');
            if (isset($vistos[$clave])) {
                $this->addError($atributo, "\"{$nombre}\" está repetido.");

                return;
            }
            $vistos[$clave] = true;
            $filas[] = ['nombre' => $nombre, 'femenino' => !empty($fila['femenino'])];
        }

        if ($filas === []) {
            $this->addError($atributo, 'Escriba al menos un tratamiento.');

            return;
        }

        $this->tratamientos = $filas;
    }
}
