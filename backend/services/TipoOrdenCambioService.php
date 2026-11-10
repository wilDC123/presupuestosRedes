<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Proyecto;
use backend\models\TipoOrdenCambio;

/**
 * Capa de Servicio para TipoOrdenCambio (tipos de orden de cambio, 2026-11-09).
 *
 * Ademas del CRUD, arma el desplegable "Tipo de cambio" del formulario de la
 * orden de cambio.
 */
class TipoOrdenCambioService
{
    /**
     * Tipos en el orden en que se cargaron, cada uno con en cuantas ordenes
     * de cambio se usa.
     *
     * @return array<int, array{tipo: TipoOrdenCambio, ordenes: int}>
     */
    public function listarConConteo(): array
    {
        $ordenes = Proyecto::find()
            ->select(['total' => 'COUNT(*)', 'idTipoOrdenCambio'])
            ->andWhere(['not', ['idTipoOrdenCambio' => null]])
            ->groupBy('idTipoOrdenCambio')
            ->indexBy('idTipoOrdenCambio')
            ->column();

        $filas = [];

        foreach (TipoOrdenCambio::find()->orderBy(['id' => SORT_ASC])->all() as $tipo) {
            $filas[] = ['tipo' => $tipo, 'ordenes' => (int) ($ordenes[$tipo->id] ?? 0)];
        }

        return $filas;
    }

    /**
     * Tipos activos como [id => nombre], mas el que la orden ya tiene aunque
     * se haya desactivado (si no, el desplegable lo perderia al editar).
     *
     * @return array<int, string>
     */
    public function opciones(?int $conservar = null): array
    {
        $query = TipoOrdenCambio::find()->andWhere(['activo' => true]);

        if ($conservar !== null) {
            $query->orWhere(['id' => $conservar]);
        }

        return $query->select(['nombre', 'id'])->orderBy(['id' => SORT_ASC])->indexBy('id')->column();
    }

    public function obtener(int $id): ?TipoOrdenCambio
    {
        return TipoOrdenCambio::findOne($id);
    }

    public function crear(TipoOrdenCambio $tipo, array $datosFormulario): bool
    {
        return $tipo->load($datosFormulario) && $tipo->save();
    }

    public function actualizar(TipoOrdenCambio $tipo, array $datosFormulario): bool
    {
        return $tipo->load($datosFormulario) && $tipo->save();
    }

    /**
     * Devuelve null si se elimino, o el motivo por el que no se puede.
     *
     * REGLA DE NEGOCIO: un tipo en uso no se elimina, se desactiva. La FK de
     * Proyecto ya lo impediria; se revisa antes para dar un mensaje claro.
     */
    public function eliminar(TipoOrdenCambio $tipo): ?string
    {
        if (Proyecto::find()->andWhere(['idTipoOrdenCambio' => $tipo->id])->exists()) {
            return 'No se puede eliminar el tipo "' . $tipo->nombre . '" porque lo usan órdenes de cambio. Desactívelo con "Editar".';
        }

        $tipo->delete();

        return null;
    }
}
