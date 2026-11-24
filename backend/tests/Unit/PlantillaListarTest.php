<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Plantilla;
use backend\models\Proyecto;
use backend\services\PlantillaService;
use Codeception\Test\Unit;

/**
 * PlantillaService::listar() informa en cuantos proyectos se uso cada
 * plantilla como plantilla de origen (usadaEn), para la columna "Usada en"
 * del listado de administracion.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class PlantillaListarTest extends Unit
{
    public function testUsadaEnCuentaLosProyectosConEsaPlantillaDeOrigen(): void
    {
        $plantilla = new Plantilla(['nombre' => 'Plantilla test listar', 'activo' => true]);
        $plantilla->save(false);
        (new Proyecto(['nombre' => 'Proyecto test listar 1', 'idPlantillaOrigen' => $plantilla->id]))->save(false);
        (new Proyecto(['nombre' => 'Proyecto test listar 2', 'idPlantillaOrigen' => $plantilla->id]))->save(false);

        $otra = new Plantilla(['nombre' => 'Plantilla test listar sin uso', 'activo' => true]);
        $otra->save(false);

        $filas = (new PlantillaService())->listar();
        $filasPorId = array_combine(
            array_map(static fn (array $fila) => $fila['plantilla']->id, $filas),
            $filas
        );

        $this->assertSame(2, $filasPorId[$plantilla->id]['usadaEn']);
        $this->assertSame(0, $filasPorId[$otra->id]['usadaEn']);
    }
}
