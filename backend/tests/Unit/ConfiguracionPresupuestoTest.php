<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\ConfiguracionPresupuesto;
use Codeception\Test\Unit;

/**
 * Nota y firmas del PDF del presupuesto (redes.ConfiguracionPresupuesto):
 * una firma sin nombre queda en blanco, el cargo se guarda en mayusculas y
 * una firma con cargo pero sin nombre no se acepta.
 */
class ConfiguracionPresupuestoTest extends Unit
{
    public function testUnaFirmaSinNombreQuedaEnBlanco(): void
    {
        $configuracion = new ConfiguracionPresupuesto([
            'firma1Nombre' => null,
            'firma2Prefijo' => 'V°B°',
            'firma2Nombre' => 'Ing. Prueba',
            'firma2Cargo' => 'CARGO',
        ]);

        $this->assertNull($configuracion->firma(1));
        $this->assertSame(['prefijo' => 'V°B°', 'nombre' => 'Ing. Prueba', 'cargo' => 'CARGO'], $configuracion->firma(2));
    }

    public function testElCargoQuedaEnMayusculasYUnaFirmaSinNombreNoSeAcepta(): void
    {
        $configuracion = new ConfiguracionPresupuesto([
            'firma1Nombre' => 'Lic. Prueba',
            'firma1Cargo' => ' jefa de área ',
            'firma2Prefijo' => 'V°B°',
            'firma2Nombre' => '',
        ]);

        $this->assertFalse($configuracion->validate());
        $this->assertSame('JEFA DE ÁREA', $configuracion->firma1Cargo);
        $this->assertTrue($configuracion->hasErrors('firma2Nombre'));
        $this->assertFalse($configuracion->hasErrors('firma1Nombre'));
    }
}
