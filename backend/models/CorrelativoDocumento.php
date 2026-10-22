<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.CorrelativoDocumento": el siguiente numero de
 * cada tipo de documento que codifica el sistema (una fila por tipo).
 *
 * Los codigos siguen el lineamiento de la DTIC RD-GD-LI-001: unidad (RD,
 * Redes y Telecomunicaciones) - proceso (PI, Proyectos de infraestructura de
 * redes y servicios) - tipo de documento - correlativo de 3 cifras. Ej.
 * RD-PI-PR-015:
 *
 *     RD - PI - PR - 015
 *     |    |    |    +-- correlativo de 3 cifras (desde 001)
 *     |    |    +------- tipo: PT proyecto, PR presupuesto, ET especificacion
 *     |    +------------ proceso: Proyectos de infraestructura de redes y servicios
 *     +----------------- unidad: Redes y Telecomunicaciones
 *
 * Los numeros los reparte CodificacionService.
 *
 * @property string $tipo Ver las constantes TIPO_*.
 * @property int $siguiente El numero que recibira el proximo documento.
 */
class CorrelativoDocumento extends ActiveRecord
{
    public const TIPO_PROYECTO = 'PT';
    public const TIPO_PRESUPUESTO = 'PR';
    public const TIPO_ESPECIFICACION = 'ET';

    public const UNIDAD = 'RD';
    public const PROCESO = 'PI';

    /**
     * Nombre del proceso PI en la tabla de codificacion de procesos del
     * lineamiento. Sale en la cabecera de los impresos.
     */
    public const NOMBRE_PROCESO = 'Proyectos de infraestructura de redes y servicios';

    public static function tableName(): string
    {
        return 'redes.CorrelativoDocumento';
    }

    /**
     * Los tres tipos, en el orden en que se muestran, con su nombre segun la
     * tabla de codigos del lineamiento.
     *
     * @return array<string, string>
     */
    public static function tipos(): array
    {
        return [
            self::TIPO_PROYECTO => 'Proyectos técnicos',
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
     * "RD-PI-ET-007". Tres cifras como minimo; despues del 999 sigue con
     * 1000 (el lineamiento no dice que hacer y asi nunca se repite uno).
     */
    public static function codigo(string $tipo, int $numero): string
    {
        return sprintf('%s-%s-%s-%03d', self::UNIDAD, self::PROCESO, $tipo, $numero);
    }

    /**
     * El numero de un codigo de ese tipo ("RD-PI-PT-004" -> 4), o null si el
     * texto no es un codigo de ese tipo.
     */
    public static function numeroDe(string $tipo, ?string $codigo): ?int
    {
        $prefijo = preg_quote(self::UNIDAD . '-' . self::PROCESO . '-' . $tipo . '-', '/');

        return preg_match('/^' . $prefijo . '(\d+)$/', trim((string) $codigo), $partes) === 1 ? (int) $partes[1] : null;
    }

    public function nombreTipo(): string
    {
        return self::tipos()[$this->tipo] ?? $this->tipo;
    }
}
