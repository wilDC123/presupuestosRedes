<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Especificacion;
use backend\models\EspecificacionCaracteristica;
use backend\models\EspecificacionVersion;
use RuntimeException;
use Throwable;
use Yii;

/**
 * Aplica un PLAN de orden a la libreria de especificaciones (2026-11-03): para
 * cada ficha, las filas como deben quedar (niveles, subtitulos y arreglos de
 * forma), revisadas antes por el encargado. Lo usa el comando
 * `php yii especificacion/ordenar`.
 *
 * Por que un plan y no una migracion: las fichas importadas tienen otros id en
 * cada base (local, servidor de la DTIC). Cada ficha del plan se reconoce por
 * su TITULO y una HUELLA del contenido que tenia cuando se armo el plan
 * (nombre y valor de sus filas, en orden). Si alguien la edito despues, la
 * huella ya no coincide y la ficha se salta: el plan nunca pisa un cambio que
 * no conoce.
 *
 * Solo se ordenan versiones SIN emisiones (se corrigen ahi mismo, con
 * EspecificacionService::guardarVersion()); una ficha cuya version actual ya
 * se emitio se salta (decidido el 2026-11-03: esas quedan para despues).
 */
class EspecificacionOrdenService
{
    public const ESTADO_APLICAR = 'se ordena';
    public const ESTADO_YA_APLICADA = 'ya estaba ordenada';
    public const ESTADO_EMITIDA = 'versión emitida: se salta';
    public const ESTADO_NO_ENCONTRADA = 'no se encontró (título o contenido distinto)';

    /**
     * Lee el archivo JSON del plan.
     *
     * @return array<int, array{titulo: string, huella: string, filas: array<int, array{nombre: ?string, valor: string, esSubtitulo: bool, nivel: int}>}>
     */
    public function leerPlan(string $archivo): array
    {
        if (!is_file($archivo)) {
            throw new RuntimeException("No existe el archivo $archivo.");
        }

        $datos = json_decode((string) file_get_contents($archivo), true);

        if (!is_array($datos) || !isset($datos['fichas']) || !is_array($datos['fichas'])) {
            throw new RuntimeException("$archivo no es un plan válido (falta \"fichas\").");
        }

        return $datos['fichas'];
    }

    /**
     * Huella del contenido de una version: nombre y valor de cada fila, en
     * orden. NO incluye nivel ni subtitulo (es justo lo que el plan cambia).
     *
     * @param array<int, array{nombre?: ?string, valor?: ?string}> $filas
     */
    public static function huella(array $filas): string
    {
        $limpio = static fn (?string $texto): string => trim(str_replace("\r\n", "\n", (string) $texto));

        return md5((string) json_encode(array_map(
            static fn (array $f) => [$limpio($f['nombre'] ?? null), $limpio($f['valor'] ?? null)],
            array_values($filas)
        ), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Que pasaria con cada ficha del plan. No escribe nada.
     *
     * @param array<int, array{titulo: string, huella: string, filas: array<int, array{nombre: ?string, valor: string, esSubtitulo: bool, nivel: int}>}> $plan
     * @return array<int, array{titulo: string, estado: string, especificacion: ?Especificacion, filas: array<int, array{nombre: ?string, valor: string, esSubtitulo: bool, nivel: int}>}>
     */
    public function analizar(array $plan): array
    {
        $resultado = [];

        foreach ($plan as $entrada) {
            $encontradas = 0;

            /** @var Especificacion[] $candidatas */
            $candidatas = Especificacion::find()->where(['titulo' => $entrada['titulo']])->orderBy('id')->all();

            foreach ($candidatas as $ficha) {
                $actual = $ficha->getVersionActual()->one();
                if (!$actual instanceof EspecificacionVersion) {
                    continue;
                }

                $filasActuales = $this->filasDe($actual);
                $estado = null;

                if ($this->mismasFilas($filasActuales, $entrada['filas'])) {
                    $estado = self::ESTADO_YA_APLICADA;
                } elseif (self::huella($filasActuales) === $entrada['huella']) {
                    $estado = $actual->estaBloqueada() ? self::ESTADO_EMITIDA : self::ESTADO_APLICAR;
                }

                if ($estado !== null) {
                    $encontradas++;
                    $resultado[] = ['titulo' => $entrada['titulo'], 'estado' => $estado, 'especificacion' => $ficha, 'filas' => $entrada['filas']];
                }
            }

            if ($encontradas === 0) {
                $resultado[] = ['titulo' => $entrada['titulo'], 'estado' => self::ESTADO_NO_ENCONTRADA, 'especificacion' => null, 'filas' => $entrada['filas']];
            }
        }

        return $resultado;
    }

    /**
     * Cuantas fichas hay en cada estado.
     *
     * @param array<int, array{estado: string}> $analisis
     * @return array<string, int>
     */
    public function resumen(array $analisis): array
    {
        $conteo = array_count_values(array_column($analisis, 'estado'));
        ksort($conteo);

        return $conteo;
    }

    /**
     * Ordena las fichas en estado ESTADO_APLICAR. Todo en UNA transaccion: si
     * una falla, no queda ninguna a medias. Devuelve cuantas se ordenaron.
     *
     * @param array<int, array{titulo: string, estado: string, especificacion: ?Especificacion, filas: array<int, array{nombre: ?string, valor: string, esSubtitulo: bool, nivel: int}>}> $analisis
     */
    public function aplicar(array $analisis): int
    {
        $especificaciones = new EspecificacionService();
        $ordenadas = 0;
        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach ($analisis as $fila) {
                if ($fila['estado'] !== self::ESTADO_APLICAR || $fila['especificacion'] === null) {
                    continue;
                }

                $resultado = $especificaciones->guardarVersion($fila['especificacion'], [], $fila['filas']);

                if ($resultado['errores'] !== []) {
                    throw new RuntimeException("\"{$fila['titulo']}\": " . implode(' ', $resultado['errores']));
                }
                if ($resultado['nueva']) {
                    // No deberia pasar (analizar() salta las emitidas): por las dudas.
                    throw new RuntimeException("\"{$fila['titulo']}\": se iba a crear una versión nueva.");
                }

                $ordenadas++;
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return $ordenadas;
    }

    /**
     * @return array<int, array{nombre: ?string, valor: string, esSubtitulo: bool, nivel: int}>
     */
    private function filasDe(EspecificacionVersion $version): array
    {
        return array_map(static fn (EspecificacionCaracteristica $c) => [
            'nombre' => $c->nombre,
            'valor' => $c->valor,
            'esSubtitulo' => (bool) $c->esSubtitulo,
            'nivel' => (int) $c->nivel,
        ], $version->caracteristicas);
    }

    /**
     * true si la version ya tiene exactamente las filas del plan (se aplico antes).
     *
     * @param array<int, array{nombre: ?string, valor: string, esSubtitulo: bool, nivel: int}> $actuales
     * @param array<int, array{nombre: ?string, valor: string, esSubtitulo: bool, nivel: int}> $plan
     */
    private function mismasFilas(array $actuales, array $plan): bool
    {
        $comparable = static fn (array $filas) => array_map(static fn (array $f) => [
            trim(str_replace("\r\n", "\n", (string) $f['nombre'])),
            trim(str_replace("\r\n", "\n", (string) $f['valor'])),
            (bool) $f['esSubtitulo'],
            (int) $f['nivel'],
        ], array_values($filas));

        return $comparable($actuales) === $comparable($plan);
    }
}
