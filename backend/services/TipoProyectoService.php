<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Plantilla;
use backend\models\Proyecto;
use backend\models\TipoProyecto;

/**
 * Capa de Servicio para TipoProyecto.
 *
 * Ademas del CRUD, arma el desplegable de tipo para los formularios de
 * proyecto y de plantilla.
 */
class TipoProyectoService
{
    /**
     * Tipos por nombre, cada uno con cuantos proyectos y plantillas lo usan
     * (una consulta agrupada por tabla, no una por tipo).
     *
     * @return array<int, array{tipo: TipoProyecto, proyectos: int, plantillas: int}>
     */
    public function listarConConteo(): array
    {
        $proyectos = $this->conteoPorTipo(Proyecto::find());
        $plantillas = $this->conteoPorTipo(Plantilla::find());

        $filas = [];

        foreach (TipoProyecto::find()->orderBy(['nombre' => SORT_ASC])->all() as $tipo) {
            $filas[] = [
                'tipo' => $tipo,
                'proyectos' => (int) ($proyectos[$tipo->id] ?? 0),
                'plantillas' => (int) ($plantillas[$tipo->id] ?? 0),
            ];
        }

        return $filas;
    }

    /**
     * Tipos activos como [id => nombre]. Si el proyecto o plantilla ya tenia
     * un tipo que despues se desactivo, se agrega igual: si no, el
     * desplegable lo perderia al editar.
     *
     * @return array<int, string>
     */
    public function opciones(?int $actual = null): array
    {
        $query = TipoProyecto::find()->andWhere(['activo' => true]);

        if ($actual !== null) {
            $query->orWhere(['id' => $actual]);
        }

        return $query->select(['nombre', 'id'])->orderBy(['nombre' => SORT_ASC])->indexBy('id')->column();
    }

    public function obtener(int $id): ?TipoProyecto
    {
        return TipoProyecto::findOne($id);
    }

    public function crear(TipoProyecto $tipo, array $datosFormulario): bool
    {
        return $tipo->load($datosFormulario) && $tipo->save();
    }

    public function actualizar(TipoProyecto $tipo, array $datosFormulario): bool
    {
        return $tipo->load($datosFormulario) && $tipo->save();
    }

    /**
     * Devuelve null si se elimino, o el motivo por el que no se puede.
     *
     * REGLA DE NEGOCIO: un tipo en uso no se elimina, se desactiva. Las FK
     * de Proyecto y Plantilla ya lo impedirian; se revisa antes para dar un
     * mensaje claro en vez de un error de la base.
     */
    public function eliminar(TipoProyecto $tipo): ?string
    {
        $enUso = Proyecto::find()->andWhere(['idTipoProyecto' => $tipo->id])->exists()
            || Plantilla::find()->andWhere(['idTipoProyecto' => $tipo->id])->exists();

        if ($enUso) {
            return 'No se puede eliminar "' . $tipo->nombre . '" porque lo usan proyectos o plantillas. Desactívelo con "Editar".';
        }

        $tipo->delete();

        return null;
    }

    /**
     * @param \yii\db\ActiveQuery $query de Proyecto o Plantilla
     * @return array<int, string> [idTipoProyecto => total]
     */
    private function conteoPorTipo($query): array
    {
        return $query
            ->select(['total' => 'COUNT(*)', 'idTipoProyecto'])
            ->andWhere(['not', ['idTipoProyecto' => null]])
            ->groupBy('idTipoProyecto')
            ->indexBy('idTipoProyecto')
            ->column();
    }
}
