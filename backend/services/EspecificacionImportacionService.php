<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\EspecificacionMetadataExtractor;
use backend\models\Especificacion;
use RuntimeException;
use Throwable;
use Yii;

/**
 * Carga inicial de la libreria desde el "Banco de especificaciones" de DTIC
 * (la carpeta de .docx del Drive del Ing.). Lo usa el comando de consola
 * `php yii especificacion/importar`.
 *
 * El banco no tiene una ficha por archivo: tiene cientos de EMISIONES de unas
 * pocas fichas (el mismo "Cable de red Categoria 6" emitido decenas de veces
 * con otro NET, otra cantidad y otra dependencia). Por eso:
 *  - se agrupan los archivos por FAMILIA: mismo titulo y mismo modelo
 *    sugerido (sin importar mayusculas, acentos ni espacios),
 *  - de cada familia se toma la emision MAS RECIENTE (año y numero del NET),
 *    que es la version que DTIC usa hoy,
 *  - se descartan los archivos marcados como no usados en el nombre ("NO",
 *    "NOOO", "NO ENVIADO", "NO EJECUTADO"...) y los que no tienen tabla de
 *    caracteristicas,
 *  - no se crea una ficha que ya exista en la libreria (misma familia): el
 *    comando se puede volver a correr sin duplicar nada,
 *  - opcionalmente, no se crean las familias cuya ultima emision es anterior
 *    a un año (productos que DTIC ya no pide),
 *  - si varias fichas quedan con el mismo titulo (hay muchas "Antena wifi"
 *    de distintos modelos), se les agrega el modelo: "Antena wifi — EAP225".
 *
 * Las emisiones antiguas NO se importan (quedan en el Excel y en Drive). La
 * categoria queda vacia: se asigna desde la pantalla de la libreria.
 */
class EspecificacionImportacionService
{
    public const ACCION_CREAR = 'crear';
    public const ACCION_YA_EXISTE = 'ya existe en la librería';
    public const ACCION_ANTIGUA = 'sin emisiones recientes';
    public const ACCION_VERSION_ANTERIOR = 'versión anterior de la familia';
    public const ACCION_MARCADO_NO = 'descartado (marcado "NO" en el nombre)';
    public const ACCION_SIN_TABLA = 'sin características';
    public const ACCION_ERROR = 'no se pudo leer';

    /**
     * Lee la carpeta y arma el plan: una fila por archivo, con que se haria
     * con el. No escribe nada en la base.
     *
     * @return array<int, array{accion: string, archivo: string, titulo: ?string, modeloSugerido: ?string, garantia: ?string, caracteristicas: array<int, array{nombre: ?string, valor: string}>, codigoNet: ?string, fechaEmision: ?string, familia: string, archivosFamilia: int, elegido: ?string}>
     */
    public function analizar(string $carpeta, ?int $desdeAnio = null): array
    {
        if (!is_dir($carpeta)) {
            throw new RuntimeException("No existe la carpeta: $carpeta");
        }

        $extractor = new EspecificacionMetadataExtractor();
        $existentes = $this->familiasExistentes();
        $filas = [];

        foreach (glob(rtrim($carpeta, '/\\') . '/*.docx') ?: [] as $ruta) {
            $archivo = basename($ruta);
            $fila = [
                'accion' => self::ACCION_CREAR, 'archivo' => $archivo, 'titulo' => null, 'modeloSugerido' => null,
                'garantia' => null, 'caracteristicas' => [], 'codigoNet' => null, 'fechaEmision' => null,
                'familia' => '', 'archivosFamilia' => 0, 'elegido' => null,
            ];

            // Archivos temporales de Word ("~$archivo.docx").
            if (str_starts_with($archivo, '~$')) {
                continue;
            }

            if ($this->marcadoNo($archivo)) {
                $filas[] = ['accion' => self::ACCION_MARCADO_NO] + $fila;
                continue;
            }

            try {
                $datos = $extractor->extraerDesdeArchivo($ruta, 'docx');
            } catch (Throwable $e) {
                $filas[] = ['accion' => self::ACCION_ERROR] + $fila;
                continue;
            }

            $fila['titulo'] = $datos['titulo'];
            $fila['modeloSugerido'] = $this->limpiar($datos['modeloSugerido']);
            $fila['garantia'] = $this->limpiar($datos['garantia']);
            $fila['codigoNet'] = $datos['codigoNet'];
            $fila['fechaEmision'] = $datos['fechaEmision'];
            $fila['caracteristicas'] = array_map(fn (array $c) => [
                'nombre' => $this->limpiar($c['nombre']),
                'valor' => (string) $this->limpiar($c['valor']),
            ], $datos['caracteristicas']);

            if ($fila['caracteristicas'] === [] || empty($fila['titulo'])) {
                $filas[] = ['accion' => self::ACCION_SIN_TABLA] + $fila;
                continue;
            }

            $fila['familia'] = $this->clave($fila['titulo']) . '|' . $this->clave((string) $fila['modeloSugerido']);
            $filas[] = $fila;
        }

        $filas = $this->elegirPorFamilia($filas, $existentes);

        if ($desdeAnio !== null) {
            foreach ($filas as $i => $fila) {
                if ($fila['accion'] === self::ACCION_CREAR && 2000 + $this->orden($fila)[0] < $desdeAnio) {
                    $filas[$i]['accion'] = self::ACCION_ANTIGUA;
                }
            }
        }

        return $this->distinguirTitulos($filas);
    }

    /**
     * Si dos o mas fichas a crear tienen el mismo titulo, se les agrega el
     * modelo sugerido para poder distinguirlas en los selectores.
     */
    private function distinguirTitulos(array $filas): array
    {
        $porTitulo = [];
        foreach ($filas as $i => $fila) {
            if ($fila['accion'] === self::ACCION_CREAR) {
                $porTitulo[$this->clave((string) $fila['titulo'])][] = $i;
            }
        }

        foreach ($porTitulo as $indices) {
            if (count($indices) < 2) {
                continue;
            }
            foreach ($indices as $i) {
                if (!empty($filas[$i]['modeloSugerido'])) {
                    $filas[$i]['titulo'] = trim((string) $filas[$i]['titulo']) . ' — ' . $filas[$i]['modeloSugerido'];
                }
            }
        }

        return $filas;
    }

    /**
     * Crea en la libreria las fichas del plan marcadas "crear", cada una con
     * su v1, en UNA transaccion: o se cargan todas o ninguna.
     *
     * @return int cuantas fichas se crearon
     */
    public function importar(array $plan): int
    {
        $especificaciones = new EspecificacionService();
        $creadas = 0;
        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach ($plan as $fila) {
                if ($fila['accion'] !== self::ACCION_CREAR) {
                    continue;
                }

                $ficha = new Especificacion(['titulo' => mb_substr((string) $fila['titulo'], 0, 300), 'activo' => true]);
                $ficha->save(false);

                $resultado = $especificaciones->guardarVersion($ficha, [
                    'modeloSugerido' => $fila['modeloSugerido'] !== null ? mb_substr($fila['modeloSugerido'], 0, 200) : null,
                    'garantia' => $fila['garantia'] !== null ? mb_substr($fila['garantia'], 0, 500) : null,
                    'notaCambio' => mb_substr('Importada del banco de DTIC: ' . $fila['archivo'] . ($fila['codigoNet'] ? " ({$fila['codigoNet']})" : ''), 0, 300),
                ], $fila['caracteristicas']);

                if ($resultado['errores'] !== []) {
                    throw new RuntimeException("{$fila['archivo']}: " . implode(' ', $resultado['errores']));
                }

                $creadas++;
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return $creadas;
    }

    /**
     * El plan como CSV (";" y BOM UTF-8, para abrir en Excel) para revisarlo
     * antes de confirmar.
     */
    public function escribirCsv(array $plan, string $ruta): void
    {
        $salida = fopen($ruta, 'w');
        if ($salida === false) {
            throw new RuntimeException("No se pudo escribir el archivo: $ruta");
        }

        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['Acción', 'Archivo', 'Título', 'Modelo sugerido', 'Características', 'Garantía', 'NET', 'Fecha de emisión', 'Archivos de la familia', 'Se usa en su lugar'], ';');

        foreach ($plan as $fila) {
            fputcsv($salida, [
                $fila['accion'],
                $fila['archivo'],
                $fila['titulo'],
                $fila['modeloSugerido'],
                count($fila['caracteristicas']),
                $fila['garantia'] !== null ? 'sí' : 'por defecto',
                $fila['codigoNet'],
                $fila['fechaEmision'],
                $fila['archivosFamilia'] ?: '',
                $fila['elegido'],
            ], ';');
        }

        fclose($salida);
    }

    /**
     * Cuantas filas del plan hay por accion: [accion => cantidad].
     *
     * @return array<string, int>
     */
    public function resumen(array $plan): array
    {
        return array_count_values(array_column($plan, 'accion'));
    }

    /**
     * De cada familia se queda con la emision mas reciente; las demas se
     * marcan como version anterior, indicando cual se usa en su lugar.
     */
    private function elegirPorFamilia(array $filas, array $existentes): array
    {
        // Muchos formularios viejos no traen modelo sugerido y los nuevos si
        // ("Cable Cat 6" de 2023 sin modelo, el de 2026 con "Nexxt"). Uno sin
        // modelo se une a la familia de su titulo si esa familia tiene UN
        // solo modelo; si tiene varios ("Antena wifi" EAP225, U6 PRO...) no se
        // sabe a cual pertenece y queda aparte.
        $modelosPorTitulo = [];
        foreach ($filas as $fila) {
            if ($fila['accion'] === self::ACCION_CREAR && !str_ends_with($fila['familia'], '|')) {
                $modelosPorTitulo[strstr($fila['familia'], '|', true)][$fila['familia']] = true;
            }
        }
        foreach ($filas as $i => $fila) {
            if ($fila['accion'] === self::ACCION_CREAR && str_ends_with($fila['familia'], '|')) {
                $modelos = $modelosPorTitulo[rtrim($fila['familia'], '|')] ?? [];
                if (count($modelos) === 1) {
                    $filas[$i]['familia'] = array_key_first($modelos);
                }
            }
        }

        $porFamilia = [];
        foreach ($filas as $i => $fila) {
            if ($fila['accion'] === self::ACCION_CREAR) {
                $porFamilia[$fila['familia']][] = $i;
            }
        }

        foreach ($porFamilia as $familia => $indices) {
            usort($indices, fn (int $a, int $b) => $this->orden($filas[$b]) <=> $this->orden($filas[$a]));
            $elegido = $indices[0];

            foreach ($indices as $i) {
                $filas[$i]['archivosFamilia'] = count($indices);

                if ($i !== $elegido) {
                    $filas[$i]['accion'] = self::ACCION_VERSION_ANTERIOR;
                    $filas[$i]['elegido'] = $filas[$elegido]['archivo'];
                } elseif (isset($existentes[$familia])) {
                    $filas[$i]['accion'] = self::ACCION_YA_EXISTE;
                }
            }
        }

        // Primero lo que se crea, ordenado por titulo; despues el resto.
        usort($filas, static fn (array $a, array $b) =>
            [$a['accion'] !== self::ACCION_CREAR, $a['accion'], mb_strtolower((string) $a['titulo']), $a['archivo']]
            <=> [$b['accion'] !== self::ACCION_CREAR, $b['accion'], mb_strtolower((string) $b['titulo']), $b['archivo']]);

        return $filas;
    }

    /**
     * Que tan reciente es una emision: [año, numero NET, fecha]. El año sale
     * del NET ("/26") o, si no tiene, de la fecha de emision; el numero del
     * NET o, si no tiene, del comienzo del nombre del archivo ("880 Cable...").
     *
     * @return array{0: int, 1: int, 2: string}
     */
    private function orden(array $fila): array
    {
        $anio = 0;
        $numero = 0;

        if ($fila['codigoNet'] !== null && preg_match('/NET:(\d+)\/(\d+)/', $fila['codigoNet'], $m) === 1) {
            $numero = (int) $m[1];
            $anio = (int) $m[2];
        }
        if ($anio === 0 && $fila['fechaEmision'] !== null) {
            $anio = (int) substr($fila['fechaEmision'], 2, 2);
        }
        if ($numero === 0 && preg_match('/^(\d+)/', $fila['archivo'], $m) === 1) {
            $numero = (int) $m[1];
        }

        return [$anio, $numero, (string) $fila['fechaEmision']];
    }

    /**
     * Archivos que DTIC marco como no usados: "243 NO Camara BALA",
     * "470 NOOO UPS...", "496 NO ENVIADO...", "442 NOOOOO SOPORTE...",
     * "864 NO (CAMBIADO POR LSZH)...".
     */
    private function marcadoNo(string $archivo): bool
    {
        return preg_match('/^[\d_.A-Za-z]*\s+(\(\d+\)\s+)?NO+\b/u', $archivo) === 1;
    }

    /**
     * Familias que ya estan en la libreria (titulo + modelo sugerido de la
     * version actual), para no duplicarlas si el comando se corre otra vez.
     *
     * @return array<string, true>
     */
    private function familiasExistentes(): array
    {
        $familias = [];

        foreach (Especificacion::find()->with('versionActual')->all() as $ficha) {
            $titulo = (string) $ficha->titulo;
            $modelo = (string) $ficha->versionActual?->modeloSugerido;

            // Una ficha importada antes puede tener el modelo agregado al
            // titulo ("Antena wifi — EAP225", ver distinguirTitulos()).
            if ($modelo !== '' && str_ends_with($titulo, ' — ' . $modelo)) {
                $titulo = mb_substr($titulo, 0, mb_strlen($titulo) - mb_strlen(' — ' . $modelo));
            }

            $familias[$this->clave($titulo) . '|' . $this->clave($modelo)] = true;
        }

        return $familias;
    }

    /**
     * Clave para comparar textos: minusculas, sin acentos, sin signos y con
     * un solo espacio. "Cable de red Categoría 6," = "CABLE DE RED CATEGORIA 6".
     */
    private function clave(string $texto): string
    {
        $texto = mb_strtolower($texto);
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $texto));
    }

    /**
     * Limpia restos de formulas pegadas en Word ("$6.3 \text{ mm} \pm 0.3 \text{ mm}$"
     * -> "6.3 mm ± 0.3 mm") y espacios de mas.
     */
    private function limpiar(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }

        if (str_contains($texto, '\\')) {
            $texto = (string) preg_replace('/\\\\text\{([^}]*)\}/', '$1', $texto);
            $texto = strtr($texto, ['\\pm' => '±', '\\times' => '×', '\\geq' => '≥', '\\leq' => '≤', '\\approx' => '≈', '\\%' => '%']);
            $texto = (string) preg_replace('/\$([^$]*)\$/', '$1', $texto);
        }

        $texto = trim((string) preg_replace('/[ \t]+/', ' ', $texto));

        return $texto === '' ? null : $texto;
    }
}
