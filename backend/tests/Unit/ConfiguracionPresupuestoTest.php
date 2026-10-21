<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\ConfiguracionPresupuesto;
use Codeception\Test\Unit;

/**
 * Nota, firmas y banda de pie del PDF del presupuesto
 * (redes.ConfiguracionPresupuesto): solo sale lo que tiene datos, el cargo
 * se guarda en mayusculas y una firma sin nombre no se acepta.
 */
class ConfiguracionPresupuestoTest extends Unit
{
    public function testSoloSalenLasFirmasYColumnasConDatos(): void
    {
        $configuracion = new ConfiguracionPresupuesto([
            'firma1Nombre' => null,
            'firma2Prefijo' => 'V°B°',
            'firma2Nombre' => 'Ing. Prueba',
            'firma2Cargo' => 'CARGO',
            'pieIzquierdaTitulo' => null,
            'pieIzquierdaLineas' => null,
            'pieDerechaTitulo' => 'Redes',
            'pieDerechaLineas' => "Interno: 5002\n\n  Correo: x@usfx.bo  ",
        ]);

        $this->assertSame([['prefijo' => 'V°B°', 'nombre' => 'Ing. Prueba', 'cargo' => 'CARGO']], $configuracion->firmas());
        $this->assertSame([['titulo' => 'Redes', 'lineas' => ['Interno: 5002', 'Correo: x@usfx.bo']]], $configuracion->columnasPie());
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
