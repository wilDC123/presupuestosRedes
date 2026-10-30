<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Personal;
use backend\models\ProyectoParticipante;

/**
 * Capa de Servicio para Personal (las personas que se ponen en el equipo de
 * un proyecto).
 *
 * Ademas del CRUD, arma la lista de nombres del formulario de proyecto. Como
 * el proyecto guarda una copia del nombre (no el id), "en uso" se cuenta por
 * nombre.
 */
class PersonalService
{
    /**
     * Personas por nombre, cada una con en cuantos proyectos participa (una
     * sola consulta agrupada).
     *
     * @return array<int, array{persona: Personal, proyectos: int}>
     */
    public function listarConConteo(): array
    {
        $proyectos = $this->conteoPorNombre();
        $filas = [];

        foreach (Personal::find()->orderBy(['nombre' => SORT_ASC])->all() as $persona) {
            $filas[] = [
                'persona' => $persona,
                'proyectos' => (int) ($proyectos[mb_strtolower($persona->nombre, 'UTF-8')] ?? 0),
            ];
        }

        return $filas;
    }

    /**
     * Nombres de las personas activas.
     *
     * @return string[]
     */
    public function nombresActivos(): array
    {
        return Personal::find()->select('nombre')->andWhere(['activo' => true])->orderBy(['nombre' => SORT_ASC])->column();
    }

    /**
     * Nombres para el desplegable "Persona" del equipo: las activas y las que
     * el proyecto ya tiene (aunque se hayan desactivado), por orden alfabetico.
     *
     * @param string[] $conservar
     * @return string[]
     */
    public function opciones(array $conservar = []): array
    {
        $nombres = array_values(array_unique(array_merge($this->nombresActivos(), array_filter($conservar, static fn ($n) => $n !== ''))));
        sort($nombres, SORT_STRING | SORT_FLAG_CASE);

        return $nombres;
    }

    public function obtener(int $id): ?Personal
    {
        return Personal::findOne($id);
    }

    public function crear(Personal $persona, array $datosFormulario): bool
    {
        return $persona->load($datosFormulario) && $persona->save();
    }

    public function actualizar(Personal $persona, array $datosFormulario): bool
    {
        return $persona->load($datosFormulario) && $persona->save();
    }

    /**
     * Devuelve null si se elimino, o el motivo por el que no se puede.
     *
     * REGLA DE NEGOCIO: una persona que esta en el equipo de algun proyecto
     * no se elimina, se desactiva (asi la lista conserva a quien ya trabajo).
     */
    public function eliminar(Personal $persona): ?string
    {
        if (ProyectoParticipante::find()->andWhere(['nombre' => $persona->nombre])->exists()) {
            return 'No se puede eliminar a "' . $persona->nombre . '" porque está en el equipo de proyectos. Desactívelo con "Editar".';
        }

        $persona->delete();

        return null;
    }

    /**
     * @return array<string, int> [nombre en minusculas => total de proyectos]
     */
    private function conteoPorNombre(): array
    {
        $filas = ProyectoParticipante::find()
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
