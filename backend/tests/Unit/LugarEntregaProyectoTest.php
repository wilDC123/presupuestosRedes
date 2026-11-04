<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\HistorialService;
use backend\services\ProyectoService;
use Codeception\Test\Unit;

/**
 * Lugar de entrega del proyecto (2026-11-03): la sugerencia para facultades,
 * la herencia a la fase siguiente y el historial. Que lo copie el formulario
 * de especificacion se prueba en EspecificacionEmisionTest.
 */
class LugarEntregaProyectoTest extends Unit
{
    public function testSeSugiereLaAdministracionSoloParaUnaFacultad(): void
    {
        $this->assertSame(
            'Administración de la Facultad de Ciencias Agrarias',
            Proyecto::lugarEntregaSugerido('  Facultad de Ciencias Agrarias ')
        );
        $this->assertSame('Administración de la facultad de pedagogía', Proyecto::lugarEntregaSugerido('facultad de pedagogía'));
        $this->assertNull(Proyecto::lugarEntregaSugerido('Canal Universitario'));
        $this->assertNull(Proyecto::lugarEntregaSugerido('Facultades unidas (test)'));
        $this->assertNull(Proyecto::lugarEntregaSugerido(null));
    }

    public function testLaFaseSiguienteHeredaElLugar(): void
    {
        $anterior = new Proyecto(['nombre' => 'Proyecto test lugar', 'unidadDependencia' => 'Facultad test', 'unidadSolicitante' => 'Facultad test', 'lugarEntrega' => 'Almacén test del bloque A', 'numeroFase' => 1]);
        $anterior->save(false);
        (new Presupuesto(['idProyecto' => $anterior->id, 'estado' => Presupuesto::ESTADO_APROBADO]))->save(false);

        $this->assertSame('Almacén test del bloque A', (new ProyectoService())->nuevo($anterior->id)->lugarEntrega);
    }

    public function testCambiarElLugarQuedaEnElHistorial(): void
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test lugar 2', 'lugarEntrega' => 'Lugar test viejo']);
        $historial = new HistorialService();
        $antes = $historial->foto($proyecto);
        $proyecto->lugarEntrega = 'Lugar test nuevo';

        $this->assertSame('Lugar de entrega: Lugar test viejo → Lugar test nuevo', $historial->cambios($antes, $historial->foto($proyecto)));
    }
}
