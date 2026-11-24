<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\UsuarioActual;
use backend\models\CorrelativoDocumento;
use backend\models\EspecificacionEmision;
use backend\models\HistorialProyecto;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoCondicionPrevia;
use backend\models\ProyectoItem;
use backend\models\ProyectoMaterialDtic;
use Throwable;
use Yii;

/**
 * Capa de Servicio de la VERSION DE UNA FASE APROBADA (decidido con el
 * desarrollador el 2026-11-23/24).
 *
 * Un presupuesto aprobado nunca se edita (Proyecto::esEditable()). Si hace
 * falta corregirlo -- cotizaciones vencidas por la demora, cantidades,
 * especificaciones, agregar o quitar un item -- y no alcanza con una orden de
 * cambio (que solo AGREGA, nunca quita ni corrige lo aprobado), se abre una
 * VERSION NUEVA de la misma fase.
 *
 * Es, igual que la orden de cambio, una FILA MAS de redes.Proyecto: mismo
 * numeroProyecto y numeroFase que la fase, con idVersionAnterior apuntando a
 * la fila de la que se abrio y numeroVersionMayor/numeroVersionMenor
 * ("1.0" -> subversion "1.1", "1.2"... -> version nueva "2.0"). Copia de la
 * fase el area, el equipo, el lugar de entrega, las secciones sin precio, la
 * lista de items y el presupuesto, con sus precios COPIADOS TAL CUAL (no se
 * recalculan): el encargado los corrige a mano donde haga falta.
 *
 * Al momento de copiar, ningun item cambio todavia (es una copia exacta de lo
 * aprobado): por eso cada item que ya tenia una ficha de especificacion con ET
 * VIGENTE se lleva la ficha y REEMITE ese ET en el item nuevo (mismo codigo,
 * version del documento + 1, mismas condiciones de entrega), igual que ya
 * pasa hoy al "cambiar el lugar de entrega" (EspecificacionEmisionService::
 * emitir() con $corrige); el ET viejo queda "reemplazado por" el nuevo. Asi
 * abrir una version solo para actualizar precios no le da trabajo extra al
 * encargado. Si DESPUES, ya en la version nueva, cambia o quita un item, sigue
 * el camino normal que ya existe (ListaItemsService: anula o vuelve a emitir).
 *
 * REGLAS DE NEGOCIO:
 *  - solo se abre sobre una FASE (no una orden de cambio) con su presupuesto
 *    APROBADO, y solo sobre su ULTIMA version (Proyecto::esUltimaVersion());
 *  - el motivo es siempre obligatorio, a mano;
 *  - no tiene codigo PT (la ficha tecnica es una por proyecto) ni copia el
 *    codigo de la lista ni el del presupuesto: saca los suyos;
 *  - la fila vieja queda esVersionVigente = false: de solo lectura, pero no
 *    se borra ni se toca (sus ET y su carta de entrega quedan como estaban).
 */
class VersionProyectoService
{
    private HistorialService $historial;

    public function __construct()
    {
        $this->historial = new HistorialService();
    }

    /** Solo sobre una fase (no una orden de cambio) con su presupuesto aprobado, en su ultima version. */
    public function puedeAbrir(Proyecto $fase): bool
    {
        return !$fase->esOrdenCambio() && $fase->esUltimaVersion() && $fase->tienePresupuestoAprobado();
    }

    /** La version vacia para el formulario "Abrir nueva versión". */
    public function nueva(Proyecto $fase): Proyecto
    {
        $version = new Proyecto(['scenario' => Proyecto::SCENARIO_VERSION]);
        $version->idVersionAnterior = $fase->id;

        return $version;
    }

    /**
     * Abre la version: copia todo lo de la fase (ver la cabecera de la
     * clase) a $version y marca $fase como ya no vigente, en una transaccion.
     *
     * $tipoVersion: 'menor' (subversion: 1.0 -> 1.1) o 'mayor' (version nueva:
     * 1.1 -> 2.0), elegido por el encargado al abrirla.
     */
    public function crear(Proyecto $fase, Proyecto $version, array $datosFormulario, string $tipoVersion): bool
    {
        if (!$this->puedeAbrir($fase)) {
            $version->addError('motivoVersion', 'Una versión se abre sobre una fase (no una orden de cambio) con su presupuesto aprobado, en su última versión.');

            return false;
        }

        if (!in_array($tipoVersion, ['menor', 'mayor'], true)) {
            $version->addError('motivoVersion', 'Elija si es una subversión o una versión nueva.');

            return false;
        }

        $version->scenario = Proyecto::SCENARIO_VERSION;
        if (!$version->load($datosFormulario) || !$version->validate()) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach (Proyecto::ATRIBUTOS_COMUNES as $atributo) {
                $version->$atributo = $fase->$atributo;
            }
            $version->numeroProyecto = $fase->numeroProyecto;
            $version->numeroFase = $fase->numeroFase;
            $version->idVersionAnterior = $fase->id;
            [$version->numeroVersionMayor, $version->numeroVersionMenor] = $this->siguienteNumero($fase, $tipoVersion);
            $version->lugarEntrega = $fase->lugarEntrega;
            $version->estadoLista = Proyecto::LISTA_EDICION;
            // La lista ya nace con los items copiados (no pasa por "Plantilla", a diferencia de una fase u orden
            // de cambio nueva): listaIniciada = true para que la barra de pasos la muestre bien.
            $version->listaIniciada = true;
            // Sin codigo PT: la ficha tecnica es una por proyecto (Proyecto::codigoFicha()).
            $version->codigo = null;
            $version->codigoLista = CorrelativoDocumento::codigo(
                CorrelativoDocumento::TIPO_LISTA,
                (new CodificacionService())->tomar(CorrelativoDocumento::TIPO_LISTA),
                $fase->proceso->codigo ?? CorrelativoDocumento::PROCESO
            );
            $version->save(false);

            (new OrdenCambioFaseService())->copiarAreasYEquipo($fase, $version);
            $this->copiarSeccionesSinPrecio($fase, $version);
            $this->copiarListaYPresupuesto($fase, $version);

            $fase->esVersionVigente = false;
            $fase->save(false);

            $detalle = 'Versión ' . $version->numeroVersionTexto() . ' abierta desde la versión ' . $fase->numeroVersionTexto()
                . '. Motivo: ' . $version->motivoVersion;
            $this->historial->registrar($fase, HistorialProyecto::ACCION_VERSION_CREADA, $detalle);
            $this->historial->registrar($version, HistorialProyecto::ACCION_VERSION_CREADA, $detalle);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * mayor -> siguiente mayor, menor vuelve a 0 (1.1 -> 2.0).
     * menor -> mismo mayor, siguiente menor (1.0 -> 1.1).
     *
     * @return array{0: int, 1: int} [numeroVersionMayor, numeroVersionMenor]
     */
    private function siguienteNumero(Proyecto $fase, string $tipoVersion): array
    {
        return $tipoVersion === 'mayor'
            ? [(int) $fase->numeroVersionMayor + 1, 0]
            : [(int) $fase->numeroVersionMayor, (int) $fase->numeroVersionMenor + 1];
    }

    /** Copia "Materiales que aporta la DTIC" y "Trabajos complementarios" (secciones sin precio de la lista). */
    private function copiarSeccionesSinPrecio(Proyecto $fase, Proyecto $version): void
    {
        foreach ($fase->materialesDtic as $material) {
            $copia = new ProyectoMaterialDtic($material->getAttributes(null, ['id', 'idProyecto']));
            $copia->idProyecto = $version->id;
            $copia->save(false);
        }

        foreach ($fase->condicionesPrevias as $condicion) {
            $copia = new ProyectoCondicionPrevia($condicion->getAttributes(null, ['id', 'idProyecto']));
            $copia->idProyecto = $version->id;
            $copia->save(false);
        }
    }

    /**
     * Copia la lista de items (con su ficha de especificacion, reemitiendo el
     * ET si tenia uno vigente: ver reemitirEtSiCorresponde()) y el
     * presupuesto, con sus precios COPIADOS TAL CUAL de la linea aprobada --
     * no se recalculan ni se ligan a la cotizacion vieja (es de otro item, de
     * otra fila de Proyecto).
     */
    private function copiarListaYPresupuesto(Proyecto $fase, Proyecto $version): void
    {
        $presupuestoViejo = $fase->presupuesto;

        $presupuestoNuevo = new Presupuesto();
        $presupuestoNuevo->idProyecto = $version->id;
        $presupuestoNuevo->estado = Presupuesto::ESTADO_EN_PROCESO;
        $presupuestoNuevo->manoDeObra = $presupuestoViejo->manoDeObra;
        $presupuestoNuevo->numeroPresupuesto = CorrelativoDocumento::codigo(
            CorrelativoDocumento::TIPO_PRESUPUESTO,
            (new CodificacionService())->tomar(CorrelativoDocumento::TIPO_PRESUPUESTO),
            $fase->proceso->codigo ?? CorrelativoDocumento::PROCESO
        );
        $presupuestoNuevo->save(false);

        foreach ($fase->items as $itemViejo) {
            $itemNuevo = new ProyectoItem($itemViejo->getAttributes(null, ['id', 'idProyecto']));
            $itemNuevo->idProyecto = $version->id;
            $itemNuevo->save(false);

            $this->reemitirEtSiCorresponde($version, $itemViejo, $itemNuevo);

            $lineaVieja = $itemViejo->presupuestoDetalle;
            if ($lineaVieja === null) {
                continue;
            }

            // Sin idCotizacionDetalle (es de otro item, de otra fila de Proyecto) ni justificacion (era de
            // por que no se elegia el menor precio DE ESAS cotizaciones, que ya no aplican): precio directo,
            // con su origen anotado en fuentePrecio.
            // precioTotal: columna CALCULADA por SQL Server (cantidad x precioUnitario); nunca se asigna a mano.
            $lineaNueva = new PresupuestoDetalle($lineaVieja->getAttributes(null, ['id', 'idPresupuesto', 'idProyectoItem', 'idCotizacionDetalle', 'justificacion', 'precioTotal']));
            $lineaNueva->idPresupuesto = $presupuestoNuevo->id;
            $lineaNueva->idProyectoItem = $itemNuevo->id;
            $lineaNueva->fuentePrecio = 'Copiado de la versión ' . $fase->numeroVersionTexto();
            $lineaNueva->save(false);
        }
    }

    /**
     * Si el item viejo tenia una ficha de especificacion con ET VIGENTE, la
     * lleva al item nuevo y reemite ese ET (mismo codigo, version del
     * documento + 1, mismas condiciones de entrega): al copiar, el item
     * todavia no cambio nada, asi que su ET sigue siendo valido. El ET viejo
     * queda "reemplazado por" el nuevo (mismo mecanismo que
     * EspecificacionEmisionService::reemitir()). Un item sin ficha, o con
     * ficha pero sin ET vigente (no deberia pasar en un presupuesto aprobado,
     * pero por si acaso), no hace nada.
     */
    private function reemitirEtSiCorresponde(Proyecto $version, ProyectoItem $itemViejo, ProyectoItem $itemNuevo): void
    {
        $emisionVieja = $itemViejo->emisionVigente;

        if ($emisionVieja === null) {
            return;
        }

        $itemNuevo->idEspecificacion = $itemViejo->idEspecificacion;
        $itemNuevo->save(false);

        $motivo = 'Versión ' . $version->numeroVersionTexto() . ' de la fase abierta (sin cambios en el ítem).';
        $emisionVieja->updateAttributes(['estado' => EspecificacionEmision::ESTADO_REEMPLAZADA, 'motivo' => $motivo]);

        $nueva = (new EspecificacionEmisionService())->emitir($itemNuevo, UsuarioActual::nombre(), [
            'lugarEntrega' => $emisionVieja->lugarEntrega,
            'validezOfertaDias' => $emisionVieja->validezOfertaDias,
            'tiempoEntregaDias' => $emisionVieja->tiempoEntregaDias,
        ], $emisionVieja);

        $emisionVieja->updateAttributes(['idReemplazadaPor' => $nueva->id]);
    }
}
