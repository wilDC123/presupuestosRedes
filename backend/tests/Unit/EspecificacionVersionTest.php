<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\EspecificacionVersion;
use backend\models\Proyecto;
use backend\services\EspecificacionService;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * Regla de versiones de EspecificacionService::guardarVersion(): una version
 * ya emitida no se modifica; cambiarla crea la siguiente.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class EspecificacionVersionTest extends Unit
{
    private EspecificacionService $service;

    protected function _before(): void
    {
        $this->service = new EspecificacionService();
    }

    public function testFichaSinVersionesCreaV1ConSusFilasEnOrden(): void
    {
        $ficha = $this->crearFicha();

        $resultado = $this->service->guardarVersion($ficha, ['modeloSugerido' => 'Nexxt PCGUCC6LZGR'], [
            ['nombre' => 'Categoría', 'valor' => 'Cat 6'],
            ['nombre' => '', 'valor' => ''],
            ['nombre' => 'Calibre', 'valor' => ' 23 AWG '],
            ['valor' => 'Cumple con ANSI/TIA-568-C.2'],
        ]);

        $this->assertSame([], $resultado['errores']);
        $this->assertTrue($resultado['nueva']);
        $this->assertSame(1, (int) $resultado['version']->numero);
        $this->assertSame(
            ['Categoría: Cat 6', 'Calibre: 23 AWG', 'Cumple con ANSI/TIA-568-C.2'],
            $this->textos($resultado['version'])
        );
    }

    public function testVersionSinEmisionesSeCorrigeAhiMismo(): void
    {
        $ficha = $this->crearFicha();
        $v1 = $this->service->guardarVersion($ficha, [], [['nombre' => 'Calibre', 'valor' => '24 AWG']])['version'];

        $resultado = $this->service->guardarVersion($ficha, [], [['nombre' => 'Calibre', 'valor' => '23 AWG']]);

        $this->assertFalse($resultado['nueva']);
        $this->assertSame($v1->id, $resultado['version']->id);
        $this->assertSame(1, (int) $ficha->getVersiones()->count());
        $this->assertSame(['Calibre: 23 AWG'], $this->textos($resultado['version']));
    }

    public function testVersionEmitidaNoCambiaYSeCreaLaSiguiente(): void
    {
        $ficha = $this->crearFicha();
        $v1 = $this->service->guardarVersion($ficha, ['modeloSugerido' => 'Furukawa'], [['nombre' => 'Calibre', 'valor' => '23 AWG']])['version'];
        $this->emitir($v1);

        $resultado = $this->service->guardarVersion(
            $ficha,
            ['notaCambio' => 'Se exige LSZH'],
            [['nombre' => 'Calibre', 'valor' => '23 AWG'], ['nombre' => 'Cubierta', 'valor' => 'LSZH']]
        );

        $this->assertTrue($resultado['nueva']);
        $this->assertSame(2, (int) $resultado['version']->numero);
        $this->assertSame('Se exige LSZH', $resultado['version']->notaCambio);
        // Lo que no vino en $datos se conserva de la version anterior.
        $this->assertSame('Furukawa', $resultado['version']->modeloSugerido);
        $this->assertSame(['Calibre: 23 AWG', 'Cubierta: LSZH'], $this->textos($resultado['version']));

        // La v1 emitida quedo exactamente como estaba.
        $v1->refresh();
        $this->assertSame(['Calibre: 23 AWG'], $this->textos($v1));
        $this->assertSame((int) $resultado['version']->id, (int) $ficha->getVersionActual()->one()->id);
    }

    public function testVersionEmitidaSinCambiosNoCreaOtra(): void
    {
        $ficha = $this->crearFicha();
        $v1 = $this->service->guardarVersion($ficha, ['modeloSugerido' => 'Furukawa'], [['nombre' => 'Calibre', 'valor' => '23 AWG']])['version'];
        $this->emitir($v1);

        // Como guardar el formulario despues de cambiar solo el titulo:
        // mismo contenido (con otros espacios) y una nota que no cuenta.
        $resultado = $this->service->guardarVersion(
            $ficha,
            ['modeloSugerido' => ' Furukawa ', 'notaCambio' => 'nada'],
            [['nombre' => 'Calibre ', 'valor' => '23 AWG']]
        );

        $this->assertFalse($resultado['nueva']);
        $this->assertSame($v1->id, $resultado['version']->id);
        $this->assertSame(1, (int) $ficha->getVersiones()->count());
    }

    public function testFilaConNombreSinValorEsError(): void
    {
        $ficha = $this->crearFicha();

        $resultado = $this->service->guardarVersion($ficha, [], [
            ['nombre' => 'Calibre', 'valor' => '23 AWG'],
            ['nombre' => 'Color', 'valor' => '  '],
        ]);

        $this->assertNull($resultado['version']);
        $this->assertSame(['Fila 2 ("Color"): falta el valor.'], $resultado['errores']);
        $this->assertSame(0, (int) $ficha->getVersiones()->count());
    }

    public function testFichaEmitidaNoSePuedeEliminar(): void
    {
        $ficha = $this->crearFicha();
        $v1 = $this->service->guardarVersion($ficha, [], [['nombre' => 'Calibre', 'valor' => '23 AWG']])['version'];
        $this->emitir($v1);

        $this->assertNotNull($this->service->eliminar($ficha));
        $this->assertNotNull(Especificacion::findOne($ficha->id));
    }

    public function testFichaSinUsosSeEliminaConSusVersiones(): void
    {
        $ficha = $this->crearFicha();
        $v1 = $this->service->guardarVersion($ficha, [], [['nombre' => 'Calibre', 'valor' => '23 AWG']])['version'];

        $this->assertNull($this->service->eliminar($ficha));
        $this->assertNull(EspecificacionVersion::findOne($v1->id));
    }

    public function testCodigoNetYVencimiento(): void
    {
        $emision = new EspecificacionEmision([
            'numeroNet' => 13,
            'anio' => 23,
            'fechaEmision' => '2026-10-01',
            'vigenciaDias' => 30,
            'estado' => EspecificacionEmision::ESTADO_VIGENTE,
        ]);

        $this->assertSame('NET:013/23', $emision->codigoNet());
        $this->assertSame('2026-10-31', $emision->fechaVencimiento()->format('Y-m-d'));
        $this->assertFalse($emision->estaVencida(new DateTimeImmutable('2026-10-31')));
        $this->assertTrue($emision->estaVencida(new DateTimeImmutable('2026-11-01')));

        // Una reemplazada ya no se usa: no se marca como vencida.
        $emision->estado = EspecificacionEmision::ESTADO_REEMPLAZADA;
        $this->assertFalse($emision->estaVencida(new DateTimeImmutable('2026-12-01')));
    }

    private function crearFicha(): Especificacion
    {
        $ficha = new Especificacion(['titulo' => 'Cable de red Categoría 6 (test)', 'activo' => true]);
        $ficha->save(false);

        return $ficha;
    }

    /**
     * Emision minima, sin item (para no chocar con las de la base local):
     * basta para que la version quede bloqueada.
     */
    private function emitir(EspecificacionVersion $version): void
    {
        $proyecto = Proyecto::find()->one();
        if ($proyecto === null) {
            $this->markTestSkipped('La base local no tiene ningún proyecto.');
        }

        $numero = (int) EspecificacionEmision::find()->max('numeroNet') + 1;

        (new EspecificacionEmision([
            'numeroNet' => $numero,
            'anio' => 26,
            'idVersion' => $version->id,
            'idProyecto' => $proyecto->id,
            'titulo' => 'test',
            'cantidad' => 1,
            'fechaEmision' => '2026-10-17',
            'vigenciaDias' => 30,
        ]))->save(false);
    }

    /**
     * @return string[]
     */
    private function textos(EspecificacionVersion $version): array
    {
        return array_map(static fn ($c) => $c->texto(), $version->getCaracteristicas()->all());
    }
}
