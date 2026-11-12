<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\components\Busqueda;
use backend\components\Monto;
use backend\models\CotizacionDetalle;
use backend\models\PresupuestoDetalle;
use backend\models\ProyectoItem;
use Codeception\Test\Unit;

/**
 * Cantidades y precios que caben en decimal(10,2) (backend\components\Monto)
 * y el limite de palabras de los buscadores (backend\components\Busqueda):
 * hallazgos 3, 12 y 13 de la auditoria del 2026-11-11. Antes, "1e5", un
 * precio enorme o un texto de busqueda muy largo llegaban a la base y el
 * usuario veia un error del servidor.
 */
class MontoTest extends Unit
{
    public function testFormatoDelMonto(): void
    {
        foreach (['0', '7', '1250', '1250.5', '1250.50', '99999999.99'] as $valido) {
            $this->assertTrue(Monto::valido($valido), $valido);
        }
        foreach (['', '-1', '1e5', '1,5', '12.345', ' 7', '100000000', 'abc'] as $invalido) {
            $this->assertFalse(Monto::valido($invalido), $invalido);
        }
    }

    public function testTotalDeLaLinea(): void
    {
        $this->assertTrue(Monto::totalCabe('1000', '99999.99'));
        $this->assertFalse(Monto::totalCabe('1000', '100000'));
        // Lo que no es un numero lo avisa la regla de cada campo, no esta.
        $this->assertTrue(Monto::totalCabe('abc', '10'));
    }

    public function testLineaDeCotizacion(): void
    {
        $linea = new CotizacionDetalle(['idSubcatalogo' => 1, 'cantidad' => '2', 'precioUnitario' => '1e5']);
        $this->assertFalse($linea->validate(['cantidad', 'precioUnitario']));
        $this->assertArrayHasKey('precioUnitario', $linea->errors);

        $linea = new CotizacionDetalle(['idSubcatalogo' => 1, 'cantidad' => '1000', 'precioUnitario' => '100000']);
        $this->assertFalse($linea->validate(['precioUnitario']));
        $this->assertStringContainsString('total de la línea', $linea->getFirstError('precioUnitario'));

        $linea = new CotizacionDetalle(['idSubcatalogo' => 1, 'cantidad' => '24', 'precioUnitario' => '1250.50']);
        $this->assertTrue($linea->validate(['cantidad', 'precioUnitario']), json_encode($linea->errors));
    }

    public function testLineaDePresupuestoYCantidadDeLaLista(): void
    {
        $linea = new PresupuestoDetalle(['cantidad' => '1', 'precioUnitario' => '1000000000']);
        $this->assertFalse($linea->validate(['precioUnitario']));

        $item = new ProyectoItem(['cantidad' => '12.345']);
        $this->assertFalse($item->validate(['cantidad']));
        $item->cantidad = '12.34';
        $this->assertTrue($item->validate(['cantidad']));
    }

    public function testBusquedaUsaComoMuchoDiezPalabras(): void
    {
        $this->assertSame(['cable', 'utp'], Busqueda::palabras('  cable   utp '));
        $this->assertSame([], Busqueda::palabras('   '));
        $this->assertCount(Busqueda::MAX_PALABRAS, Busqueda::palabras(str_repeat('palabra ', 400)));
    }
}
