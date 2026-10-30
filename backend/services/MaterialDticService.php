<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Proyecto;
use backend\models\ProyectoMaterialDtic;
use backend\models\SubcatalogoItem;

/**
 * Capa de Servicio de "Otros materiales y equipamiento que proveerá la
 * D.T.I.C." del proyecto (revision del 2026-10-29, paso 8; antes en el
 * presupuesto, ElaborarPresupuestoService::guardarComplementos()). Se
 * escriben debajo de la lista y se guardan con ella
 * (ListaItemsService::guardar()), mientras la lista esta en edicion.
 */
class MaterialDticService
{
    /**
     * Arma los materiales con lo que llego del formulario
     * (materiales[n][grupo|idSubcatalogo|descripcion|cantidad|unidadMedida]).
     * REGLAS: una fila en blanco (o solo con el subtitulo) se ignora; la que
     * tiene algo necesita descripcion y cantidad mayor que cero. Si elige un
     * item del subcatalogo y deja en blanco el nombre o la unidad, se toman
     * del item; su subtitulo es SIEMPRE el nombre de la categoria del item
     * (2026-10-30; se copia al guardar). Escrita a mano sin subtitulo va a
     * "Otros". Los mensajes se agregan a $errores.
     *
     * @param mixed $filas
     * @param string[] $errores (por referencia)
     * @return ProyectoMaterialDtic[] sin guardar, en el orden del formulario
     */
    public function armar(Proyecto $proyecto, $filas, array &$errores): array
    {
        $materiales = [];
        $numero = 0;

        foreach ((array) $filas as $datos) {
            if (!is_array($datos)) {
                continue;
            }

            $datos = array_map(
                static fn ($valor) => is_scalar($valor) ? trim((string) $valor) : '',
                array_intersect_key($datos, array_flip(['grupo', 'idSubcatalogo', 'descripcion', 'cantidad', 'unidadMedida']))
            );

            if (implode('', array_diff_key($datos, ['grupo' => true])) === '') {
                continue;
            }

            $numero++;
            $material = new ProyectoMaterialDtic();
            $material->setAttributes($datos);

            if (ctype_digit((string) $material->idSubcatalogo)) {
                $item = SubcatalogoItem::findOne((int) $material->idSubcatalogo);

                if ($item !== null) {
                    $material->descripcion = $material->descripcion ?: $item->descripcion;
                    $material->unidadMedida = $material->unidadMedida ?: $item->abreviaturaUnidad();
                    $material->grupo = $item->categoria->nombre ?? $material->grupo;
                }
            } else {
                $material->idSubcatalogo = null;
            }

            $material->grupo = $material->grupo ?: ProyectoMaterialDtic::GRUPO_OTROS;

            if (!$material->validate()) {
                $errores[] = "Otros materiales de la DTIC, fila $numero: " . implode(' ', $material->getErrorSummary(false));
                continue;
            }

            // El filtro 'trim' de validate() deja '' donde habia NULL.
            if ($material->unidadMedida === '') {
                $material->unidadMedida = null;
            }

            $materiales[] = $material;
        }

        return $materiales;
    }

    /**
     * Reemplaza los materiales del proyecto por estos. Se llama dentro de la
     * transaccion de quien guarda.
     *
     * @param ProyectoMaterialDtic[] $materiales
     */
    public function reemplazar(Proyecto $proyecto, array $materiales): void
    {
        ProyectoMaterialDtic::deleteAll(['idProyecto' => $proyecto->id]);

        foreach ($materiales as $orden => $material) {
            $material->idProyecto = $proyecto->id;
            $material->orden = $orden;
            $material->save(false);
        }

        unset($proyecto->materialesDtic);
    }
}
