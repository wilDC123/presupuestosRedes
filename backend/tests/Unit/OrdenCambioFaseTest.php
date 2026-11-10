<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\EspecificacionEmision;
use backend\models\HistorialProyecto;
use backend\models\Personal;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\models\TipoOrdenCambio;
use backend\services\OrdenCambioFaseService;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use backend\services\RolProyectoService;
use backend\services\TipoOrdenCambioService;
use Codeception\Test\Unit;

/**
 * Orden de cambio (revision del 2026-11-05, punto 4; parte 6): un presupuesto
 * adicional de una fase aprobada, guardado como una fila mas de
 * redes.Proyecto (mismo numero y fase, idFaseOrigen + numeroOrdenCambio).
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class OrdenCambioFaseTest extends Unit
{
    private ProyectoService $proyectos;
    private OrdenCambioFaseService $ordenes;

    protected function _before(): void
    {
        $this->proyectos = new ProyectoService();
        $this->ordenes = new OrdenCambioFaseService();
        (new Personal(['nombre' => 'Ing. Test Orden']))->save(false);
    }

    public function testSoloSobreUnaFaseAprobadaYCopiaLoDeLaFase(): void
    {
        $fase = $this->crearFase();

        // Sin aprobar: no.
        $this->assertFalse($this->ordenes->puedeRegistrar($fase));
        $orden = $this->ordenes->nueva($fase);
        $this->assertFalse($this->ordenes->crear($fase, $orden, $this->datos()));

        $this->aprobar($fase);
        $fase = Proyecto::findOne($fase->id);
        $this->assertTrue($this->ordenes->puedeRegistrar($fase));

        $orden = $this->crearOrden($fase);
        $this->assertSame([(int) $fase->id, 1, (int) $fase->numeroProyecto, (int) $fase->numeroFase], [(int) $orden->idFaseOrigen, (int) $orden->numeroOrdenCambio, (int) $orden->numeroProyecto, (int) $orden->numeroFase]);
        $this->assertSame([$fase->nombre, $fase->unidadDependencia, $fase->lugarEntrega], [$orden->nombre, $orden->unidadDependencia, $orden->lugarEntrega]);
        // Sin codigo PT (la ficha es la de la fase 1) y con su propia lista LM.
        $this->assertNull($orden->codigo);
        $this->assertSame($fase->codigo, $orden->codigoFicha());
        $this->assertMatchesRegularExpression('/^RD-PI-LM-\d{3,}$/', (string) $orden->codigoLista);
        $this->assertNotSame($fase->codigoLista, $orden->codigoLista);
        // Area y equipo copiados de la fase.
        $this->assertSame(['Edificio test orden'], array_map(static fn ($a) => $a->edificio, $orden->areasIntervencion));
        $this->assertSame(['Ing. Test Orden'], array_map(static fn ($p) => $p->nombre, $orden->participantes));
        // Anotado en la orden y en su fase.
        foreach ([$orden->id, $fase->id] as $id) {
            $this->assertTrue(HistorialProyecto::find()->andWhere(['idProyecto' => $id, 'accion' => HistorialProyecto::ACCION_ORDEN_CAMBIO_CREADA])->exists());
        }

        // La segunda de la fase es la N.° 2.
        $this->assertSame(2, (int) $this->crearOrden($fase)->numeroOrdenCambio);
    }

    /** No es una fase: no cuenta en las fases, en "Agregar fase" ni en "(FASE n)". */
    public function testNoEsUnaFase(): void
    {
        $fase = $this->crearFase();
        $this->aprobar($fase);
        $orden = $this->crearOrden(Proyecto::findOne($fase->id));
        $fase = Proyecto::findOne($fase->id);
        $numero = $fase->numeroTexto();

        $this->assertTrue($orden->esOrdenCambio());
        $this->assertSame([(int) $fase->id], array_map(static fn ($f) => (int) $f->id, $fase->fases));
        $this->assertFalse($fase->tieneVariasFases());
        $this->assertTrue($fase->esUltimaFase());
        $this->assertTrue($this->proyectos->puedeAgregarFase($fase));
        $this->assertFalse($this->proyectos->puedeAgregarFase($orden));
        $this->assertFalse($this->ordenes->puedeRegistrar($orden));
        $this->assertSame("Proyecto $numero · Orden de cambio N.° 1", $orden->identificador());
        $this->assertSame("PROYECTO: $numero – PROYECTO TEST ORDEN – ORDEN DE CAMBIO N.° 1", $orden->lineaDocumento());
        $this->assertSame("PROYECTO: $numero – PROYECTO TEST ORDEN", $fase->lineaDocumento());

        // Aprobar la orden no cambia la version de la ficha tecnica del proyecto.
        $this->aprobar($orden);
        $this->assertSame('1.0', $this->proyectos->datosFicha($fase)['version']);
    }

    public function testSeEditaSoloSuDescripcionMotivoYTipo(): void
    {
        $fase = $this->crearFase();
        $this->aprobar($fase);
        $orden = $this->crearOrden(Proyecto::findOne($fase->id));

        // El formulario del proyecto no la toca.
        $this->assertFalse($this->proyectos->actualizar($orden, ['Proyecto' => ['nombre' => 'Otro']]));

        $omision = (int) TipoOrdenCambio::find()->select('id')->where(['like', 'nombre', 'Errores u omisiones'])->scalar();
        $datos = ['Proyecto' => ['descripcionCambio' => 'Ahora 80 m de cable', 'motivoCambio' => 'Se midió de nuevo', 'idTipoOrdenCambio' => (string) $omision, 'nombre' => 'No debe cambiar']];
        $this->assertTrue($this->ordenes->actualizar($orden, $datos), json_encode($orden->errors));
        $orden = Proyecto::findOne($orden->id);
        $this->assertSame(['Ahora 80 m de cable', $omision, 'Proyecto test orden'], [$orden->descripcionCambio, (int) $orden->idTipoOrdenCambio, $orden->nombre]);
        $this->assertSame('Errores u omisiones en la planificación y propuestas técnicas', $orden->tipoCambioLabel());
        // El historial anota el tipo con su nombre, antes y despues.
        $detalle = (string) HistorialProyecto::find()->select('detalle')
            ->andWhere(['idProyecto' => $orden->id, 'accion' => HistorialProyecto::ACCION_PROYECTO_MODIFICADO])->scalar();
        $this->assertStringContainsString('Tipo de cambio: Nuevas solicitudes de los usuarios → Errores u omisiones', $detalle);

        // Aprobada ya no.
        $this->aprobar($orden);
        $this->assertFalse($this->ordenes->actualizar(Proyecto::findOne($orden->id), $datos));
    }

    public function testSiNoSeApruebaSeEliminaYSusEtQuedanAnuladas(): void
    {
        $fase = $this->crearFase();
        $this->aprobar($fase);
        $orden = $this->crearOrden(Proyecto::findOne($fase->id));
        (new Presupuesto(['idProyecto' => $orden->id, 'estado' => Presupuesto::ESTADO_EN_PROCESO]))->save(false);

        $modelo = EspecificacionEmision::find()->one();
        if ($modelo === null) {
            $this->markTestSkipped('La base local no tiene especificaciones emitidas para copiar.');
        }
        $emision = new EspecificacionEmision($modelo->getAttributes(null, ['id', 'idReemplazadaPor']));
        $emision->idProyecto = $orden->id;
        $emision->idProyectoItem = null;
        $emision->numeroNet = 99999;
        $emision->estado = EspecificacionEmision::ESTADO_VIGENTE;
        $emision->save(false);

        $this->assertNull($this->proyectos->eliminar($orden));

        $this->assertNull(Proyecto::findOne($orden->id));
        $emision = EspecificacionEmision::findOne($emision->id);
        $this->assertSame([EspecificacionEmision::ESTADO_ANULADA, (int) $fase->id], [$emision->estado, (int) $emision->idProyecto]);
        $this->assertStringContainsString('Orden de cambio N.° 1 eliminada', (string) $emision->motivo);
        $this->assertTrue(HistorialProyecto::find()->andWhere(['idProyecto' => $fase->id, 'accion' => HistorialProyecto::ACCION_ORDEN_CAMBIO_ELIMINADA])->exists());

        // Una aprobada no se elimina.
        $otra = $this->crearOrden(Proyecto::findOne($fase->id));
        $this->aprobar($otra);
        $this->assertSame('Una orden de cambio aprobada no se elimina.', $this->proyectos->eliminar(Proyecto::findOne($otra->id)));
        // Y la fase con ordenes tampoco (ademas de su presupuesto).
        $this->assertStringContainsString('orden(es) de cambio', (string) $this->proyectos->eliminar(Proyecto::findOne($fase->id)));
    }

    private function crearFase(): Proyecto
    {
        $proyecto = new Proyecto();
        $this->assertTrue($this->proyectos->crear($proyecto, ['Proyecto' => [
            'nombre' => 'Proyecto test orden',
            'idProceso' => (string) (new ProcesoService())->idPorDefecto(),
            'idProyectoAnterior' => '',
            'unidadDependencia' => 'Carrera test orden',
            'unidadSolicitante' => 'Facultad test orden',
            'lugarEntrega' => 'Almacén test',
            'areas' => [['manual' => '1', 'edificio' => 'Edificio test orden']],
            'equipo' => [['nombre' => 'Ing. Test Orden', 'idRol' => (string) (new RolProyectoService())->idProyectista()]],
            'equipoResponsable' => '0',
        ]]), json_encode($proyecto->errors));

        return Proyecto::findOne($proyecto->id);
    }

    private function crearOrden(Proyecto $fase): Proyecto
    {
        $orden = $this->ordenes->nueva($fase);
        $this->assertTrue($this->ordenes->crear($fase, $orden, $this->datos()), json_encode($orden->errors));

        return Proyecto::findOne($orden->id);
    }

    private function datos(): array
    {
        $tipo = TipoOrdenCambio::find()->select('id')->where(['nombre' => 'Nuevas solicitudes de los usuarios'])->scalar();

        return ['Proyecto' => ['descripcionCambio' => '50 m más de cable', 'motivoCambio' => 'Pedido de la unidad', 'idTipoOrdenCambio' => (string) $tipo]];
    }

    /** Catalogo (2026-11-09): un tipo inactivo no se elige, uno en uso no se elimina. */
    public function testElTipoSaleDelCatalogo(): void
    {
        $fase = $this->crearFase();
        $this->aprobar($fase);
        $fase = Proyecto::findOne($fase->id);

        $tipo = new TipoOrdenCambio(['nombre' => 'Tipo test inactivo', 'activo' => false]);
        $tipo->save(false);
        $this->assertArrayNotHasKey($tipo->id, (new TipoOrdenCambioService())->opciones());
        $this->assertArrayHasKey($tipo->id, (new TipoOrdenCambioService())->opciones($tipo->id));

        $orden = $this->ordenes->nueva($fase);
        $datos = $this->datos();
        $datos['Proyecto']['idTipoOrdenCambio'] = (string) $tipo->id;
        $this->assertFalse($this->ordenes->crear($fase, $orden, $datos));
        $this->assertStringContainsString('inactivo', (string) $orden->getFirstError('idTipoOrdenCambio'));

        $usado = TipoOrdenCambio::findOne((int) $this->crearOrden($fase)->idTipoOrdenCambio);
        $this->assertStringContainsString('lo usan órdenes de cambio', (string) (new TipoOrdenCambioService())->eliminar($usado));
        $this->assertNull((new TipoOrdenCambioService())->eliminar($tipo));
    }

    private function aprobar(Proyecto $proyecto): void
    {
        (new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => Presupuesto::ESTADO_APROBADO, 'fechaAprobacion' => '2026-11-09']))->save(false);
    }
}
