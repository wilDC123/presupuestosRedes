<?php

declare(strict_types=1);

namespace backend\components;

/**
 * Las palabras de un texto de busqueda (buscador de especificaciones, de
 * areas de UsfxGEO y del catalogo SIGMA): cada palabra es una condicion con
 * sus propios parametros en la consulta.
 *
 * Se usan como mucho MAX_PALABRAS:
 * SQL Server acepta hasta 2100 parametros por consulta, y el buscador de
 * especificaciones usa 6 por palabra; pegar un texto largo daba error del
 * servidor (8003). Diez palabras sobran para encontrar algo.
 */
final class Busqueda
{
    public const MAX_PALABRAS = 10;

    /**
     * @return string[] las palabras (sin vacias), a lo sumo MAX_PALABRAS
     */
    public static function palabras(string $texto): array
    {
        $palabras = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY);

        return $palabras === false ? [] : array_slice($palabras, 0, self::MAX_PALABRAS);
    }
}
