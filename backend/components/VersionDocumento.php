<?php

declare(strict_types=1);

namespace backend\components;

/**
 * Como se ESCRIBE la version de un documento, en UN solo lugar (revision del
 * Ing. de DTIC del 2026-11-05, punto 8: "la version se escribe 1.0", y un
 * cambio menor da la 1.1).
 *
 * En la base la version sigue siendo un numero corrido (1, 2, 3...:
 * EspecificacionEmision.versionDocumento, ListaMaterialesAprobacion.version);
 * solo cambia como se muestra: la 1 es "1.0", la 2 "1.1", la 3 "1.2"...
 * Un cambio importante no es una version nueva sino OTRO documento, con otro
 * codigo (asi trabaja ya la reemision), por eso el "1." no sube nunca.
 * Antes se escribia "01", "02".
 */
class VersionDocumento
{
    /** 1 -> "1.0", 2 -> "1.1"... (menos de 1 se toma como la primera). */
    public static function texto(int $numero): string
    {
        return '1.' . (max($numero, 1) - 1);
    }

    /**
     * Lo contrario, para los buscadores: "1.1" -> 2. Acepta tambien como se
     * escribia antes ("02", "2" -> 2), porque esta en papeles ya impresos.
     * null si no es una version.
     */
    public static function numeroDe(string $texto): ?int
    {
        $texto = trim($texto);

        if (preg_match('/^1\.(\d{1,3})$/', $texto, $m) === 1) {
            return (int) $m[1] + 1;
        }

        if (preg_match('/^\d{1,3}$/', $texto) === 1 && (int) $texto > 0) {
            return (int) $texto;
        }

        return null;
    }
}
