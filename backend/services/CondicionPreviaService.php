<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Proyecto;
use backend\models\ProyectoCondicionPrevia;

/**
 * Capa de Servicio de las condiciones y requerimientos previos del proyecto
 * (revision del 2026-10-29, paso 8): lo que deben hacer otras unidades, sin
 * precio. Se escriben en la pantalla de la lista y se guardan con ella
 * (ListaItemsService::guardar()), mientras la lista esta en edicion.
 */
class CondicionPreviaService
{
    /**
     * Arma las condiciones con lo que llego del formulario
     * (condiciones[n][idTipo|descripcion|unidadEncargada]).
     * REGLAS: una fila en blanco se ignora; la que tiene algo escrito
     * necesita tipo y descripcion; el tipo debe estar activo, salvo los que
     * el proyecto ya tenia. Los mensajes se agregan a $errores.
     *
     * @param mixed $filas
     * @param string[] $errores (por referencia)
     * @return ProyectoCondicionPrevia[] sin guardar, en el orden del formulario
     */
    public function armar(Proyecto $proyecto, $filas, array &$errores): array
    {
        $yaTenia = array_map(static fn (ProyectoCondicionPrevia $c) => (int) $c->idTipo, $proyecto->condicionesPrevias);
        $tipos = array_map('intval', array_keys((new TipoCondicionPreviaService())->opciones($yaTenia)));
        $condiciones = [];
        $numero = 0;

        foreach ((array) $filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }

            $valores = array_map(
                static fn ($valor) => is_scalar($valor) ? trim((string) $valor) : '',
                array_intersect_key($fila, array_flip(['idTipo', 'descripcion', 'unidadEncargada']))
            );

            // El tipo solo no cuenta: la fila nueva ya viene con uno elegido.
            if (trim(($valores['descripcion'] ?? '') . ($valores['unidadEncargada'] ?? '')) === '') {
                continue;
            }

            $numero++;
            $idTipo = $valores['idTipo'] ?? '';

            if (!ctype_digit($idTipo) || !in_array((int) $idTipo, $tipos, true)) {
                $errores[] = "Trabajos complementarios, fila $numero: elija un tipo de la lista.";
                continue;
            }

            $condicion = new ProyectoCondicionPrevia([
                'idTipo' => (int) $idTipo,
                'descripcion' => $valores['descripcion'] ?? '',
                'unidadEncargada' => ($valores['unidadEncargada'] ?? '') === '' ? null : $valores['unidadEncargada'],
            ]);

            if (!$condicion->validate(['descripcion', 'unidadEncargada'])) {
                $errores[] = "Trabajos complementarios, fila $numero: " . implode(' ', $condicion->getFirstErrors());
                continue;
            }

            // El filtro 'trim' de validate() deja '' donde habia NULL.
            if ($condicion->unidadEncargada === '') {
                $condicion->unidadEncargada = null;
            }

            $condiciones[] = $condicion;
        }

        return $condiciones;
    }

    /**
     * Reemplaza las condiciones del proyecto por estas. Se llama dentro de la
     * transaccion de quien guarda.
     *
     * @param ProyectoCondicionPrevia[] $condiciones
     */
    public function reemplazar(Proyecto $proyecto, array $condiciones): void
    {
        ProyectoCondicionPrevia::deleteAll(['idProyecto' => $proyecto->id]);

        foreach ($condiciones as $orden => $condicion) {
            $condicion->idProyecto = $proyecto->id;
            $condicion->orden = $orden;
            $condicion->save(false);
        }

        unset($proyecto->condicionesPrevias);
    }

    /**
     * Unidades encargadas para el <datalist>: las sugeridas y las ya
     * escritas en cualquier proyecto, sin repetir (sin importar mayusculas).
     *
     * @return string[]
     */
    public function sugerenciasUnidad(): array
    {
        $escritas = ProyectoCondicionPrevia::find()
            ->select('unidadEncargada')
            ->distinct()
            ->andWhere(['not', ['unidadEncargada' => null]])
            ->column();

        $sugerencias = [];
        foreach (array_merge(ProyectoCondicionPrevia::UNIDADES_ENCARGADAS_SUGERIDAS, $escritas) as $unidad) {
            $sugerencias[mb_strtolower(trim((string) $unidad), 'UTF-8')] ??= trim((string) $unidad);
        }

        return array_values(array_filter($sugerencias, static fn (string $u) => $u !== ''));
    }
}
