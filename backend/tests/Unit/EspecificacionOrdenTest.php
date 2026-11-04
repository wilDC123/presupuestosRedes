<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Especificacion;
use backend\models\EspecificacionVersion;
use backend\services\EspecificacionOrdenService;
use backend\services\EspecificacionService;
use Codeception\Test\Unit;

/**
 * Orden de la libreria con un plan (EspecificacionOrdenService): reconoce la
 * ficha por titulo y huella, la ordena una sola vez y no pisa una ficha que
 * cambio despues de armar el plan.
 */
class EspecificacionOrdenTest extends Unit
{
    private const TITULO = 'NVR (test orden de especificaciones)';

    /** @var array<int, array{nombre: ?string, valor: string}> */
    private array $filasHoy = [
        ['nombre' => 'Procesador', 'valor' => 'Industrial'],
        ['nombre' => null, 'valor' => 'Certificaciones'],
        ['nombre' => 'FCC', 'valor' => 'Parte 15'],
        ['nombre' => 'Instalación', 'valor' => 'Rack'],
    ];

    private function crearFicha(): Especificacion
    {
        $ficha = new Especificacion(['titulo' => self::TITULO, 'activo' => true]);
        $ficha->save(false);
        // Como lo dejo la migracion: subtitulo y todo lo de abajo en nivel 2.
        (new EspecificacionService())->guardarVersion($ficha, [], [
            $this->filasHoy[0],
            $this->filasHoy[1] + ['esSubtitulo' => true],
            $this->filasHoy[2] + ['nivel' => 2],
            $this->filasHoy[3] + ['nivel' => 2],
        ]);

        return $ficha;
    }

    /**
     * @return array<int, array{titulo: string, huella: string, filas: array<int, array{nombre: ?string, valor: string, esSubtitulo: bool, nivel: int}>}>
     */
    private function plan(): array
    {
        return [[
            'titulo' => self::TITULO,
            'huella' => EspecificacionOrdenService::huella($this->filasHoy),
            'filas' => [
                ['nombre' => 'Procesador', 'valor' => 'Industrial', 'esSubtitulo' => false, 'nivel' => 1],
                ['nombre' => null, 'valor' => 'Certificaciones', 'esSubtitulo' => true, 'nivel' => 1],
                ['nombre' => 'FCC', 'valor' => 'Parte 15', 'esSubtitulo' => false, 'nivel' => 2],
                ['nombre' => 'Instalación', 'valor' => 'Rack', 'esSubtitulo' => false, 'nivel' => 1],
            ],
        ]];
    }

    public function testOrdenaLaFichaUnaSolaVez(): void
    {
        $ficha = $this->crearFicha();
        $servicio = new EspecificacionOrdenService();

        $analisis = $servicio->analizar($this->plan());
        $this->assertSame([EspecificacionOrdenService::ESTADO_APLICAR], array_column($analisis, 'estado'));
        $this->assertSame(1, $servicio->aplicar($analisis));

        $version = $ficha->getVersionActual()->one();
        $this->assertInstanceOf(EspecificacionVersion::class, $version);
        $this->assertSame(['1.3', '1.4', '1.4.1', '1.5'], array_column($version->caracteristicasNumeradas(), 'numero'));

        // Correrlo otra vez no cambia nada.
        $otraVez = $servicio->analizar($this->plan());
        $this->assertSame([EspecificacionOrdenService::ESTADO_YA_APLICADA], array_column($otraVez, 'estado'));
        $this->assertSame(0, $servicio->aplicar($otraVez));
    }

    public function testNoPisaUnaFichaQueCambioDespuesDelPlan(): void
    {
        $ficha = $this->crearFicha();
        // Alguien corrigio un valor despues de armar el plan.
        (new EspecificacionService())->guardarVersion($ficha, [], [
            $this->filasHoy[0],
            $this->filasHoy[1] + ['esSubtitulo' => true],
            ['nombre' => 'FCC', 'valor' => 'Parte 15, Clase A', 'nivel' => 2],
            $this->filasHoy[3] + ['nivel' => 2],
        ]);

        $analisis = (new EspecificacionOrdenService())->analizar($this->plan());

        $this->assertSame([EspecificacionOrdenService::ESTADO_NO_ENCONTRADA], array_column($analisis, 'estado'));
    }

    public function testLaHuellaNoDependeDeSaltosDeLineaNiEspacios(): void
    {
        $this->assertSame(
            EspecificacionOrdenService::huella([['nombre' => 'A', 'valor' => "uno\ndos"]]),
            EspecificacionOrdenService::huella([['nombre' => ' A ', 'valor' => "uno\r\ndos "]])
        );
        $this->assertNotSame(
            EspecificacionOrdenService::huella([['nombre' => 'A', 'valor' => 'uno']]),
            EspecificacionOrdenService::huella([['nombre' => null, 'valor' => 'A: uno']])
        );
    }
}
