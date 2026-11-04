<?php

declare(strict_types=1);

namespace backend\components;

/**
 * Numeracion del formulario de especificacion (2026-11-03). Es el UNICO lugar
 * que decide los numeros: los usan el PDF (especificacion/formulario-pdf.php),
 * la ficha de la libreria (view.php) y la ventana "Ver detalle"; el formulario
 * de edicion los repite en el navegador (web/js/especificacion-form.js) con la
 * misma regla, solo para mostrarlos mientras se escribe.
 *
 * Los numeros NO se guardan: salen del orden y del nivel de las filas, asi
 * que no pueden saltarse ni repetirse.
 *
 * Regla, dentro de una seccion N (I = 1, II = 2...):
 *  - Las filas van N.1, N.2, N.3...
 *  - En la seccion I, N.1 y N.2 son "marca y modelo" y "URL", que agrega
 *    siempre el formulario (FILAS_FIJAS): las caracteristicas empiezan en 1.3.
 *  - Cada fila tiene su NIVEL (1, 2 o 3; EspecificacionCaracteristica.nivel),
 *    como en un indice: una de nivel 2 va debajo de la ultima de nivel 1
 *    (1.4 Hardware -> 1.4.1, 1.4.2...), una de nivel 3 debajo de la ultima
 *    de nivel 2 (1.4.2.1) y una de nivel 1 cierra el grupo (1.5).
 *  - Un SUBTITULO (esSubtitulo) es una fila de un solo texto, en negrita; el
 *    nivel no depende de el (lo normal es que las de abajo vayan un nivel
 *    mas adentro).
 */
final class NumeracionEspecificacion
{
    /** Filas que el formulario agrega antes de las caracteristicas: marca y modelo, URL. */
    public const FILAS_FIJAS = 2;

    /** Niveles de una fila: 1 = N.n, 2 = N.n.m, 3 = N.n.m.k. */
    public const NIVEL_MAXIMO = 3;

    /**
     * Numera las caracteristicas de la seccion I (o de la seccion que se pida).
     *
     * @param array<int, array{nombre?: ?string, valor?: ?string, esSubtitulo?: mixed, nivel?: mixed}> $filas en orden
     * @return array<int, array{numero: string, nivel: int, esSubtitulo: bool, nombre: ?string, valor: string}>
     *         nivel: el que se usa para numerar (ver niveles())
     */
    public static function caracteristicas(array $filas, int $seccion = 1, int $desde = self::FILAS_FIJAS): array
    {
        $niveles = self::niveles(array_map(static fn (array $f) => $f['nivel'] ?? 1, $filas));
        // $contadores[n] = numero de la ultima fila de nivel n.
        $contadores = [1 => $desde];
        $resultado = [];

        foreach (array_values($filas) as $i => $fila) {
            $nivel = $niveles[$i];
            $contadores[$nivel] = ($contadores[$nivel] ?? 0) + 1;
            // Una fila nueva de este nivel cierra los grupos que estaban debajo.
            for ($n = $nivel + 1; $n <= self::NIVEL_MAXIMO; $n++) {
                unset($contadores[$n]);
            }

            $esSubtitulo = !empty($fila['esSubtitulo']);
            $nombre = !$esSubtitulo && isset($fila['nombre']) && trim((string) $fila['nombre']) !== '' ? (string) $fila['nombre'] : null;
            $valor = (string) ($fila['valor'] ?? '');

            $resultado[] = [
                'numero' => $seccion . '.' . implode('.', array_slice($contadores, 0, $nivel)),
                'nivel' => $nivel,
                'esSubtitulo' => $esSubtitulo,
                'nombre' => $nombre,
                'valor' => $esSubtitulo ? self::sinNumeral($valor) : $valor,
            ];
        }

        return $resultado;
    }

    /**
     * El nivel con que se numera cada fila, a partir del que se pidio: entre 1
     * y NIVEL_MAXIMO, la primera fila en 1 y ninguna mas de un nivel por
     * debajo de la anterior (una fila de nivel 3 sin una de nivel 2 arriba se
     * toma como 2). Es la misma correccion que hace el servidor al guardar y
     * el formulario al mostrar: no hay numeros como 1.4.0.1.
     *
     * @param array<int, mixed> $pedidos en orden
     * @return int[]
     */
    public static function niveles(array $pedidos): array
    {
        $niveles = [];
        $anterior = 0;

        foreach (array_values($pedidos) as $pedido) {
            $nivel = max(1, min((int) $pedido, $anterior + 1, self::NIVEL_MAXIMO));
            $niveles[] = $nivel;
            $anterior = $nivel;
        }

        return $niveles;
    }

    /**
     * Niveles para filas que solo traen subtitulos marcados (importacion de un
     * Word, migracion de lo que habia): un subtitulo en 1 y las filas que le
     * siguen en 2, hasta el proximo subtitulo. Es como se numeraba antes de
     * que cada fila tuviera su nivel; despues se corrige a mano.
     *
     * @param array<int, mixed> $esSubtitulo en orden
     * @return int[]
     */
    public static function nivelesPorSubtitulo(array $esSubtitulo): array
    {
        $niveles = [];
        $enGrupo = false;

        foreach (array_values($esSubtitulo) as $subtitulo) {
            if (!empty($subtitulo)) {
                $enGrupo = true;
                $niveles[] = 1;
            } else {
                $niveles[] = $enGrupo ? 2 : 1;
            }
        }

        return $niveles;
    }

    /**
     * Un texto de varias lineas (la garantia) como filas: una por linea no
     * vacia, sin el numero que traia del Word ("1.Garantías de buen
     * funcionamiento..." -> "Garantías de buen funcionamiento...").
     *
     * @return string[]
     */
    public static function lineas(?string $texto): array
    {
        $lineas = preg_split('/\R/u', (string) $texto) ?: [];
        $lineas = array_map(static fn (string $l) => self::sinNumeral(trim($l)), $lineas);

        return array_values(array_filter($lineas, static fn (string $l) => $l !== ''));
    }

    /**
     * Quita un numero escrito a mano al principio ("II. MEDIO AMBIENTE",
     * "II MEDIO AMBIENTE", "1.Garantías", "2) Soporte"), porque el sistema
     * pone el suyo. Solo si despues viene una letra: "802.11n" o "24 V" no se
     * tocan.
     */
    public static function sinNumeral(string $texto): string
    {
        $limpio = preg_replace('/^\s*(?:[IVX]+\s*[.)\-–]*\s+|[IVX]+\s*[.)\-–]+\s*|\d+\s*[.)\-–]+\s*)(?=\p{L})/u', '', $texto);

        return $limpio === null || $limpio === '' ? $texto : $limpio;
    }

    /**
     * Numero romano de una seccion (1 -> I, 4 -> IV).
     */
    public static function romano(int $numero): string
    {
        $simbolos = [10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
        $texto = '';

        foreach ($simbolos as $valor => $simbolo) {
            while ($numero >= $valor) {
                $texto .= $simbolo;
                $numero -= $valor;
            }
        }

        return $texto;
    }
}
