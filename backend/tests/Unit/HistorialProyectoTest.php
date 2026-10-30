<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\HistorialProyecto;
use backend\models\Personal;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use backend\services\ElaborarPresupuestoService;
use backend\services\HistorialService;
use backend\services\PresupuestoService;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use backend\services\RolProyectoService;
use Codeception\Test\Unit;

/**
 * Historial del proyecto (revision del 2026-10-29, paso 6): se anotan la
 * creacion y los cambios de datos del proyecto (solo lo que cambio), los
 * precios del presupuesto (antes → despues) y sus cambios de estado; de ahi
 * salen las fechas de revision y de ultima modificacion. Si el proyecto se
 * elimina, el historial queda.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class HistorialProyectoTest extends Unit
{
    private HistorialService $historial;
    private ProyectoService $proyectos;

    protected function _before(): void
    {
        $this->historial = new HistorialService();
        $this->proyectos = new ProyectoService();
        (new Personal(['nombre' => 'Ing. Test Historial']))->save(false);
    }

    public function testAnotaLaCreacionYSoloLosDatosQueCambian(): void
    {
        $proyecto = new Proyecto();
        $datos = $this->datos(['unidadSolicitante' => 'Decanato test']);
        $this->assertTrue($this->proyectos->crear($proyecto, $datos), json_encode($proyecto->errors));

        $this->assertSame([HistorialProyecto::ACCION_PROYECTO_CREADO], $this->acciones($proyecto));

        // Guardar sin cambios no anota nada.
        $this->assertTrue($this->proyectos->actualizar(Proyecto::findOne($proyecto->id), $datos));
        $this->assertCount(1, $this->historial->delProyecto($proyecto->id));

        $datos['Proyecto']['unidadSolicitante'] = 'Rectorado test';
        $datos['Proyecto']['descripcion'] = 'Cámaras del canal';
        $this->assertTrue($this->proyectos->actualizar(Proyecto::findOne($proyecto->id), $datos));

        $ultimo = $this->historial->delProyecto($proyecto->id)[0];
        $this->assertSame(HistorialProyecto::ACCION_PROYECTO_MODIFICADO, $ultimo->accion);
        $this->assertSame("Descripción: — → Cámaras del canal\nUnidad solicitante: Decanato test → Rectorado test", $ultimo->detalle);
        $this->assertSame($proyecto->codigo, $ultimo->codigoProyecto);
        // En los tests no hay sesion.
        $this->assertSame('(antes del historial)', $ultimo->usuarioTexto());
    }

    public function testAnotaLosPreciosConElAnteriorYLosEstadosConSusFechas(): void
    {
        $itemSubcatalogo = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        if ($itemSubcatalogo === null) {
            $this->markTestSkipped('La base local no tiene ítems activos en el subcatálogo.');
        }

        $proyecto = new Proyecto();
        $this->assertTrue($this->proyectos->crear($proyecto, $this->datos([])), json_encode($proyecto->errors));
        Proyecto::updateAll(['estadoLista' => Proyecto::LISTA_EN_COTIZACION], ['id' => $proyecto->id]);
        $item = new ProyectoItem(['idProyecto' => $proyecto->id, 'idSubcatalogo' => $itemSubcatalogo->id, 'idCategoria' => $itemSubcatalogo->idCategoria, 'cantidad' => 2, 'orden' => 1]);
        $item->save(false);

        $elaborar = new ElaborarPresupuestoService();
        $presupuesto = $elaborar->delProyecto(Proyecto::findOne($proyecto->id));
        $this->assertNull($elaborar->elegir($presupuesto, $item->id, ElaborarPresupuestoService::DIRECTO, '100', '', 'Mercado'));
        $this->assertNull($elaborar->elegir($presupuesto, $item->id, ElaborarPresupuestoService::DIRECTO, '80'));
        // Mismo precio, solo otra fuente: no es un cambio de precio.
        $this->assertNull($elaborar->elegir($presupuesto, $item->id, ElaborarPresupuestoService::DIRECTO, '80', '', 'web'));

        $precios = array_values(array_filter($this->historial->delProyecto($proyecto->id), static fn ($h) => $h->accion === HistorialProyecto::ACCION_PRECIOS));
        $this->assertCount(2, $precios);
        $nombre = $itemSubcatalogo->descripcion;
        $this->assertSame($nombre . ': 100.00 (precio directo: Mercado) → 80.00 (precio directo)', $precios[0]->detalle);
        $this->assertSame($nombre . ': 100.00 (precio directo: Mercado)', $precios[1]->detalle);

        // Revision -> observado -> corregir -> revision otra vez -> aprobado.
        $presupuesto->setAttributes(['oficioDtic' => '1', 'areaIntervencion' => 'Test', 'fecha' => date('Y-m-d')], false);
        $presupuesto->save(false);
        $estados = new PresupuestoService();
        foreach ([Presupuesto::ESTADO_EN_REVISION, Presupuesto::ESTADO_OBSERVADO, Presupuesto::ESTADO_EN_PROCESO, Presupuesto::ESTADO_EN_REVISION, Presupuesto::ESTADO_APROBADO] as $estado) {
            $this->assertTrue($estados->cambiarEstado($presupuesto, $estado), $estado);
        }

        $cambios = array_values(array_filter($this->historial->delProyecto($proyecto->id), static fn ($h) => $h->accion === HistorialProyecto::ACCION_PRESUPUESTO_ESTADO));
        $this->assertSame(['aprobado', 'en_revision', 'en_proceso', 'observado', 'en_revision'], array_map(static fn ($h) => $h->estado, $cambios));
        $this->assertSame($presupuesto->numeroPresupuesto . ': En revisión → Aprobado', $cambios[0]->detalle);
        $this->assertSame('Presupuesto: aprobado', $cambios[0]->accionLabel());

        $fechas = $this->historial->fechas(Proyecto::findOne($proyecto->id));
        $this->assertSame(date('Y-m-d'), substr((string) $fechas['revision'], 0, 10));
        $this->assertSame(date('Y-m-d'), substr((string) $fechas['aprobacion'], 0, 10));
        $this->assertSame($cambios[0]->fecha, $fechas['ultimaModificacion']);
        $this->assertContains(HistorialProyecto::ACCION_PRESUPUESTO_CREADO, $this->acciones($proyecto));
    }

    public function testAlEliminarElProyectoSuHistorialQueda(): void
    {
        $proyecto = new Proyecto();
        $this->assertTrue($this->proyectos->crear($proyecto, $this->datos([])), json_encode($proyecto->errors));
        $id = $proyecto->id;

        $this->assertTrue($this->proyectos->eliminar($proyecto));

        $this->assertNull(Proyecto::findOne($id));
        $this->assertSame([HistorialProyecto::ACCION_PROYECTO_ELIMINADO, HistorialProyecto::ACCION_PROYECTO_CREADO], array_map(static fn ($h) => $h->accion, $this->historial->delProyecto($id)));
    }

    /**
     * @return string[] acciones del historial, de la mas nueva a la mas antigua
     */
    private function acciones(Proyecto $proyecto): array
    {
        return array_map(static fn (HistorialProyecto $h) => $h->accion, $this->historial->delProyecto($proyecto->id));
    }

    private function datos(array $cambios): array
    {
        return ['Proyecto' => array_merge([
            'nombre' => 'Proyecto test historial',
            'idProceso' => (new ProcesoService())->idPorDefecto(),
            'unidadDependencia' => 'Carrera test',
            'unidadSolicitante' => 'Facultad test',
            'equipo' => [['nombre' => 'Ing. Test Historial', 'idRol' => (string) (new RolProyectoService())->idProyectista()]],
            'equipoResponsable' => '0',
        ], $cambios)];
    }
}
