<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Proyectista;
use backend\models\ProyectoProyectista;

/**
 * Capa de Servicio para Proyectista.
 *
 * Ademas del CRUD, arma la lista de nombres del formulario de proyecto. Como
 * el proyecto guarda una copia del nombre (no el id), "en uso" se cuenta por
 * nombre.
 */
class ProyectistaService
{
    /**
     * Proyectistas por nombre, cada uno con cuantos proyectos lo tienen
     * asignado (una sola consulta agrupada).
     *
     * @return array<int, array{proyectista: Proyectista, proyectos: int}>
     */
    public function listarConConteo(): array
    {
        $proyectos = $this->conteoPorNombre();
        $filas = [];

        foreach (Proyectista::find()->orderBy(['nombre' => SORT_ASC])->all() as $proyectista) {
            $filas[] = [
                'proyectista' => $proyectista,
                'proyectos' => (int) ($proyectos[mb_strtolower($proyectista->nombre, 'UTF-8')] ?? 0),
            ];
        }

        return $filas;
    }

    /**
     * Nombres de los proyectistas activos, para el desplegable del proyecto.
     *
     * @return string[]
     */
    public function nombresActivos(): array
    {
        return Proyectista::find()->select('nombre')->andWhere(['activo' => true])->orderBy(['nombre' => SORT_ASC])->column();
    }

    public function obtener(int $id): ?Proyectista
    {
        return Proyectista::findOne($id);
    }

    public function crear(Proyectista $proyectista, array $datosFormulario): bool
    {
        return $proyectista->load($datosFormulario) && $proyectista->save();
    }

    public function actualizar(Proyectista $proyectista, array $datosFormulario): bool
    {
        return $proyectista->load($datosFormulario) && $proyectista->save();
    }

    /**
     * Devuelve null si se elimino, o el motivo por el que no se puede.
     *
     * REGLA DE NEGOCIO: un proyectista asignado a algun proyecto no se
     * elimina, se desactiva (asi la lista conserva a quien ya trabajo).
     */
    public function eliminar(Proyectista $proyectista): ?string
    {
        if (ProyectoProyectista::find()->andWhere(['nombre' => $proyectista->nombre])->exists()) {
            return 'No se puede eliminar a "' . $proyectista->nombre . '" porque está asignado a proyectos. Desactívelo con "Editar".';
        }

        $proyectista->delete();

        return null;
    }

    /**
     * @return array<string, int> [nombre en minusculas => total de proyectos]
     */
    private function conteoPorNombre(): array
    {
        $filas = ProyectoProyectista::find()
            ->select(['nombre', 'total' => 'COUNT(DISTINCT idProyecto)'])
            ->groupBy('nombre')
            ->asArray()
            ->all();

        $conteo = [];

        foreach ($filas as $fila) {
            $clave = mb_strtolower(trim((string) $fila['nombre']), 'UTF-8');
            $conteo[$clave] = ($conteo[$clave] ?? 0) + (int) $fila['total'];
        }

        return $conteo;
    }
}
