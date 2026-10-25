<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Plantilla;
use backend\models\Proyecto;
use backend\models\TipoProyecto;
use backend\services\TipoProyectoService;
use Codeception\Test\Unit;

/**
 * Reglas de redes.TipoProyecto: un tipo en uso no se elimina (se
 * desactiva), uno inactivo no se puede elegir, pero quien ya lo tenia lo
 * conserva.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class TipoProyectoTest extends Unit
{
    private TipoProyectoService $service;

    protected function _before(): void
    {
        $this->service = new TipoProyectoService();
    }

    public function testNoAdmiteNombreRepetido(): void
    {
        $this->crearTipo('Tipo test repetido');

        $otro = new TipoProyecto();
        $this->assertFalse($this->service->crear($otro, ['TipoProyecto' => ['nombre' => '  Tipo test repetido ']]));
        $this->assertTrue($otro->hasErrors('nombre'));
    }

    public function testUnTipoEnUsoNoSeEliminaYUnoLibreSi(): void
    {
        $enUso = $this->crearTipo('Tipo test en uso');
        $libre = $this->crearTipo('Tipo test libre');
        $this->crearProyecto($enUso->id);

        $this->assertNotNull($this->service->eliminar($enUso));
        $this->assertNotNull(TipoProyecto::findOne($enUso->id));

        $this->assertNull($this->service->eliminar($libre));
        $this->assertNull(TipoProyecto::findOne($libre->id));
    }

    public function testUnTipoInactivoNoSeEligeEnUnProyectoNuevo(): void
    {
        $inactivo = $this->crearTipo('Tipo test inactivo', false);

        $proyecto = new Proyecto(['nombre' => 'Proyecto test tipo', 'idTipoProyecto' => $inactivo->id]);

        $this->assertFalse($proyecto->validate());
        $this->assertTrue($proyecto->hasErrors('idTipoProyecto'));
        $this->assertArrayNotHasKey($inactivo->id, $this->service->opciones());
    }

    public function testQuienYaTeniaElTipoLoConservaAlDesactivarse(): void
    {
        $tipo = $this->crearTipo('Tipo test desactivado despues');
        $proyecto = $this->crearProyecto($tipo->id);
        $plantilla = new Plantilla(['nombre' => 'Plantilla test tipo', 'idTipoProyecto' => $tipo->id]);
        $this->assertTrue($plantilla->save());

        $this->assertTrue($this->service->actualizar($tipo, ['TipoProyecto' => ['activo' => '0']]));

        // El formulario manda el id como texto: no cuenta como cambio.
        $proyecto->idTipoProyecto = (string) $tipo->id;
        $this->assertTrue($proyecto->validate(), json_encode($proyecto->errors));
        $this->assertTrue($plantilla->validate(), json_encode($plantilla->errors));
        $this->assertSame($tipo->nombre, $this->service->opciones($tipo->id)[$tipo->id] ?? null);

        $conteo = array_values(array_filter($this->service->listarConConteo(), static fn ($fila) => $fila['tipo']->id === $tipo->id))[0];
        $this->assertSame(1, $conteo['proyectos']);
        $this->assertSame(1, $conteo['plantillas']);
    }

    private function crearTipo(string $nombre, bool $activo = true): TipoProyecto
    {
        $tipo = new TipoProyecto(['nombre' => $nombre, 'activo' => $activo]);
        $this->assertTrue($tipo->save(), json_encode($tipo->errors));

        return $tipo;
    }

    private function crearProyecto(int $idTipoProyecto): Proyecto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test tipo', 'idTipoProyecto' => $idTipoProyecto, 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);
        $proyecto->refresh();

        return $proyecto;
    }
}
