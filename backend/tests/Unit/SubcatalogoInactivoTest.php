<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Categoria;
use backend\models\Especificacion;
use backend\models\SubcatalogoItem;
use backend\services\EspecificacionService;
use backend\services\SubcatalogoItemService;
use Codeception\Test\Unit;

/**
 * Un item del subcatalogo INACTIVO ya no se elige (desplegables de la
 * especificacion y de la cotizacion, ni como item de referencia de una
 * ficha), pero quien ya lo tenia guardado lo conserva al editar.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class SubcatalogoInactivoTest extends Unit
{
    public function testLasOpcionesSoloTraenActivosMasLosQueSeConservan(): void
    {
        $activo = $this->crearItem('ITEM ACTIVO TEST', true);
        $inactivo = $this->crearItem('ITEM INACTIVO TEST', false);
        $service = new SubcatalogoItemService();

        $this->assertArrayHasKey($activo->id, $service->opciones());
        $this->assertArrayNotHasKey($inactivo->id, $service->opciones());
        $this->assertArrayHasKey($inactivo->id, $service->opciones([$inactivo->id, null, '']));
    }

    public function testUnaFichaNoTomaUnItemInactivoPeroConservaElQueYaTenia(): void
    {
        $inactivo = $this->crearItem('ITEM INACTIVO TEST', false);
        $ficha = new Especificacion(['titulo' => 'Ficha test', 'idCategoria' => null, 'activo' => true]);
        $ficha->save(false);

        $ficha->idSubcatalogo = $inactivo->id;
        $ficha->validate(['idSubcatalogo']);
        $this->assertTrue($ficha->hasErrors('idSubcatalogo'), 'no se elige un item inactivo');

        // Una ficha que ya lo tenia guardado (se desactivo despues) se sigue editando.
        $ficha->save(false);
        $ficha = Especificacion::findOne($ficha->id);
        $ficha->idSubcatalogo = (string) $inactivo->id;
        $this->assertTrue($ficha->validate(['idSubcatalogo']), implode(' ', $ficha->getFirstErrors()));
    }

    public function testRecordarComoSugeridaIgnoraUnItemInactivo(): void
    {
        $inactivo = $this->crearItem('ITEM INACTIVO TEST', false);
        $activo = $this->crearItem('ITEM ACTIVO TEST', true);
        $ficha = new Especificacion(['titulo' => 'Ficha test', 'activo' => true]);
        $ficha->save(false);
        $service = new EspecificacionService();

        $service->recordarItemDeReferencia($ficha->id, $inactivo->id);
        $this->assertNull(Especificacion::findOne($ficha->id)->idSubcatalogo);

        $service->recordarItemDeReferencia($ficha->id, $activo->id);
        $this->assertSame($activo->id, (int) Especificacion::findOne($ficha->id)->idSubcatalogo);
    }

    private function crearItem(string $descripcion, bool $activo): SubcatalogoItem
    {
        $item = new SubcatalogoItem([
            'descripcion' => $descripcion,
            'activo' => $activo,
            'idCategoria' => (int) Categoria::find()->select('id')->scalar(),
        ]);
        $item->save(false);

        return $item;
    }
}
