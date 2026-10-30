<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Proceso;
use backend\models\Proyecto;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use Codeception\Test\Unit;

/**
 * Reglas de redes.Proceso (tabla 4 del lineamiento): el codigo son dos
 * letras mayusculas y unico; uno en uso no se elimina ni cambia de codigo
 * (se desactiva); el proyecto lo exige y uno nuevo trae PI.
 *
 * Cada test corre en una transaccion que se deshace al terminar.
 */
class ProcesoTest extends Unit
{
    private ProcesoService $service;

    protected function _before(): void
    {
        $this->service = new ProcesoService();
    }

    public function testElCodigoSonDosLetrasMayusculasYUnico(): void
    {
        $proceso = new Proceso();
        $this->assertTrue($this->service->crear($proceso, ['Proceso' => ['codigo' => ' zx ', 'nombre' => 'Proceso test']]), json_encode($proceso->errors));
        $this->assertSame('ZX', $proceso->codigo);

        foreach (['ZX', 'Z1', 'ZXY'] as $codigo) {
            $otro = new Proceso();
            $this->assertFalse($this->service->crear($otro, ['Proceso' => ['codigo' => $codigo, 'nombre' => 'Proceso test ' . $codigo]]), $codigo);
            $this->assertTrue($otro->hasErrors('codigo'), $codigo);
        }
    }

    public function testUnProcesoEnUsoNoSeEliminaNiCambiaDeCodigo(): void
    {
        $proceso = $this->crearProceso('ZY');
        $this->crearProyecto($proceso->id);

        $this->assertNotNull($this->service->eliminar($proceso));
        $this->assertNotNull(Proceso::findOne($proceso->id));

        $this->assertFalse($this->service->actualizar($proceso, ['Proceso' => ['codigo' => 'ZW']]));
        $this->assertTrue($proceso->hasErrors('codigo'));

        // El nombre y el estado si se cambian.
        $proceso = Proceso::findOne($proceso->id);
        $this->assertTrue($this->service->actualizar($proceso, ['Proceso' => ['nombre' => 'Proceso test renombrado', 'activo' => '0']]), json_encode($proceso->errors));

        $libre = $this->crearProceso('ZV');
        $this->assertNull($this->service->eliminar($libre));
        $this->assertNull(Proceso::findOne($libre->id));
    }

    public function testElProyectoExigeProcesoYQuienYaLoTeniaLoConserva(): void
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test proceso', 'unidadDependencia' => 'Carrera test', 'unidadSolicitante' => 'Facultad test']);
        $this->assertFalse($proyecto->validate());
        $this->assertTrue($proyecto->hasErrors('idProceso'));

        $inactivo = $this->crearProceso('ZU', false);
        $proyecto->idProceso = $inactivo->id;
        $this->assertFalse($proyecto->validate());
        $this->assertTrue($proyecto->hasErrors('idProceso'));

        // Uno que se desactiva despues: el proyecto que lo tenia lo conserva.
        $proceso = $this->crearProceso('ZT');
        $proyecto = $this->crearProyecto($proceso->id);
        $this->assertTrue($this->service->actualizar($proceso, ['Proceso' => ['activo' => '0']]));

        $proyecto->idProceso = (string) $proceso->id;
        $this->assertTrue($proyecto->validate(), json_encode($proyecto->errors));
        $this->assertArrayHasKey($proceso->id, $this->service->opciones($proceso->id));
        $this->assertArrayNotHasKey($proceso->id, $this->service->opciones());
    }

    public function testUnProyectoNuevoTraePi(): void
    {
        $proyecto = (new ProyectoService())->nuevo();

        $this->assertNotNull($proyecto->idProceso);
        $this->assertSame(ProcesoService::CODIGO_POR_DEFECTO, Proceso::findOne($proyecto->idProceso)->codigo);
    }

    private function crearProceso(string $codigo, bool $activo = true): Proceso
    {
        $proceso = new Proceso(['codigo' => $codigo, 'nombre' => 'Proceso test ' . $codigo, 'activo' => $activo]);
        $this->assertTrue($proceso->save(), json_encode($proceso->errors));

        return $proceso;
    }

    private function crearProyecto(int $idProceso): Proyecto
    {
        $proyecto = new Proyecto([
            'nombre' => 'Proyecto test proceso',
            'idProceso' => $idProceso,
            'unidadDependencia' => 'Carrera test',
            'unidadSolicitante' => 'Facultad test',
            'estadoLista' => Proyecto::LISTA_EDICION,
        ]);
        $proyecto->save(false);

        return $proyecto;
    }
}
