<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\ProyectoCondicionPrevia;
use backend\models\TipoCondicionPrevia;

/**
 * Capa de Servicio para TipoCondicionPrevia (tipos de condicion previa).
 *
 * Ademas del CRUD, arma el desplegable "Tipo" de las condiciones previas en
 * la pantalla de la lista.
 */
class TipoCondicionPreviaService
{
    /**
     * Tipos en el orden en que se cargaron, cada uno con en cuantos
     * proyectos se usa.
     *
     * @return array<int, array{tipo: TipoCondicionPrevia, proyectos: int}>
     */
    public function listarConConteo(): array
    {
        $proyectos = ProyectoCondicionPrevia::find()
            ->select(['total' => 'COUNT(DISTINCT idProyecto)', 'idTipo'])
            ->groupBy('idTipo')
            ->indexBy('idTipo')
            ->column();

        $filas = [];

        foreach (TipoCondicionPrevia::find()->orderBy(['id' => SORT_ASC])->all() as $tipo) {
            $filas[] = ['tipo' => $tipo, 'proyectos' => (int) ($proyectos[$tipo->id] ?? 0)];
        }

        return $filas;
    }

    /**
     * Tipos activos como [id => nombre], mas los que el proyecto ya tiene
     * aunque se hayan desactivado (si no, el desplegable los perderia al
     * editar).
     *
     * @param int[] $conservar
     * @return array<int, string>
     */
    public function opciones(array $conservar = []): array
    {
        $query = TipoCondicionPrevia::find()->andWhere(['activo' => true]);

        if ($conservar !== []) {
            $query->orWhere(['id' => $conservar]);
        }

        return $query->select(['nombre', 'id'])->orderBy(['id' => SORT_ASC])->indexBy('id')->column();
    }

    public function obtener(int $id): ?TipoCondicionPrevia
    {
        return TipoCondicionPrevia::findOne($id);
    }

    public function crear(TipoCondicionPrevia $tipo, array $datosFormulario): bool
    {
        return $tipo->load($datosFormulario) && $tipo->save();
    }

    public function actualizar(TipoCondicionPrevia $tipo, array $datosFormulario): bool
    {
        return $tipo->load($datosFormulario) && $tipo->save();
    }

    /**
     * Devuelve null si se elimino, o el motivo por el que no se puede.
     *
     * REGLA DE NEGOCIO: un tipo en uso no se elimina, se desactiva. La FK de
     * ProyectoCondicionPrevia ya lo impediria; se revisa antes para dar un
     * mensaje claro.
     */
    public function eliminar(TipoCondicionPrevia $tipo): ?string
    {
        if (ProyectoCondicionPrevia::find()->andWhere(['idTipo' => $tipo->id])->exists()) {
            return 'No se puede eliminar el tipo "' . $tipo->nombre . '" porque lo usan proyectos. Desactívelo con "Editar".';
        }

        $tipo->delete();

        return null;
    }
}
