<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\components\NumeracionEspecificacion;
use backend\models\Especificacion;
use backend\models\EspecificacionCaracteristica;
use backend\services\EspecificacionService;
use Codeception\Test\Unit;

/**
 * Numeracion del formulario de especificacion (NumeracionEspecificacion) y
 * como se guardan los subtitulos (EspecificacionService::guardarVersion()).
 */
class NumeracionEspecificacionTest extends Unit
{
    public function testLasCaracteristicasEmpiezanDespuesDeMarcaYUrl(): void
    {
        $filas = NumeracionEspecificacion::caracteristicas([
            ['nombre' => 'Tipo', 'valor' => 'Switch'],
            ['nombre' => 'Montable en rack', 'valor' => 'si'],
        ]);

        $this->assertSame(['1.3', '1.4'], array_column($filas, 'numero'));
        $this->assertSame([1, 1], array_column($filas, 'nivel'));
    }

    public function testCadaFilaVaEnSuNivelYUnaDeNivel1CierraElGrupo(): void
    {
        // Lo que fallaba con solo la casilla: "El sistema operativo debe estar
        // preinstalado" quedaba dentro de Software (1.5.2). Ahora vuelve a 1.6.
        $filas = NumeracionEspecificacion::caracteristicas([
            ['nombre' => 'Tipo', 'valor' => 'Switch'],
            ['valor' => 'Hardware', 'esSubtitulo' => true],
            ['nombre' => 'CPU', 'valor' => '800 MHz', 'nivel' => 2],
            ['nombre' => 'RAM', 'valor' => '256 MB', 'nivel' => '2'],
            ['valor' => 'Software', 'esSubtitulo' => '1'],
            ['nombre' => 'Sistema operativo', 'valor' => 'RouterOS', 'nivel' => 2],
            ['valor' => 'El sistema operativo debe estar preinstalado.', 'nivel' => 1],
        ]);

        $this->assertSame(['1.3', '1.4', '1.4.1', '1.4.2', '1.5', '1.5.1', '1.6'], array_column($filas, 'numero'));
        $this->assertSame([1, 1, 2, 2, 1, 2, 1], array_column($filas, 'nivel'));
        $this->assertSame([false, true, false, false, true, false, false], array_column($filas, 'esSubtitulo'));
    }

    public function testTercerNivelYVueltaAlSegundo(): void
    {
        // INCLUYE -> Accesorios de fábrica -> Adaptador PoE (cámara bala).
        $filas = NumeracionEspecificacion::caracteristicas([
            ['valor' => 'INCLUYE', 'esSubtitulo' => true],
            ['valor' => 'Accesorios de fábrica', 'esSubtitulo' => true, 'nivel' => 2],
            ['valor' => 'Adaptador PoE', 'nivel' => 3],
            ['valor' => 'Soporte de pared', 'nivel' => 3],
            ['valor' => 'Manual de instalación', 'nivel' => 2],
            ['nombre' => 'Garantía del fabricante', 'valor' => '3 años'],
        ]);

        $this->assertSame(['1.3', '1.3.1', '1.3.1.1', '1.3.1.2', '1.3.2', '1.4'], array_column($filas, 'numero'));
    }

    public function testUnNivelQueSeSaltaUnoSeCorrige(): void
    {
        // Primera fila siempre en 1; nunca mas de un nivel debajo de la anterior; maximo 3.
        $this->assertSame([1, 2, 3, 3, 3, 1], NumeracionEspecificacion::niveles([3, 2, 4, 3, 9, 0]));
        $this->assertSame(
            ['1.3', '1.3.1', '1.3.2'],
            array_column(NumeracionEspecificacion::caracteristicas([
                ['valor' => 'Red', 'esSubtitulo' => true],
                ['nombre' => 'Puertos', 'valor' => '8', 'nivel' => 3],
                ['nombre' => 'PoE', 'valor' => 'si', 'nivel' => 2],
            ]), 'numero')
        );
    }

    public function testNivelesPorSubtituloEsComoSeNumerabaAntes(): void
    {
        $this->assertSame([1, 1, 2, 2, 1, 2], NumeracionEspecificacion::nivelesPorSubtitulo([false, true, false, false, true, 0]));
    }

    public function testUnSubtituloPierdeElNumeroQueTraiaDelWord(): void
    {
        $filas = NumeracionEspecificacion::caracteristicas([
            ['valor' => 'II. MEDIO AMBIENTE', 'esSubtitulo' => true],
            ['nombre' => 'Humedad', 'valor' => '5 a 95%'],
        ]);

        $this->assertSame('MEDIO AMBIENTE', $filas[0]['valor']);
        $this->assertNull($filas[0]['nombre']);
    }

    public function testSinNumeralSoloQuitaUnNumeroSeguidoDeTexto(): void
    {
        $this->assertSame('MEDIO AMBIENTE', NumeracionEspecificacion::sinNumeral('II MEDIO AMBIENTE'));
        $this->assertSame('Garantías de buen funcionamiento', NumeracionEspecificacion::sinNumeral('1.Garantías de buen funcionamiento'));
        $this->assertSame('Soporte', NumeracionEspecificacion::sinNumeral('2) Soporte'));
        $this->assertSame('802.11n', NumeracionEspecificacion::sinNumeral('802.11n'));
        $this->assertSame('24 V', NumeracionEspecificacion::sinNumeral('24 V'));
        $this->assertSame('VLAN', NumeracionEspecificacion::sinNumeral('VLAN'));
        $this->assertSame('IP', NumeracionEspecificacion::sinNumeral('IP'));
    }

    public function testLaGarantiaSeParteEnUnaFilaPorLinea(): void
    {
        $this->assertSame(
            ['Garantía de 1 año.', 'Soporte técnico en Sucre.'],
            NumeracionEspecificacion::lineas("1.Garantía de 1 año.\n\n 2. Soporte técnico en Sucre. ")
        );
        $this->assertSame([], NumeracionEspecificacion::lineas(null));
    }

    public function testRomano(): void
    {
        $this->assertSame(['I', 'II', 'III', 'IV'], array_map([NumeracionEspecificacion::class, 'romano'], [1, 2, 3, 4]));
    }

    public function testPareceSubtitulo(): void
    {
        $this->assertTrue(EspecificacionCaracteristica::pareceSubtitulo(null, 'Hardware'));
        $this->assertFalse(EspecificacionCaracteristica::pareceSubtitulo('Tipo', 'Switch'));
        $this->assertFalse(EspecificacionCaracteristica::pareceSubtitulo(null, 'Incluye la configuración.'));
        $this->assertFalse(EspecificacionCaracteristica::pareceSubtitulo(null, "Capa 2\nProtocolo LLDP"));
    }

    public function testElSubtituloSeGuardaComoUnSoloTexto(): void
    {
        $ficha = new Especificacion(['titulo' => 'Switch (test numeracion)', 'activo' => true]);
        $ficha->save(false);
        $service = new EspecificacionService();

        // Escrito como "Característica": pasa al valor.
        $resultado = $service->guardarVersion($ficha, [], [
            ['nombre' => 'Hardware', 'valor' => '', 'esSubtitulo' => '1'],
            ['nombre' => 'CPU', 'valor' => '800 MHz', 'nivel' => 2],
        ]);

        $this->assertSame([], $resultado['errores']);
        $this->assertSame(
            ['1.3', '1.3.1'],
            array_column($resultado['version']->caracteristicasNumeradas(), 'numero')
        );
        $primera = $resultado['version']->caracteristicas[0];
        $this->assertTrue((bool) $primera->esSubtitulo);
        $this->assertNull($primera->nombre);
        $this->assertSame('Hardware', $primera->valor);

        // Con los dos campos, es un error.
        $error = $service->guardarVersion($ficha, [], [['nombre' => 'Hardware', 'valor' => 'ARM', 'esSubtitulo' => '1']]);
        $this->assertCount(1, $error['errores']);
    }

    public function testMarcarSubtituloEnUnaVersionEmitidaEsUnCambio(): void
    {
        $ficha = new Especificacion(['titulo' => 'Switch (test numeracion 2)', 'activo' => true]);
        $ficha->save(false);
        $service = new EspecificacionService();
        $filas = [['valor' => 'Hardware'], ['nombre' => 'CPU', 'valor' => '800 MHz']];
        $v1 = $service->guardarVersion($ficha, [], $filas)['version'];

        // Sin emisiones se corrige ahi mismo: misma version.
        $filas[0]['esSubtitulo'] = true;
        $resultado = $service->guardarVersion($ficha, [], $filas);

        $this->assertFalse($resultado['nueva']);
        $this->assertSame($v1->id, $resultado['version']->id);
        $this->assertTrue((bool) $resultado['version']->caracteristicas[0]->esSubtitulo);
    }

    public function testElNivelSeGuardaCorregidoYSePuedeCambiar(): void
    {
        $ficha = new Especificacion(['titulo' => 'Switch (test numeracion 3)', 'activo' => true]);
        $ficha->save(false);
        $service = new EspecificacionService();

        // La fila vacia no cuenta: "CPU" queda en 2 (debajo de Hardware) y no en 3.
        $filas = [
            ['valor' => 'Hardware', 'esSubtitulo' => '1', 'nivel' => '2'],
            ['nombre' => '', 'valor' => '', 'nivel' => '2'],
            ['nombre' => 'CPU', 'valor' => '800 MHz', 'nivel' => '3'],
            ['nombre' => 'Montable en rack', 'valor' => 'si', 'nivel' => '2'],
        ];
        $v1 = $service->guardarVersion($ficha, [], $filas)['version'];

        $this->assertSame([1, 2, 2], array_map(static fn ($c) => (int) $c->nivel, $v1->caracteristicas));
        $this->assertSame(['1.3', '1.3.1', '1.3.2'], array_column($v1->caracteristicasNumeradas(), 'numero'));

        // Guardar lo mismo no cambia nada; sacar una fila del grupo, si.
        $this->assertSame([], $service->guardarVersion($ficha, [], $filas)['errores']);
        $filas[3]['nivel'] = 1;

        $resultado = $service->guardarVersion($ficha, [], $filas);
        $this->assertSame(['1.3', '1.3.1', '1.4'], array_column($resultado['version']->caracteristicasNumeradas(), 'numero'));
    }
}
