<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\SubcatalogoItem;
use Codeception\Test\Unit;

/**
 * El nombre DTIC (SubcatalogoItem.descripcion) se guarda en mayusculas.
 */
class SubcatalogoItemTest extends Unit
{
    public function testElNombreDticQuedaEnMayusculasConTildes(): void
    {
        $item = new SubcatalogoItem(['descripcion' => '  Abrazadera metálica de 1"  ']);
        $item->validate(['descripcion']);

        $this->assertSame('ABRAZADERA METÁLICA DE 1"', $item->descripcion);
    }

    public function testUnNombreSoloConEspaciosEsObligatorio(): void
    {
        $item = new SubcatalogoItem(['descripcion' => '   ']);

        $this->assertFalse($item->validate(['descripcion']));
        $this->assertTrue($item->hasErrors('descripcion'));
    }
}
