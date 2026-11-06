<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CorrelativoDocumento;
use backend\models\EspecificacionEmision;
use backend\models\Presupuesto;
use backend\models\Proceso;
use backend\models\Proyecto;

/**
 * Capa de Servicio para Proceso.
 *
 * Ademas del CRUD, arma el desplegable de proceso del formulario de
 * proyecto y da el proceso por defecto de un proyecto nuevo (PI).
 */
class ProcesoService
{
    /**
     * Proceso que se marca por defecto en un proyecto nuevo: lo normal es
     * que el proyecto lo haga "Proyectos de infraestructura de redes y
     * servicios" (revision del 2026-10-29).
     */
    public const CODIGO_POR_DEFECTO = 'PI';

    /**
     * Procesos por codigo, cada uno con cuantos proyectos lo usan.
     *
     * @return array<int, array{proceso: Proceso, proyectos: int}>
     */
    public function listarConConteo(): array
    {
        $proyectos = Proyecto::find()
            ->select(['total' => 'COUNT(*)', 'idProceso'])
            ->andWhere(['not', ['idProceso' => null]])
            ->groupBy('idProceso')
            ->indexBy('idProceso')
            ->column();

        $filas = [];

        foreach (Proceso::find()->orderBy(['codigo' => SORT_ASC])->all() as $proceso) {
            $filas[] = ['proceso' => $proceso, 'proyectos' => (int) ($proyectos[$proceso->id] ?? 0)];
        }

        return $filas;
    }

    /**
     * Procesos activos como [id => "PI – nombre"]. Si el proyecto ya tenia
     * uno que despues se desactivo, se agrega igual: si no, el desplegable
     * lo perderia al editar.
     *
     * @return array<int, string>
     */
    public function opciones(?int $actual = null): array
    {
        $query = Proceso::find()->andWhere(['activo' => true]);

        if ($actual !== null) {
            $query->orWhere(['id' => $actual]);
        }

        $opciones = [];

        foreach ($query->orderBy(['codigo' => SORT_ASC])->all() as $proceso) {
            $opciones[$proceso->id] = $proceso->etiqueta();
        }

        return $opciones;
    }

    /**
     * Id del proceso por defecto (PI), o null si no existe o esta inactivo.
     */
    public function idPorDefecto(): ?int
    {
        $id = Proceso::find()->select('id')->andWhere(['codigo' => self::CODIGO_POR_DEFECTO, 'activo' => true])->scalar();

        return $id === false || $id === null ? null : (int) $id;
    }

    public function obtener(int $id): ?Proceso
    {
        return Proceso::findOne($id);
    }

    public function crear(Proceso $proceso, array $datosFormulario): bool
    {
        return $proceso->load($datosFormulario) && $proceso->save();
    }

    /**
     * REGLA DE NEGOCIO: el codigo de un proceso que ya usa algun proyecto o
     * documento no se cambia (es parte del codigo de los presupuestos y las
     * especificaciones). El nombre y el estado si.
     */
    public function actualizar(Proceso $proceso, array $datosFormulario): bool
    {
        if (!$proceso->load($datosFormulario)) {
            return false;
        }

        if (!$proceso->validate()) {
            return false;
        }

        if ($proceso->isAttributeChanged('codigo') && $this->enUso($proceso)) {
            $proceso->addError('codigo', 'No se puede cambiar el código: ya lo usan proyectos o documentos.');

            return false;
        }

        return $proceso->save(false);
    }

    /**
     * Devuelve null si se elimino, o el motivo por el que no se puede.
     *
     * REGLA DE NEGOCIO: un proceso en uso no se elimina, se desactiva. La FK
     * de Proyecto ya lo impediria; se revisa antes para dar un mensaje claro.
     * Tambien cuenta si solo lo tienen documentos (un proyecto que despues
     * cambio de proceso): su cabecera "Proceso:" saca el nombre de aqui.
     */
    public function eliminar(Proceso $proceso): ?string
    {
        if ($this->enUso($proceso)) {
            return 'No se puede eliminar "' . $proceso->etiqueta() . '" porque lo usan proyectos o documentos. Desactívelo con "Editar".';
        }

        $proceso->delete();

        return null;
    }

    /**
     * Si lo tiene algun proyecto, especificacion emitida o presupuesto. Se
     * mira el codigo GUARDADO (getOldAttribute): al editar, $proceso ya trae
     * el codigo nuevo.
     */
    private function enUso(Proceso $proceso): bool
    {
        $codigo = (string) ($proceso->getOldAttribute('codigo') ?? $proceso->codigo);
        $prefijo = static fn (string $tipo): string => CorrelativoDocumento::UNIDAD . '-' . $codigo . '-' . $tipo . '-%';

        return Proyecto::find()->andWhere(['idProceso' => $proceso->id])->exists()
            || EspecificacionEmision::find()->andWhere(['proceso' => $codigo])->exists()
            || Presupuesto::find()->andWhere(['like', 'numeroPresupuesto', $prefijo(CorrelativoDocumento::TIPO_PRESUPUESTO), false])->exists()
            || Proyecto::find()->andWhere(['like', 'codigoLista', $prefijo(CorrelativoDocumento::TIPO_LISTA), false])->exists();
    }
}
