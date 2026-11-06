<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\UsuarioActual;
use backend\models\HistorialProyecto;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\models\ProyectoParticipante;
use DateTimeImmutable;

/**
 * Capa de Servicio del historial del proyecto (revision del 2026-10-29,
 * paso 6). Los demas servicios le avisan cada hecho en el momento en que
 * ocurre, dentro de su misma transaccion: si el cambio se deshace, el
 * registro tambien.
 *
 * Se registra desde los servicios y no automaticamente en cada save() de
 * los modelos: el sistema usa mucho updateAll()/deleteAll(), que no pasan
 * por esos eventos, y lo que se quiere guardar son hechos del negocio
 * ("se cambio el precio del switch"), no filas de la base.
 *
 * Tambien calcula las fechas del proyecto (creacion, revision, aprobacion,
 * ultima modificacion).
 */
class HistorialService
{
    /**
     * Agrega un hecho al historial del proyecto, con la fecha y hora de
     * ahora y el usuario que inicio sesion (null en consola o en los tests).
     */
    public function registrar(Proyecto $proyecto, string $accion, ?string $detalle = null, ?string $estado = null): void
    {
        $fila = new HistorialProyecto([
            'fecha' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            // Sin sesion (consola, tests): el hecho se registra sin usuario.
            'usuario' => UsuarioActual::nombre(),
            'idProyecto' => $proyecto->id,
            // Copia para reconocer el proyecto aunque se elimine: su numero (y la fase si hay varias).
            'codigoProyecto' => $proyecto->identificador(),
            'accion' => $accion,
            'estado' => $estado,
            'detalle' => $detalle === null || trim($detalle) === '' ? null : $detalle,
        ]);
        $fila->save(false);
    }

    /**
     * Todo el historial del proyecto, de lo mas nuevo a lo mas antiguo.
     *
     * @return HistorialProyecto[]
     */
    public function delProyecto(int $idProyecto): array
    {
        return HistorialProyecto::find()
            ->andWhere(['idProyecto' => $idProyecto])
            ->orderBy(['fecha' => SORT_DESC, 'id' => SORT_DESC])
            ->all();
    }

    /**
     * Las fechas del proyecto, para su vista y la ficha tecnica:
     *  - creacion: Proyecto.fechaCreacion,
     *  - revision: la ultima vez que el presupuesto paso a "en revision",
     *  - aprobacion: la del presupuesto (aprobar = cerrar el proyecto),
     *  - ultimaModificacion y ultimoUsuario: el ultimo hecho del historial.
     * null donde todavia no hay.
     *
     * @return array{creacion: string|null, revision: string|null, aprobacion: string|null, ultimaModificacion: string|null, ultimoUsuario: string|null}
     */
    public function fechas(Proyecto $proyecto): array
    {
        $revision = HistorialProyecto::find()
            ->andWhere(['idProyecto' => $proyecto->id, 'accion' => HistorialProyecto::ACCION_PRESUPUESTO_ESTADO, 'estado' => Presupuesto::ESTADO_EN_REVISION])
            ->max('fecha');

        $ultimo = HistorialProyecto::find()
            ->andWhere(['idProyecto' => $proyecto->id])
            ->orderBy(['fecha' => SORT_DESC, 'id' => SORT_DESC])
            ->one();

        $presupuesto = $proyecto->presupuesto;

        return [
            'creacion' => $proyecto->fechaCreacion,
            'revision' => $revision ?: null,
            'aprobacion' => $presupuesto !== null && $presupuesto->estado === Presupuesto::ESTADO_APROBADO ? $presupuesto->fechaAprobacion : null,
            'ultimaModificacion' => $ultimo?->fecha,
            'ultimoUsuario' => $ultimo?->usuarioTexto(),
        ];
    }

    /**
     * Lo que se compara al editar un proyecto, como texto para leer: [rotulo
     * => valor]. Se toma antes de guardar y despues; las diferencias van al
     * historial (cambios()).
     *
     * @return array<string, string>
     */
    public function foto(Proyecto $proyecto): array
    {
        $equipo = array_map(
            static fn (ProyectoParticipante $p) => $p->nombre . ' (' . ($p->rol->nombre ?? '?') . ($p->esResponsable ? ', responsable' : '') . ')',
            $proyecto->participantes
        );

        return [
            $proyecto->getAttributeLabel('nombre') => (string) $proyecto->nombre,
            $proyecto->getAttributeLabel('descripcion') => (string) $proyecto->descripcion,
            $proyecto->getAttributeLabel('idTipoProyecto') => (string) ($proyecto->tipoProyecto->nombre ?? ''),
            $proyecto->getAttributeLabel('idProceso') => (string) ($proyecto->proceso->codigo ?? ''),
            $proyecto->getAttributeLabel('unidadDependencia') => (string) $proyecto->unidadDependencia,
            $proyecto->getAttributeLabel('unidadSolicitante') => (string) $proyecto->unidadSolicitante,
            $proyecto->getAttributeLabel('lugarEntrega') => (string) $proyecto->lugarEntrega,
            $proyecto->getAttributeLabel('areas') => $proyecto->areaIntervencionTexto(),
            $proyecto->getAttributeLabel('equipo') => implode('; ', $equipo),
            'Fase anterior' => (string) ($proyecto->proyectoAnterior?->identificador() ?? ''),
        ];
    }

    /**
     * "Unidad solicitante: Decanato → Rectorado", una linea por dato que
     * cambio entre dos fotos ("—" = vacio). Vacio si no cambio nada.
     *
     * @param array<string, string> $antes
     * @param array<string, string> $despues
     */
    public function cambios(array $antes, array $despues): string
    {
        $lineas = [];

        foreach ($despues as $rotulo => $valor) {
            $anterior = $antes[$rotulo] ?? '';
            if (trim($anterior) !== trim($valor)) {
                $lineas[] = $rotulo . ': ' . $this->oGuion($anterior) . ' → ' . $this->oGuion($valor);
            }
        }

        return implode("\n", $lineas);
    }

    private function oGuion(string $valor): string
    {
        return trim($valor) === '' ? '—' : trim($valor);
    }

}
