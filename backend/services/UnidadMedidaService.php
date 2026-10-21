<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\PresupuestoDetalle;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use backend\models\UnidadMedida;

/**
 * Capa de Servicio para UnidadMedida.
 *
 * Ademas del CRUD, arma los desplegables de unidad para los formularios: el
 * subcatalogo guarda el id de la unidad, y las listas y las lineas del
 * presupuesto guardan su abreviatura como texto (copiada, ver la migracion
 * m261015_233726_create_unidad_medida).
 */
class UnidadMedidaService
{
    /**
     * Unidades por nombre, cada una con la cantidad de items del subcatalogo
     * que la usan (en UNA consulta agrupada, no una por unidad).
     *
     * @return array<int, array{unidad: UnidadMedida, items: int}>
     */
    public function listarConConteo(): array
    {
        $conteos = SubcatalogoItem::find()
            ->select(['total' => 'COUNT(*)', 'idUnidadMedida'])
            ->andWhere(['not', ['idUnidadMedida' => null]])
            ->groupBy('idUnidadMedida')
            ->indexBy('idUnidadMedida')
            ->column();

        $filas = [];

        foreach (UnidadMedida::find()->orderBy(['nombre' => SORT_ASC])->all() as $unidad) {
            $filas[] = [
                'unidad' => $unidad,
                'items' => (int) ($conteos[$unidad->id] ?? 0),
            ];
        }

        return $filas;
    }

    /**
     * Unidades activas como [id => "Metro (m)"], para el formulario del
     * subcatalogo. Si el item ya tenia una unidad que despues se desactivo,
     * se agrega igual: si no, el desplegable la perderia al editar.
     *
     * @return array<int, string>
     */
    public function opcionesPorId(?int $actual = null): array
    {
        $opciones = [];

        foreach ($this->activasOConId($actual) as $unidad) {
            $opciones[$unidad->id] = $unidad->etiqueta();
        }

        return $opciones;
    }

    public function obtener(int $id): ?UnidadMedida
    {
        return UnidadMedida::findOne($id);
    }

    public function crear(UnidadMedida $unidad, array $datosFormulario): bool
    {
        return $unidad->load($datosFormulario) && $unidad->save();
    }

    public function actualizar(UnidadMedida $unidad, array $datosFormulario): bool
    {
        return $unidad->load($datosFormulario) && $unidad->save();
    }

    /**
     * Devuelve null si se elimino, o el motivo por el que no se puede.
     *
     * REGLA DE NEGOCIO: una unidad en uso no se elimina, se desactiva. La FK
     * del subcatalogo ya lo impediria, pero las listas y los presupuestos
     * guardan la abreviatura como texto (sin FK), asi que se revisan aqui
     * tambien.
     */
    public function eliminar(UnidadMedida $unidad): ?string
    {
        $enUso = SubcatalogoItem::find()->andWhere(['idUnidadMedida' => $unidad->id])->exists()
            || ProyectoItem::find()->andWhere(['unidadMedida' => $unidad->abreviatura])->exists()
            || PresupuestoDetalle::find()->andWhere(['unidadMedida' => $unidad->abreviatura])->exists();

        if ($enUso) {
            return 'No se puede eliminar "' . $unidad->nombre . '" porque está en uso (subcatálogo, listas o presupuestos). Desactívela con "Editar".';
        }

        $unidad->delete();

        return null;
    }

    /**
     * @return UnidadMedida[]
     */
    private function activasOConId(?int $id): array
    {
        $query = UnidadMedida::find()->andWhere(['activo' => true]);

        if ($id !== null) {
            $query->orWhere(['id' => $id]);
        }

        return $query->orderBy(['nombre' => SORT_ASC])->all();
    }
}
