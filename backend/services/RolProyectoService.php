<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\ProyectoParticipante;
use backend\models\RolProyecto;

/**
 * Capa de Servicio para RolProyecto (roles del equipo del proyecto).
 *
 * Ademas del CRUD, arma el desplegable "Rol" del equipo en el formulario de
 * proyecto.
 */
class RolProyectoService
{
    /**
     * Roles en el orden en que se cargaron (Proyectista primero), cada uno
     * con en cuantos proyectos se usa.
     *
     * @return array<int, array{rol: RolProyecto, proyectos: int}>
     */
    public function listarConConteo(): array
    {
        $proyectos = ProyectoParticipante::find()
            ->select(['total' => 'COUNT(DISTINCT idProyecto)', 'idRol'])
            ->groupBy('idRol')
            ->indexBy('idRol')
            ->column();

        $filas = [];

        foreach (RolProyecto::find()->orderBy(['id' => SORT_ASC])->all() as $rol) {
            $filas[] = ['rol' => $rol, 'proyectos' => (int) ($proyectos[$rol->id] ?? 0)];
        }

        return $filas;
    }

    /**
     * Roles activos como [id => nombre], mas los que el proyecto ya tiene
     * aunque se hayan desactivado (si no, el desplegable los perderia al
     * editar).
     *
     * @param int[] $conservar
     * @return array<int, string>
     */
    public function opciones(array $conservar = []): array
    {
        $query = RolProyecto::find()->andWhere(['activo' => true]);

        if ($conservar !== []) {
            $query->orWhere(['id' => $conservar]);
        }

        return $query->select(['nombre', 'id'])->orderBy(['id' => SORT_ASC])->indexBy('id')->column();
    }

    /**
     * Id del rol Proyectista, el que se propone en una fila nueva del equipo
     * (null si ya no existe o esta inactivo).
     */
    public function idProyectista(): ?int
    {
        $id = RolProyecto::find()->select('id')->andWhere(['nombre' => 'Proyectista', 'activo' => true])->scalar();

        return $id === false || $id === null ? null : (int) $id;
    }

    public function obtener(int $id): ?RolProyecto
    {
        return RolProyecto::findOne($id);
    }

    public function crear(RolProyecto $rol, array $datosFormulario): bool
    {
        return $rol->load($datosFormulario) && $rol->save();
    }

    public function actualizar(RolProyecto $rol, array $datosFormulario): bool
    {
        return $rol->load($datosFormulario) && $rol->save();
    }

    /**
     * Devuelve null si se elimino, o el motivo por el que no se puede.
     *
     * REGLA DE NEGOCIO: un rol en uso no se elimina, se desactiva. La FK de
     * ProyectoParticipante ya lo impediria; se revisa antes para dar un
     * mensaje claro.
     */
    public function eliminar(RolProyecto $rol): ?string
    {
        if (ProyectoParticipante::find()->andWhere(['idRol' => $rol->id])->exists()) {
            return 'No se puede eliminar el rol "' . $rol->nombre . '" porque lo usan proyectos. Desactívelo con "Editar".';
        }

        $rol->delete();

        return null;
    }
}
