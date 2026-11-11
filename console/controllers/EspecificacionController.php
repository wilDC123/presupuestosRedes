<?php

declare(strict_types=1);

namespace console\controllers;

use backend\services\EspecificacionImportacionService;
use backend\services\EspecificacionOrdenService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Comandos de consola de la libreria de especificaciones.
 *
 * Uso:
 *   php yii especificacion/importar "C:\ruta\Banco de especificaciones"
 *       Solo ANALIZA: escribe un CSV con lo que haria y no toca la base.
 *   php yii especificacion/importar "C:\ruta\Banco de especificaciones" --confirmar
 *       Crea las fichas en la libreria.
 *   --desde=2025  no crea las familias que no se emitieron desde ese año.
 *   --csv=ruta    donde escribir el CSV del plan.
 *
 *   php yii especificacion/ordenar console/data/orden-especificaciones-2026-11-03.json
 *       Solo MUESTRA que fichas se ordenarian (niveles, subtitulos, forma).
 *   php yii especificacion/ordenar console/data/orden-especificaciones-2026-11-03.json --confirmar
 *       Las ordena (EspecificacionOrdenService). Se puede volver a correr: las
 *       ya ordenadas se saltan.
 *
 * Las reglas estan en EspecificacionImportacionService (importar) y
 * EspecificacionOrdenService (ordenar).
 */
class EspecificacionController extends Controller
{
    /**
     * @var bool escribir en la base (sin esta opcion solo se analiza)
     */
    public bool $confirmar = false;

    /**
     * @var string|null donde escribir el CSV del plan (por defecto, en console/runtime)
     */
    public ?string $csv = null;

    /**
     * @var int|null no crear las familias cuya ultima emision es anterior a este año (ej. 2025)
     */
    public ?int $desde = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['confirmar', 'csv', 'desde']);
    }

    /**
     * Carga la libreria desde la carpeta de .docx del banco de especificaciones.
     *
     * @param string $carpeta carpeta con los .docx
     */
    public function actionImportar(string $carpeta): int
    {
        $servicio = new EspecificacionImportacionService();

        $this->stdout("Leyendo $carpeta ...\n");
        $plan = $servicio->analizar($carpeta, $this->desde);

        $rutaCsv = $this->csv ?? Yii::getAlias('@console/runtime/importacion-especificaciones-' . date('Ymd-His') . '.csv');
        $servicio->escribirCsv($plan, $rutaCsv);

        $this->stdout("\nResumen (" . count($plan) . " archivos):\n");
        foreach ($servicio->resumen($plan) as $accion => $cantidad) {
            $this->stdout(sprintf("  %5d  %s\n", $cantidad, $accion));
        }
        $this->stdout("\nDetalle archivo por archivo: $rutaCsv\n");

        if (!$this->confirmar) {
            $this->stdout("\nNo se creó nada. Revise el CSV y vuelva a correr el comando con --confirmar.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        $creadas = $servicio->importar($plan);
        $this->stdout("\nSe crearon $creadas fichas en la librería (sin categoría: asígnela desde la pantalla).\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Ordena la libreria segun un plan revisado (niveles, subtitulos y arreglos
     * de forma de cada ficha). Cada ficha se reconoce por titulo y contenido:
     * vale para cualquier base aunque los id sean otros.
     *
     * @param string $archivo JSON del plan (ej. console/data/orden-especificaciones-2026-11-03.json)
     */
    public function actionOrdenar(string $archivo): int
    {
        $servicio = new EspecificacionOrdenService();
        $analisis = $servicio->analizar($servicio->leerPlan($archivo));

        $this->stdout("\nResumen (" . count($analisis) . " fichas del plan):\n");
        foreach ($servicio->resumen($analisis) as $estado => $cantidad) {
            $this->stdout(sprintf("  %5d  %s\n", $cantidad, $estado));
        }
        foreach ($analisis as $fila) {
            if (in_array($fila['estado'], [EspecificacionOrdenService::ESTADO_NO_ENCONTRADA, EspecificacionOrdenService::ESTADO_EMITIDA], true)) {
                $this->stdout("  - {$fila['titulo']}: {$fila['estado']}\n", Console::FG_YELLOW);
            }
        }

        if (!$this->confirmar) {
            $this->stdout("\nNo se cambió nada. Vuelva a correr el comando con --confirmar para ordenar.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        $ordenadas = $servicio->aplicar($analisis);
        $this->stdout("\nSe ordenaron $ordenadas fichas.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
