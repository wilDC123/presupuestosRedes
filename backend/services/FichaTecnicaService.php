<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\VersionDocumento;
use backend\models\EspecificacionEmision;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\SolicitudCotizacion;

/**
 * La FICHA TECNICA DEL PROYECTO (PDF, proyecto/ficha-pdf.php) y los montos que
 * tambien muestra la vista del proyecto: el COSTO TOTAL (suma de los
 * presupuestos aprobados de fases y ordenes de cambio) y el total del
 * presupuesto de cada fase. Solo lee: no cambia datos.
 *
 * Reglas en docs/reglas/ficha-tecnica.md.
 */
class FichaTecnicaService
{
    private HistorialService $historial;

    public function __construct()
    {
        $this->historial = new HistorialService();
    }

    /**
     * Lo que imprime la FICHA TECNICA DEL PROYECTO: UNA por proyecto, con
     * TODAS sus fases (revision del 2026-11-05, punto 2; parte 5d): arriba lo
     * del proyecto (la vista lo toma del modelo) y despues un bloque por fase
     * con su area, equipo, fechas y documentos de su carpeta economica. Sale
     * igual desde cualquier fase.
     *
     * Version y aprobacion se CALCULAN (no hay tabla): cada fase que se aprueba
     * es una version, contadas en el ORDEN EN QUE SE APROBARON (la primera
     * aprobada da 1.0, la segunda 1.1..., sea la fase 1 o la 2: desde el
     * 2026-11-09 varias fases se elaboran a la vez). "Reabrir la ficha tecnica"
     * es agregar una fase; una ORDEN DE CAMBIO no cambia la version. Mientras
     * ALGUNA fase tenga su presupuesto sin aprobar, la ficha tecnica del
     * proyecto es la version siguiente en BORRADOR (marca de agua). Una fase
     * con el presupuesto CANCELADO no cuenta como pendiente (nunca se
     * aprobara). La fecha de la cabecera es la de creacion de la fase mas
     * nueva: "arriba va la fecha de creacion del documento". Desde el
     * 2026-11-09 (formatos corregidos v3, parte 6c) la ficha NO lleva firmas.
     *
     * Cada fase trae su CARPETA ECONOMICA (carpeta()) y, dentro, la de cada
     * una de sus ORDENES DE CAMBIO ('ordenes'). 'costo' es el TOTAL UNICO del
     * proyecto (costoTotal()).
     *
     * @return array{
     *   codigo: string|null,
     *   fases: array<int, array<string, mixed>>,
     *   variasFases: bool,
     *   costo: array{total: float, partes: array<int, array{rotulo: string, monto: float}>},
     *   version: string,
     *   fechaDocumento: string|null,
     *   aprobada: bool,
     *   fechaAprobacion: string
     * }
     */
    public function datosFicha(Proyecto $proyecto): array
    {
        /** @var Proyecto[] $fases */
        $fases = $proyecto->numeroProyecto !== null ? $proyecto->fases : [$proyecto];
        $fases = $fases === [] ? [$proyecto] : $fases;
        $aprobadas = array_values(array_filter($fases, fn (Proyecto $fase) => $fase->tienePresupuestoAprobado()));
        $pendientes = array_filter(
            $fases,
            fn (Proyecto $fase) => !$fase->tienePresupuestoAprobado() && ($fase->presupuesto->estado ?? null) !== Presupuesto::ESTADO_CANCELADO
        );
        $todasAprobadas = $aprobadas !== [] && $pendientes === [];
        // La aprobada mas reciente (a igual fecha, la de fase mayor) da la fecha de aprobacion.
        usort($aprobadas, static fn (Proyecto $a, Proyecto $b) => [(string) $a->presupuesto->fechaAprobacion, (int) $a->numeroFase]
            <=> [(string) $b->presupuesto->fechaAprobacion, (int) $b->numeroFase]);
        $presupuesto = $todasAprobadas ? end($aprobadas)->presupuesto : null;
        $creaciones = array_map(static fn (Proyecto $fase) => (string) $fase->fechaCreacion, $fases);

        $porFase = [];
        foreach ($fases as $fase) {
            $porFase[] = $this->carpeta($fase) + [
                'fechas' => $this->historial->fechas($fase),
                'ordenes' => array_map(fn (Proyecto $orden) => $this->carpeta($orden), $fase->ordenesCambio),
            ];
        }

        return [
            'codigo' => $fases[0]->codigo,
            'fases' => $porFase,
            'variasFases' => count($fases) > 1,
            'costo' => $this->costoTotal($proyecto),
            'version' => VersionDocumento::texto(max(1, $todasAprobadas ? count($aprobadas) : count($aprobadas) + 1)),
            'fechaDocumento' => max($creaciones) === '' ? null : max($creaciones),
            'aprobada' => $todasAprobadas,
            'fechaAprobacion' => $presupuesto !== null ? $presupuesto->fechaAprobacionFormateada() : '',
        ];
    }

    /**
     * La CARPETA ECONOMICA de una fase o de una orden de cambio, para la ficha
     * tecnica: lista de materiales, presupuesto (y su total), especificaciones
     * vigentes, solicitudes con sus proformas recibidas. 'reemisiones' =
     * [id de la emision vigente => motivo] de las ET reemitidas (el motivo
     * queda en la emision anterior, la "reemplazada por" la vigente).
     *
     * @return array{proyecto: Proyecto, monto: float|null, especificaciones: EspecificacionEmision[], reemisiones: array<int, string>, solicitudes: SolicitudCotizacion[], lista: array}
     */
    private function carpeta(Proyecto $proyecto): array
    {
        $especificaciones = (new EspecificacionEmisionService())->vigentesDelProyecto($proyecto);
        $reemitidas = array_map(
            static fn (EspecificacionEmision $e): int => (int) $e->id,
            array_filter($especificaciones, static fn (EspecificacionEmision $e): bool => (int) $e->versionDocumento > 1)
        );

        return [
            'proyecto' => $proyecto,
            'monto' => $this->montoPresupuesto($proyecto),
            'especificaciones' => $especificaciones,
            'reemisiones' => $reemitidas === [] ? [] : EspecificacionEmision::find()
                ->select(['motivo', 'idReemplazadaPor'])
                ->andWhere(['idReemplazadaPor' => $reemitidas])
                ->indexBy('idReemplazadaPor')
                ->column(),
            'solicitudes' => (new SolicitudCotizacionService())->listar($proyecto),
            'lista' => (new ListaItemsService())->documento($proyecto),
        ];
    }

    /**
     * COSTO TOTAL DEL PROYECTO (parte 6c, formatos corregidos v3): UN solo
     * total, la suma de los presupuestos APROBADOS de todas sus fases y de sus
     * ordenes de cambio, en orden (cada fase seguida de sus ordenes). Las
     * partes, para el detalle "Fase 1: 5,732.50 + orden de cambio N.° 1:
     * 1,870.00 + fase 2: 3,026.00"; con fase unica, la fase se llama
     * "Presupuesto".
     *
     * @return array{total: float, partes: array<int, array{rotulo: string, monto: float}>}
     */
    public function costoTotal(Proyecto $proyecto): array
    {
        $base = $proyecto->esOrdenCambio() ? $proyecto->faseOrigen : $proyecto;
        /** @var Proyecto[] $fases */
        $fases = $base->numeroProyecto !== null && $base->fases !== [] ? $base->fases : [$base];
        $variasFases = count($fases) > 1;
        $partes = [];

        foreach ($fases as $fase) {
            foreach (array_merge([$fase], $fase->ordenesCambio) as $fila) {
                if (!$fila->tienePresupuestoAprobado()) {
                    continue;
                }
                $rotulo = $fila->esOrdenCambio()
                    ? 'orden de cambio N.° ' . (int) $fila->numeroOrdenCambio
                    : ($variasFases ? 'fase ' . (int) $fila->numeroFase : 'presupuesto');
                $partes[] = ['rotulo' => $rotulo, 'monto' => (float) $this->montoPresupuesto($fila)];
            }
        }

        // La primera parte con mayuscula: "Fase 1: ... + orden de cambio N.° 1: ...".
        if ($partes !== []) {
            $partes[0]['rotulo'] = mb_strtoupper(mb_substr($partes[0]['rotulo'], 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($partes[0]['rotulo'], 1, null, 'UTF-8');
        }

        return ['total' => array_sum(array_column($partes, 'monto')), 'partes' => $partes];
    }

    /**
     * Total del presupuesto del proyecto (null si aun no tiene presupuesto).
     */
    public function montoPresupuesto(Proyecto $proyecto): ?float
    {
        $presupuesto = $proyecto->presupuesto;

        if ($presupuesto === null) {
            return null;
        }

        return (float) PresupuestoDetalle::find()->andWhere(['idPresupuesto' => $presupuesto->id])->sum('precioTotal');
    }
}
