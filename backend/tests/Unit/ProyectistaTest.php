<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Proyectista;
use backend\models\Proyecto;
use backend\models\ProyectoProyectista;
use backend\services\ProyectistaService;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use Codeception\Test\Unit;

/**
 * Reglas de redes.Proyectista (2026-10-28, antes en el JSON): solo los
 * activos se eligen, uno asignado a proyectos no se elimina (se desactiva) y
 * el proyecto que ya lo tenia lo conserva.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class ProyectistaTest extends Unit
{
    private ProyectistaService $service;

    protected function _before(): void
    {
        $this->service = new ProyectistaService();
    }

    public function testNoAdmiteNombreRepetido(): void
    {
        $this->assertTrue($this->service->crear(new Proyectista(), ['Proyectista' => ['nombre' => 'Ing. Test Repetido']]));

        $otro = new Proyectista();
        $this->assertFalse($this->service->crear($otro, ['Proyectista' => ['nombre' => '  Ing. Test Repetido ']]));
        $this->assertTrue($otro->hasErrors('nombre'));
    }

    public function testSoloLosActivosSeEligenPeroQuienYaLoTeniaLoConserva(): void
    {
        $proyectista = new Proyectista(['nombre' => 'Ing. Test Asignado']);
        $this->assertTrue($proyectista->save());
        $proyectos = new ProyectoService();

        $proyecto = new Proyecto();
        $datos = ['Proyecto' => ['nombre' => 'Proyecto test proyectista', 'idProceso' => (new ProcesoService())->idPorDefecto(), 'unidadDependencia' => 'Carrera test', 'unidadSolicitante' => 'Facultad test', 'nombresProyectista' => ['Ing. Test Asignado']]];
        $this->assertTrue($proyectos->crear($proyecto, $datos), json_encode($proyecto->errors));

        // Se desactiva: ya no se ofrece, no se elimina, pero el proyecto lo conserva al editarse.
        $this->assertTrue($this->service->actualizar($proyectista, ['Proyectista' => ['activo' => '0']]));
        $this->assertNotContains('Ing. Test Asignado', $this->service->nombresActivos());
        $this->assertNotNull($this->service->eliminar($proyectista));
        $this->assertTrue($proyectos->actualizar(Proyecto::findOne($proyecto->id), $datos));
        $this->assertSame(['Ing. Test Asignado'], ProyectoProyectista::find()->select('nombre')->andWhere(['idProyecto' => $proyecto->id])->column());

        // Un proyecto nuevo ya no puede elegirlo.
        $nuevo = new Proyecto();
        $this->assertFalse($proyectos->crear($nuevo, $datos));
        $this->assertTrue($nuevo->hasErrors('nombresProyectista'));

        $fila = array_values(array_filter($this->service->listarConConteo(), static fn ($f) => $f['proyectista']->id === $proyectista->id))[0];
        $this->assertSame(1, $fila['proyectos']);
    }

    public function testUnoSinProyectosSeElimina(): void
    {
        $proyectista = new Proyectista(['nombre' => 'Ing. Test Sin Proyectos']);
        $this->assertTrue($proyectista->save());

        $this->assertNull($this->service->eliminar($proyectista));
        $this->assertNull(Proyectista::findOne($proyectista->id));
    }
}
