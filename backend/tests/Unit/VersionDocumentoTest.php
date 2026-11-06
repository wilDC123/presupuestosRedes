<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\components\VersionDocumento;
use backend\models\EspecificacionEmision;
use backend\models\ListaMaterialesAprobacion;
use backend\models\Presupuesto;
use Codeception\Test\Unit;

/**
 * Revision del Ing. de DTIC del 2026-11-05, punto 8: la version se escribe
 * 1.0, y un cambio menor da la 1.1 (antes "01", "02").
 */
class VersionDocumentoTest extends Unit
{
    public function testLaPrimeraEsUnoPuntoCeroYCadaCambioMenorSubeElDecimal(): void
    {
        $this->assertSame(['1.0', '1.1', '1.2', '1.10'], array_map([VersionDocumento::class, 'texto'], [1, 2, 3, 11]));
        // Sin version guardada (0 o negativa) es la primera.
        $this->assertSame('1.0', VersionDocumento::texto(0));
    }

    public function testElBuscadorEntiendeLaNuevaYLaDeAntes(): void
    {
        $this->assertSame([1, 2, 11, 2, 2], array_map([VersionDocumento::class, 'numeroDe'], ['1.0', '1.1', '1.10', '02', '2']));
        $this->assertNull(VersionDocumento::numeroDe('2.0'));
        $this->assertNull(VersionDocumento::numeroDe('0'));
        $this->assertNull(VersionDocumento::numeroDe('abc'));
    }

    public function testTodosLosDocumentosLaEscribenIgual(): void
    {
        $this->assertSame('1.1', (new EspecificacionEmision(['versionDocumento' => 2]))->versionTexto());
        $this->assertSame('1.0', (new EspecificacionEmision())->versionTexto());
        $this->assertSame('1.2', ListaMaterialesAprobacion::versionTexto(3));
        $this->assertSame('1.0', Presupuesto::VERSION_DOCUMENTO);
    }
}
