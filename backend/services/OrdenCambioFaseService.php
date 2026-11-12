<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CorrelativoDocumento;
use backend\models\Cotizacion;
use backend\models\EspecificacionEmision;
use backend\models\HistorialProyecto;
use backend\models\Proyecto;
use backend\models\ProyectoAreaIntervencion;
use backend\models\ProyectoParticipante;
use backend\models\SolicitudCotizacion;
use Throwable;
use Yii;

/**
 * Capa de Servicio de las ORDENES DE CAMBIO de una fase (revision del Ing. de
 * DTIC del 2026-11-05, punto 4; parte 6; formato RD-GD-FR-006).
 *
 * No confundir con OrdenCambioService, que es "reabrir la lista" (la tabla
 * redes.OrdenCambio conservo ese nombre).
 *
 * Una orden de cambio es un PRESUPUESTO ADICIONAL dentro de una fase ya
 * aprobada: "se compraron 100 m de cable y piden 50 m mas -> un presupuesto
 * solo de los 50 m". Es una FILA MAS de redes.Proyecto (decidido con el
 * desarrollador el 2026-11-09), con el mismo numero de proyecto y la misma
 * fase, idFaseOrigen = la fase y numeroOrdenCambio = 1, 2... Asi tiene sola su
 * lista (con su codigo LM), sus ET (emisiones propias, con la cantidad de la
 * orden), sus solicitudes, su presupuesto (con su codigo PR) y su carta.
 *
 * REGLAS DE NEGOCIO:
 *  - solo se registra sobre una FASE (no sobre otra orden) con su presupuesto
 *    APROBADO; una fase puede tener varias (N.° 1, 2...);
 *  - del FR-006 el sistema guarda descripcion, motivo y tipo de cambio (el
 *    formulario se sigue haciendo en Word y se le adjunta el presupuesto);
 *  - copia de la fase los datos del proyecto, el area de intervencion, el
 *    equipo y el lugar de entrega por defecto; no tiene codigo PT (la ficha
 *    tecnica es una por proyecto) ni cambia la version de la ficha;
 *  - descripcion, motivo y tipo se cambian mientras su presupuesto no este
 *    aprobado ni cancelado;
 *  - si NO se aprueba, se ELIMINA: con su lista, solicitudes, cotizaciones y
 *    presupuesto; sus ET quedan ANULADAS en el registro (pasan a la fase,
 *    porque una emision siempre es de un proyecto) y los numeros LM, SC, PR y
 *    ET no se reutilizan. Queda anotado en el historial de la fase.
 */
class OrdenCambioFaseService
{
    private HistorialService $historial;

    public function __construct()
    {
        $this->historial = new HistorialService();
    }

    /** Solo sobre una fase (no otra orden) con su presupuesto aprobado. */
    public function puedeRegistrar(Proyecto $fase): bool
    {
        return !$fase->esOrdenCambio() && $fase->tienePresupuestoAprobado();
    }

    /** La orden vacia para el formulario "Registrar orden de cambio". */
    public function nueva(Proyecto $fase): Proyecto
    {
        $orden = new Proyecto(['scenario' => Proyecto::SCENARIO_ORDEN_CAMBIO]);
        $orden->idFaseOrigen = $fase->id;
        $orden->numeroOrdenCambio = $this->siguienteNumero($fase);

        return $orden;
    }

    /**
     * Registra la orden: descripcion, motivo y tipo del formulario; todo lo
     * demas, de la fase. Numero de orden y codigo LM en la misma transaccion.
     */
    public function crear(Proyecto $fase, Proyecto $orden, array $datosFormulario): bool
    {
        if (!$this->puedeRegistrar($fase)) {
            $orden->addError('descripcionCambio', 'Una orden de cambio se registra sobre una fase con su presupuesto aprobado.');

            return false;
        }

        $orden->scenario = Proyecto::SCENARIO_ORDEN_CAMBIO;
        if (!$orden->load($datosFormulario) || !$orden->validate()) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach (Proyecto::ATRIBUTOS_COMUNES as $atributo) {
                $orden->$atributo = $fase->$atributo;
            }
            $orden->numeroProyecto = $fase->numeroProyecto;
            $orden->numeroFase = $fase->numeroFase;
            $orden->idFaseOrigen = $fase->id;
            $orden->numeroOrdenCambio = $this->siguienteNumero($fase);
            $orden->lugarEntrega = $fase->lugarEntrega;
            // Sin codigo PT: la ficha tecnica es una por proyecto (la de la fase 1).
            $orden->codigo = null;
            $orden->codigoLista = CorrelativoDocumento::codigo(
                CorrelativoDocumento::TIPO_LISTA,
                (new CodificacionService())->tomar(CorrelativoDocumento::TIPO_LISTA),
                $fase->proceso->codigo ?? CorrelativoDocumento::PROCESO
            );
            $orden->save(false);

            $this->copiarAreasYEquipo($fase, $orden);

            $detalle = $orden->textoOrdenCambio() . ' — ' . $orden->tipoCambioLabel() . "\n" . $orden->descripcionCambio;
            $this->historial->registrar($orden, HistorialProyecto::ACCION_ORDEN_CAMBIO_CREADA, $detalle);
            $this->historial->registrar($fase, HistorialProyecto::ACCION_ORDEN_CAMBIO_CREADA, $detalle);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Cambia descripcion, motivo y tipo. Solo con su presupuesto sin aprobar
     * ni cancelar (como cualquier proyecto).
     */
    public function actualizar(Proyecto $orden, array $datosFormulario): bool
    {
        if (!$orden->esOrdenCambio() || !$orden->esEditable()) {
            $orden->addError('descripcionCambio', 'Esta orden de cambio ya no se modifica.');

            return false;
        }

        $antes = [
            'Tipo de cambio' => $orden->tipoCambioLabel(),
            'Descripción del cambio' => (string) $orden->descripcionCambio,
            'Motivo' => (string) $orden->motivoCambio,
        ];

        $orden->scenario = Proyecto::SCENARIO_ORDEN_CAMBIO;
        if (!$orden->load($datosFormulario) || !$orden->validate()) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $orden->save(false);
            $cambios = $this->historial->cambios($antes, [
                'Tipo de cambio' => $orden->tipoCambioLabel(),
                'Descripción del cambio' => (string) $orden->descripcionCambio,
                'Motivo' => (string) $orden->motivoCambio,
            ]);
            if ($cambios !== '') {
                $this->historial->registrar($orden, HistorialProyecto::ACCION_PROYECTO_MODIFICADO, $cambios);
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Elimina una orden de cambio NO aprobada (ver la regla en la cabecera).
     * Devuelve null si se elimino, o el motivo por el que no.
     */
    public function eliminar(Proyecto $orden): ?string
    {
        if (!$orden->esOrdenCambio()) {
            return 'No es una orden de cambio.';
        }

        if ($orden->tienePresupuestoAprobado()) {
            return 'Una orden de cambio aprobada no se elimina.';
        }

        $fase = $orden->faseOrigen;
        $texto = $orden->textoOrdenCambio();
        $transaccion = Yii::$app->db->beginTransaction();

        try {
            // 1. El presupuesto (sus lineas y su carta se borran en cascada). Va
            //    primero: sus lineas apuntan a precios de las cotizaciones.
            $orden->presupuesto?->delete();

            // 2. Las cotizaciones de sus solicitudes (la base no las borra sola).
            $solicitudes = SolicitudCotizacion::find()->select('id')->andWhere(['idProyecto' => $orden->id])->column();
            foreach (Cotizacion::find()->andWhere(['idSolicitudCotizacion' => $solicitudes])->all() as $cotizacion) {
                $cotizacion->delete();
            }

            // 3. Las ET no se borran: las vigentes quedan anuladas y todas pasan
            //    a la fase (una emision siempre es de un proyecto). Sus numeros
            //    no se reutilizan.
            EspecificacionEmision::updateAll(
                ['estado' => EspecificacionEmision::ESTADO_ANULADA, 'motivo' => "$texto eliminada (no se aprobó)."],
                ['idProyecto' => $orden->id, 'estado' => EspecificacionEmision::ESTADO_VIGENTE]
            );
            EspecificacionEmision::updateAll(['idProyecto' => $fase->id, 'idProyectoItem' => null], ['idProyecto' => $orden->id]);

            // 4. El historial queda (no tiene llave foranea), en la orden y en su fase.
            $detalle = $texto . ' — ' . $orden->descripcionCambio . "\nLista " . $orden->codigoLista . ' y sus documentos; las especificaciones emitidas quedan anuladas.';
            $this->historial->registrar($orden, HistorialProyecto::ACCION_ORDEN_CAMBIO_ELIMINADA, $detalle);
            $this->historial->registrar($fase, HistorialProyecto::ACCION_ORDEN_CAMBIO_ELIMINADA, $detalle);

            // 5. La orden: su lista, solicitudes, area y equipo se borran en cascada.
            $orden->delete();

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return null;
    }

    /**
     * El numero que toma la proxima orden de la fase: el mayor de las que
     * quedan + 1 (si se elimino la ultima, su numero vuelve a usarse; no es un
     * codigo del lineamiento, los codigos LM, SC, PR y ET nunca se repiten).
     */
    private function siguienteNumero(Proyecto $fase): int
    {
        return 1 + (int) Proyecto::find()->andWhere(['idFaseOrigen' => $fase->id])->max('numeroOrdenCambio');
    }

    /** Copia del area de intervencion y del equipo de la fase (filas nuevas de la orden). */
    private function copiarAreasYEquipo(Proyecto $fase, Proyecto $orden): void
    {
        foreach ($fase->areasIntervencion as $area) {
            $copia = new ProyectoAreaIntervencion($area->getAttributes(null, ['id', 'idProyecto']));
            $copia->idProyecto = $orden->id;
            $copia->save(false);
        }

        foreach ($fase->participantes as $participante) {
            $copia = new ProyectoParticipante($participante->getAttributes(null, ['id', 'idProyecto']));
            $copia->idProyecto = $orden->id;
            $copia->save(false);
        }
    }
}
