<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\ProyectoItem;
use backend\services\EspecificacionService;
use Codeception\Test\Unit;

/**
 * Orden del selector "Especificación técnica" de cada fila de la lista
 * (EspecificacionService::opcionesPorItem()): sugeridas, categoria del item,
 * resto. Usa un selector armado a mano: no toca la base.
 */
class EspecificacionSelectorTest extends Unit
{
    private const SELECTOR = [
        'grupos' => [
            1 => ['nombre' => 'Cableado', 'opciones' => [10 => 'Cable cat 6', 11 => 'Cable cat 5e']],
            2 => ['nombre' => 'Dispositivos', 'opciones' => [20 => 'Switch PoE', 21 => 'Router']],
            3 => ['nombre' => 'Otros', 'opciones' => [30 => 'Velcro']],
        ],
        'urls' => [],
        'subcatalogos' => [20 => 65, 30 => 65],
        'sugeridasPorItem' => [65 => [20, 30]],
    ];

    public function testSugeridasPrimeroLuegoLaCategoriaDelItemYSinRepetir(): void
    {
        $mapa = (new EspecificacionService())->opcionesPorItem(self::SELECTOR, [
            new ProyectoItem(['idCategoria' => 2, 'idSubcatalogo' => 65]),
        ]);
        $fila = $mapa['2|65'];

        $this->assertSame(
            [EspecificacionService::GRUPO_SUGERIDAS, 'Dispositivos', 'Cableado'],
            array_keys($fila['opciones'])
        );
        $this->assertSame([20 => 'Switch PoE', 30 => 'Velcro'], $fila['opciones'][EspecificacionService::GRUPO_SUGERIDAS]);
        // Se movieron: ya no estan en su categoria, y "Otros" quedo vacio y no sale.
        $this->assertSame([21 => 'Router'], $fila['opciones']['Dispositivos']);
        $this->assertArrayNotHasKey('Otros', $fila['opciones']);
        $this->assertSame(['data-sugeridas' => 1], $fila['grupos'][EspecificacionService::GRUPO_SUGERIDAS]);
    }

    public function testItemSinSugeridasYFilaModelo(): void
    {
        $mapa = (new EspecificacionService())->opcionesPorItem(self::SELECTOR, [
            new ProyectoItem(['idCategoria' => 3, 'idSubcatalogo' => 99]),
        ]);

        $this->assertSame(['Otros', 'Cableado', 'Dispositivos'], array_keys($mapa['3|99']['opciones']));
        // Fila modelo (filas nuevas): orden de las categorias, sin sugeridas.
        $this->assertSame(['Cableado', 'Dispositivos', 'Otros'], array_keys($mapa['']['opciones']));
    }
}
