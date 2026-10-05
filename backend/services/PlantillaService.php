<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Categoria;
use backend\models\Plantilla;
use backend\models\PlantillaCategoria;
use backend\models\PlantillaItem;
use backend\models\SubcatalogoItem;
use Throwable;
use Yii;
use yii\db\IntegrityException;

/**
 * Capa de Servicio para Plantilla: administrar las plantillas de items
 * (crear, editar, desactivar) con su clasificacion configurable.
 *
 * Reglas de negocio que viven aqui:
 *  - cada plantilla elige que clasificaciones (categorias) incluye,
 *  - cada item va en UNA de las clasificaciones incluidas en su plantilla,
 *  - plantilla, clasificaciones e items se guardan juntos (una transaccion),
 *  - una plantilla ya usada por algun proyecto no se elimina: se desactiva.
 *
 * Copiar una plantilla a la lista de un proyecto es tarea de ListaItemsService.
 */
class PlantillaService
{
    /**
     * Plantillas para el listado de administracion (todas) o para "Cargar
     * plantilla" (solo activas), cada una con sus items ya agrupados.
     *
     * @return array<int, array{plantilla: Plantilla, totalItems: int, grupos: array<string, PlantillaItem[]>}>
     */
    public function listar(bool $soloActivas = false): array
    {
        $plantillas = Plantilla::find()
            ->andFilterWhere(['activo' => $soloActivas ? true : null])
            ->with(['items.subcatalogoItem', 'items.categoria', 'categorias'])
            ->orderBy(['nombre' => SORT_ASC])
            ->all();

        return array_map(fn (Plantilla $plantilla) => [
            'plantilla' => $plantilla,
            'totalItems' => count($plantilla->items),
            'grupos' => $this->agruparPorClasificacion($plantilla),
        ], $plantillas);
    }

    public function obtener(int $id): ?Plantilla
    {
        return Plantilla::findOne($id);
    }

    /**
     * Items de la plantilla agrupados por el nombre de su clasificacion, en
     * el orden de las categorias.
     *
     * @return array<string, PlantillaItem[]>
     */
    public function agruparPorClasificacion(Plantilla $plantilla): array
    {
        $grupos = [];
        $orden = [];

        foreach ($plantilla->items as $item) {
            $nombre = $item->categoria->nombre ?? 'Sin clasificación';
            $grupos[$nombre][] = $item;
            $orden[$nombre] = $item->categoria->orden ?? 999;
        }

        uksort($grupos, static fn ($a, $b) => $orden[$a] <=> $orden[$b]);

        return $grupos;
    }

    /**
     * Ids de las clasificaciones incluidas en la plantilla.
     *
     * @return int[]
     */
    public function idsCategoria(Plantilla $plantilla): array
    {
        return array_map(static fn (PlantillaCategoria $fila) => (int) $fila->idCategoria, $plantilla->plantillaCategorias);
    }

    /**
     * Crea o actualiza la plantilla con sus clasificaciones e items.
     *
     * El formulario envia tres bloques:
     *   Plantilla[...]            datos de la plantilla
     *   categorias[]              ids de las clasificaciones marcadas
     *   items[n][idSubcatalogo|idCategoria|cantidadSugerida|observacion]
     *
     * Devuelve false si algo no es valido. Los errores quedan en
     * $plantilla->errors (los de clasificaciones e items, bajo las claves
     * 'categorias' e 'items'), y $items trae los items tal como llegaron,
     * para volver a mostrarlos en el formulario.
     *
     * @param PlantillaItem[] $items se llena con los items enviados (por referencia)
     */
    public function guardar(Plantilla $plantilla, array $datosFormulario, array &$items = []): bool
    {
        if (!$plantilla->load($datosFormulario)) {
            return false;
        }

        $idsCategoria = $this->idsCategoriaValidos($datosFormulario['categorias'] ?? []);
        $items = $this->itemsDesdeFormulario($datosFormulario['items'] ?? []);

        // validate() borra los errores previos: va antes de los del servicio.
        $plantilla->validate();

        if ($idsCategoria === []) {
            $plantilla->addError('categorias', 'Marque al menos una clasificación.');
        }

        foreach ($items as $numero => $item) {
            if (!$item->validate(['idSubcatalogo', 'idCategoria', 'cantidadSugerida', 'observacion'])) {
                $plantilla->addError('items', 'Ítem ' . ($numero + 1) . ': ' . implode(' ', $item->getFirstErrors()));
            } elseif ($idsCategoria !== [] && !in_array((int) $item->idCategoria, $idsCategoria, true)) {
                // Sin ninguna clasificacion marcada basta el aviso de arriba.
                $plantilla->addError('items', 'Ítem ' . ($numero + 1) . ': su clasificación no está entre las marcadas para esta plantilla.');
            }
        }

        if ($plantilla->hasErrors()) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $plantilla->save(false);

            // Se reemplaza todo: mas simple y seguro que comparar fila por fila.
            PlantillaCategoria::deleteAll(['idPlantilla' => $plantilla->id]);
            PlantillaItem::deleteAll(['idPlantilla' => $plantilla->id]);

            foreach ($idsCategoria as $orden => $idCategoria) {
                $fila = new PlantillaCategoria();
                $fila->idPlantilla = $plantilla->id;
                $fila->idCategoria = $idCategoria;
                $fila->orden = $orden + 1;
                $fila->save(false);
            }

            foreach ($items as $orden => $item) {
                $item->idPlantilla = $plantilla->id;
                $item->orden = $orden + 1;
                $item->save(false);
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Devuelve false si la plantilla no se puede borrar porque algun
     * proyecto la tiene como plantilla de origen. En ese caso se desactiva.
     */
    public function eliminar(Plantilla $plantilla): bool
    {
        try {
            return $plantilla->delete() !== false;
        } catch (IntegrityException $e) {
            return false;
        }
    }

    public function cambiarActivo(Plantilla $plantilla, bool $activo): void
    {
        $plantilla->updateAttributes(['activo' => $activo]);
    }

    /**
     * De los ids enviados, solo los que existen como categoria, sin
     * repetidos y en el orden de las categorias.
     *
     * @param mixed $enviados
     * @return int[]
     */
    private function idsCategoriaValidos($enviados): array
    {
        $ids = array_filter((array) $enviados, static fn ($id) => is_string($id) && ctype_digit($id));

        if ($ids === []) {
            return [];
        }

        return array_map('intval', Categoria::find()
            ->select('id')
            ->andWhere(['id' => array_values($ids)])
            ->orderBy(['orden' => SORT_ASC])
            ->column());
    }

    /**
     * Convierte las filas items[n][...] del formulario en modelos
     * PlantillaItem sin guardar. Se asigna campo por campo (no con load())
     * porque las filas no vienen con el nombre del formulario del modelo.
     *
     * @param mixed $filas
     * @return PlantillaItem[]
     */
    private function itemsDesdeFormulario($filas): array
    {
        $items = [];

        foreach ((array) $filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }

            $item = new PlantillaItem();
            $item->idSubcatalogo = $this->texto($fila['idSubcatalogo'] ?? '');
            $item->idCategoria = $this->texto($fila['idCategoria'] ?? '');
            $item->cantidadSugerida = $this->texto($fila['cantidadSugerida'] ?? '');
            $item->observacion = $this->texto($fila['observacion'] ?? '');
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Garantiza un texto aunque en el formulario llegue un arreglo.
     *
     * @param mixed $valor
     */
    private function texto($valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }
}
