<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use backend\services\PresupuestoService;
use Codeception\Test\Unit;

/**
 * Reglas del presupuesto que viven en PresupuestoService (no en los
 * controladores), para que ningun camino se las salte:
 *  - a "en revision" solo con la cabecera completa y todos los items con precio,
 *  - solo se elimina un presupuesto "en proceso".
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class PresupuestoEstadoTest extends Unit
{
    private PresupuestoService $service;

    protected function _before(): void
    {
        $this->service = new PresupuestoService();
    }

    public function testSinCabeceraNoPasaAEnRevision(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);

        $this->assertFalse($this->service->cambiarEstado($presupuesto, Presupuesto::ESTADO_EN_REVISION));
        $this->assertSame(Presupuesto::ESTADO_EN_PROCESO, Presupuesto::findOne($presupuesto->id)->estado);
    }

    public function testConItemsSinPrecioNoPasaAEnRevision(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO, true);
        $itemSubcatalogo = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        if ($itemSubcatalogo === null) {
            $this->markTestSkipped('La base local no tiene ítems activos en el subcatálogo.');
        }
        (new ProyectoItem([
            'idProyecto' => $presupuesto->idProyecto,
            'idSubcatalogo' => $itemSubcatalogo->id,
            'idCategoria' => $itemSubcatalogo->idCategoria,
            'cantidad' => 1,
            'orden' => 1,
        ]))->save(false);

        $this->assertFalse($this->service->cambiarEstado($presupuesto, Presupuesto::ESTADO_EN_REVISION));
        $this->assertSame(Presupuesto::ESTADO_EN_PROCESO, Presupuesto::findOne($presupuesto->id)->estado);
    }

    public function testConCabeceraYTodoConPrecioPasaAEnRevision(): void
    {
        // Lista vacia: no hay items sin precio.
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO, true);

        $this->assertTrue($this->service->cambiarEstado($presupuesto, Presupuesto::ESTADO_EN_REVISION));
        $this->assertSame(Presupuesto::ESTADO_EN_REVISION, Presupuesto::findOne($presupuesto->id)->estado);
    }

    public function testLasDemasTransicionesNoPidenCabecera(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_REVISION);

        $this->assertTrue($this->service->cambiarEstado($presupuesto, Presupuesto::ESTADO_OBSERVADO));
    }

    public function testBotonesDeEstadoSalenDeLasTransicionesSinEnRevision(): void
    {
        $this->assertSame([], $this->service->estadosConBoton($this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO)));
        $this->assertSame(
            [Presupuesto::ESTADO_OBSERVADO, Presupuesto::ESTADO_APROBADO, Presupuesto::ESTADO_CANCELADO],
            $this->service->estadosConBoton($this->crearPresupuesto(Presupuesto::ESTADO_EN_REVISION))
        );
        $this->assertSame([Presupuesto::ESTADO_EN_PROCESO], $this->service->estadosConBoton($this->crearPresupuesto(Presupuesto::ESTADO_OBSERVADO)));
        $this->assertSame([], $this->service->estadosConBoton($this->crearPresupuesto(Presupuesto::ESTADO_APROBADO)));
    }

    public function testSoloSeEliminaUnPresupuestoEnProceso(): void
    {
        $enRevision = $this->crearPresupuesto(Presupuesto::ESTADO_EN_REVISION);
        $aprobado = $this->crearPresupuesto(Presupuesto::ESTADO_APROBADO);
        $borrador = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);

        $this->assertFalse($this->service->eliminar($enRevision));
        $this->assertFalse($this->service->eliminar($aprobado));
        $this->assertNotNull(Presupuesto::findOne($enRevision->id));
        $this->assertNotNull(Presupuesto::findOne($aprobado->id));

        $this->assertTrue($this->service->eliminar($borrador));
        $this->assertNull(Presupuesto::findOne($borrador->id));
    }

    private function crearPresupuesto(string $estado, bool $conCabecera = false): Presupuesto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test estados', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);

        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => $estado]);
        if ($conCabecera) {
            $presupuesto->numeroPresupuesto = 'TEST-1';
            $presupuesto->oficioDtic = 'DTIC 1/26';
            $presupuesto->areaIntervencion = 'Bloque A';
            $presupuesto->fecha = '2026-10-20';
        }
        $presupuesto->save(false);

        return $presupuesto;
    }
}
