<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Especificacion;
use backend\models\SubcatalogoItem;
use backend\services\EspecificacionService;
use Codeception\Test\Unit;

/**
 * Buscador de la ventana "Ver detalle" de la lista de items
 * (EspecificacionService::buscar()) y "Recordar como sugerida para este ítem"
 * (EspecificacionService::recordarItemDeReferencia()).
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar. Los
 * textos llevan una marca rara ("Zqx") para no coincidir con fichas reales.
 */
class EspecificacionBusquedaTest extends Unit
{
    private EspecificacionService $service;

    protected function _before(): void
    {
        $this->service = new EspecificacionService();
    }

    public function testTodasLasPalabrasEnCualquierOrdenSinImportarTildes(): void
    {
        $ficha = $this->crearFicha('Cámara Zqx domo', 'Dahua ZQX-123');

        $this->assertContains($ficha->id, $this->ids('zqx camara'));
        $this->assertContains($ficha->id, $this->ids('DOMO cámara zqx'));
        // Una palabra que no esta deja la ficha fuera.
        $this->assertNotContains($ficha->id, $this->ids('zqx camara bala'));
    }

    public function testBuscaEnModeloYCaracteristicasDeLaVersionActual(): void
    {
        $ficha = $this->crearFicha('Switch Zqx', 'Cisco ZQX-24P', [['nombre' => 'Puertos', 'valor' => '24 puertos PoE+ zqxpoe']]);

        $this->assertContains($ficha->id, $this->ids('zqx-24p'));
        $this->assertContains($ficha->id, $this->ids('zqxpoe'));

        // Sin esa caracteristica en la version actual, ya no se encuentra por ella.
        $this->service->guardarVersion($ficha, [], [['nombre' => 'Puertos', 'valor' => '24 puertos']]);
        $this->assertNotContains($ficha->id, $this->ids('zqxpoe'));
    }

    public function testNoDevuelveFichasInactivas(): void
    {
        $ficha = $this->crearFicha('Router Zqx inactivo');
        $ficha->updateAttributes(['activo' => false]);

        $this->assertNotContains($ficha->id, $this->ids('zqx inactivo'));
    }

    public function testElTituloPesaMasQueLasCaracteristicas(): void
    {
        $porCaracteristica = $this->crearFicha('Antena Zqx', null, [['nombre' => 'Nota', 'valor' => 'zqxtitulo']]);
        $porTitulo = $this->crearFicha('Zqxtitulo de 24 puertos');

        $this->assertSame([$porTitulo->id, $porCaracteristica->id], $this->ids('zqxtitulo'));
    }

    public function testRecordarPoneElItemSoloSiLaFichaNoTeniaUno(): void
    {
        [$item1, $item2] = SubcatalogoItem::find()->orderBy(['id' => SORT_ASC])->limit(2)->all();
        $ficha = $this->crearFicha('Ficha Zqx sin referencia');

        $this->service->recordarItemDeReferencia($ficha->id, $item1->id);
        $ficha->refresh();
        $this->assertSame($item1->id, (int) $ficha->idSubcatalogo);

        // Ya tiene uno: no se pisa.
        $this->service->recordarItemDeReferencia($ficha->id, $item2->id);
        $ficha->refresh();
        $this->assertSame($item1->id, (int) $ficha->idSubcatalogo);
    }

    /**
     * @return int[]
     */
    private function ids(string $texto): array
    {
        return array_column($this->service->buscar($texto), 'id');
    }

    /**
     * @param array<int, array{nombre: string, valor: string}> $caracteristicas
     */
    private function crearFicha(string $titulo, ?string $modelo = null, array $caracteristicas = [['nombre' => 'Uso', 'valor' => 'Prueba']]): Especificacion
    {
        $ficha = new Especificacion(['titulo' => $titulo, 'activo' => true]);
        $ficha->save(false);
        $this->service->guardarVersion($ficha, ['modeloSugerido' => $modelo], $caracteristicas);

        return $ficha;
    }
}
