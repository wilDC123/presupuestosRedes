<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.CorrelativoDocumento": el siguiente numero de
 * cada tipo de documento que codifica el sistema (una fila por tipo).
 *
 * Los codigos siguen el lineamiento de la DTIC RD-GD-LI-001: unidad (RD,
 * Redes y Telecomunicaciones) - proceso - tipo de documento - correlativo de
 * 3 cifras. Ej. RD-GR-ET-015:
 *
 *     RD - GR - ET - 015
 *     |    |    |    +------- correlativo de 3 cifras como minimo (desde 001; despues del 999 sigue con 1000)
 *     |    |    +------------ tipo: PT ficha tecnica del proyecto, LM lista de materiales, SC solicitud de proforma,
 *     |    |                         PR presupuesto, ET especificacion
 *     |    +----------------- proceso del proyecto (redes.Proceso); el PT siempre PI
 *     +---------------------- unidad: Redes y Telecomunicaciones
 *
 * Desde la revision del 2026-10-29 (paso 4) el proceso de PR y ET (y desde
 * el 2026-11-05 el de LM) es el del
 * proyecto, pero el numero es UNO SOLO para todos los procesos: nunca hay un
 * RD-PI-PR-016 y un RD-GR-PR-016. NINGUN codigo lleva el año: desde el
 * 2026-11-10 las ET tampoco (del 2026-10-29 al 11-10 eran "RD-PI-ET-015/26" y
 * volvian a 001 cada 1 de enero); su numero sigue subiendo sin reiniciarse.
 *
 * Los numeros los reparte CodificacionService.
 *
 * @property string $tipo Ver las constantes TIPO_*.
 * @property int $siguiente El numero que recibira el proximo documento.
 */
class CorrelativoDocumento extends ActiveRecord
{
    /**
     * Numero de proyecto (revision del 2026-11-05): no es un documento ni lleva
     * codigo del lineamiento, solo el numero ("001"), que comparten todas las
     * fases del proyecto. Se reparte con el mismo correlativo que los codigos.
     */
    public const TIPO_NUMERO_PROYECTO = 'NP';
    public const TIPO_PROYECTO = 'PT';
    /** Lista de materiales y condiciones previas (revision del 2026-11-05, punto 7; no esta en el lineamiento). */
    public const TIPO_LISTA = 'LM';
    /** Solicitud de proforma de cotizacion (formatos corregidos v3, 2026-11-09; no esta en el lineamiento). */
    public const TIPO_SOLICITUD = 'SC';
    public const TIPO_PRESUPUESTO = 'PR';
    public const TIPO_ESPECIFICACION = 'ET';

    public const UNIDAD = 'RD';

    /**
     * Proceso de los proyectos (PT) y el que se usa si el proyecto no tiene
     * proceso o un codigo de antes no lo trae.
     */
    public const PROCESO = 'PI';

    /**
     * Nombre del proceso PI en la tabla de codificacion de procesos del
     * lineamiento. Respaldo de Proceso::nombreDe() para la cabecera de los
     * impresos.
     */
    public const NOMBRE_PROCESO = 'Proyectos de infraestructura de redes y servicios';

    public static function tableName(): string
    {
        return 'redes.CorrelativoDocumento';
    }

    /**
     * Los tipos, en el orden en que se muestran, con su nombre segun la
     * tabla de codigos del lineamiento (LM no esta en ella: es la propuesta de
     * la revision del 2026-11-05).
     *
     * @return array<string, string>
     */
    public static function tipos(): array
    {
        return [
            self::TIPO_NUMERO_PROYECTO => 'Número de proyecto (no es un documento)',
            // El PT es de la FICHA, no del proyecto: el lineamiento cambiara "Proyecto tecnico" por
            // "Ficha tecnica del proyecto" (aclarado por el desarrollador el 2026-11-05).
            self::TIPO_PROYECTO => 'Fichas técnicas del proyecto',
            self::TIPO_LISTA => 'Listas de materiales',
            self::TIPO_SOLICITUD => 'Solicitudes de proforma de cotización',
            self::TIPO_PRESUPUESTO => 'Presupuestos',
            self::TIPO_ESPECIFICACION => 'Especificaciones técnicas',
        ];
    }

    public function rules(): array
    {
        return [
            [['siguiente'], 'required', 'message' => 'Escriba el siguiente número.'],
            [['siguiente'], 'integer', 'min' => 1],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'tipo' => 'Tipo de documento',
            'siguiente' => 'Siguiente número',
        ];
    }

    /**
     * "RD-PI-PR-007" o "RD-GR-ET-007". Tres cifras como minimo; despues del
     * 999 sigue con 1000, 1001... (el lineamiento no dice que hacer y asi
     * nunca se repite uno). Sin año (2026-11-10).
     */
    public static function codigo(string $tipo, int $numero, string $proceso = self::PROCESO): string
    {
        // El numero de proyecto no es un codigo del lineamiento: solo "001".
        if ($tipo === self::TIPO_NUMERO_PROYECTO) {
            return self::numeroTexto($numero);
        }

        return sprintf('%s-%s-%s-%03d', self::UNIDAD, $proceso, $tipo, $numero);
    }

    /**
     * "007": el numero sin unidad, proceso ni tipo. Para los mensajes y la
     * pantalla de codificacion, donde el proceso depende de cada proyecto.
     */
    public static function numeroTexto(int $numero): string
    {
        return sprintf('%03d', $numero);
    }

    /**
     * Las partes de un codigo de ese tipo, con cualquier proceso:
     * "RD-GR-ET-015" -> ['proceso' => 'GR', 'numero' => 15, 'anio' => null].
     * Todavia reconoce el año de los formularios ET impresos del 2026-10-29 al
     * 11-10 ("RD-GR-ET-015/26" -> 'anio' => 26), para buscarlos con lo que
     * dice el papel. Null si el texto no es un codigo de ese tipo.
     *
     * @return array{proceso: string, numero: int, anio: int|null}|null
     */
    public static function partesDe(string $tipo, ?string $codigo): ?array
    {
        $patron = '/^' . preg_quote(self::UNIDAD, '/') . '-([A-Z]{2})-' . preg_quote($tipo, '/') . '-(\d+)(?:\/(\d{2}))?$/';

        if (preg_match($patron, trim((string) $codigo), $partes) !== 1) {
            return null;
        }

        return [
            'proceso' => $partes[1],
            'numero' => (int) $partes[2],
            'anio' => isset($partes[3]) ? (int) $partes[3] : null,
        ];
    }

    /**
     * El numero de un codigo de ese tipo ("RD-GR-PR-004" -> 4), o null si el
     * texto no es un codigo de ese tipo.
     */
    public static function numeroDe(string $tipo, ?string $codigo): ?int
    {
        return self::partesDe($tipo, $codigo)['numero'] ?? null;
    }

    public function nombreTipo(): string
    {
        return self::tipos()[$this->tipo] ?? $this->tipo;
    }
}
