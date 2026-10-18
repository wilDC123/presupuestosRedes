<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Categoria;
use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\Proyecto;
use backend\services\EspecificacionService;
use Codeception\Test\Unit;

/**
 * EspecificacionService::guardar() (el formulario completo: ficha + version
 * + caracteristicas), formulario() y la busqueda de listar().
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class EspecificacionFormularioTest extends Unit
{
    private EspecificacionService $service;
    private int $idCategoria;

    protected function _before(): void
    {
        $this->service = new EspecificacionService();

        $categoria = Categoria::find()->one();
        if ($categoria === null) {
            $this->markTestSkipped('La base local no tiene ninguna categoría.');
        }
        $this->idCategoria = (int) $categoria->id;
    }

    public function testCrearGuardaFichaVersionYFilas(): void
    {
        $ficha = new Especificacion();

        $resultado = $this->service->guardar($ficha, $this->post('Cable test', [
            ['nombre' => 'Calibre', 'valor' => '23 AWG'],
            ['nombre' => '', 'valor' => ''],
            ['nombre' => 'Cubierta', 'valor' => 'LSZH'],
        ], ['modeloSugerido' => 'Furukawa']), null);

        $this->assertTrue($resultado['guardado']);
        $this->assertFalse($ficha->isNewRecord);
        $version = $ficha->getVersionActual()->one();
        $this->assertSame(1, (int) $version->numero);
        $this->assertSame('Furukawa', $version->modeloSugerido);
        $this->assertSame(2, (int) $version->getCaracteristicas()->count());
    }

    public function testCrearConFilaInvalidaNoGuardaNadaYConservaLoEscrito(): void
    {
        $ficha = new Especificacion();

        $resultado = $this->service->guardar($ficha, $this->post('Cable test con error', [
            ['nombre' => 'Calibre', 'valor' => '23 AWG'],
            ['nombre' => 'Color', 'valor' => ''],
        ], ['modeloSugerido' => 'Furukawa']), null);

        $this->assertFalse($resultado['guardado']);
        $this->assertSame(['Fila 2 ("Color"): falta el valor.'], $resultado['errores']);
        // La ficha vuelve a ser "nueva": el formulario sigue siendo el de crear.
        $this->assertTrue($ficha->isNewRecord);
        $this->assertNull(Especificacion::findOne(['titulo' => 'Cable test con error']));
        // Lo que escribio el usuario vuelve al formulario.
        $this->assertSame('Furukawa', $resultado['version']->modeloSugerido);
        $this->assertSame('23 AWG', $resultado['filas'][0]['valor']);
    }

    public function testFichaSinTituloNoSeGuarda(): void
    {
        $ficha = new Especificacion();

        $resultado = $this->service->guardar($ficha, $this->post('  ', [['nombre' => 'Calibre', 'valor' => '23 AWG']]), null);

        $this->assertFalse($resultado['guardado']);
        $this->assertTrue($ficha->hasErrors('titulo'));
    }

    public function testFormularioOfreceNotaVaciaSoloSiLaVersionEstaEmitida(): void
    {
        $ficha = new Especificacion();
        $this->service->guardar($ficha, $this->post('Cable test nota', [['nombre' => 'Calibre', 'valor' => '23 AWG']], ['notaCambio' => 'primera']), null);

        // Sin emisiones: se corrige la misma version y su nota se conserva.
        $this->assertSame('primera', $this->service->formulario($ficha)['version']->notaCambio);

        $this->emitir((int) $ficha->getVersionActual()->one()->id);

        // Emitida: guardar creara la v2, la nota es la de ese cambio.
        $formulario = $this->service->formulario($ficha);
        $this->assertNull($formulario['version']->notaCambio);
        $this->assertSame(1, $formulario['emisiones']);
    }

    public function testBuscarEncuentraPorCaracteristicaSoloEnLaVersionActual(): void
    {
        $ficha = new Especificacion();
        $this->service->guardar($ficha, $this->post('Cable test busqueda', [['nombre' => 'Cubierta', 'valor' => 'PVC-XQZ']]), null);
        $this->emitir((int) $ficha->getVersionActual()->one()->id);

        $this->assertSame([$ficha->id], $this->idsEncontrados('PVC-XQZ'));

        // v2 sin esa caracteristica: la v1 la sigue teniendo, pero la busqueda
        // mira solo la version actual.
        $this->service->guardar($ficha, $this->post('Cable test busqueda', [['nombre' => 'Cubierta', 'valor' => 'LSZH-XQZ']]), null);

        $this->assertSame([], $this->idsEncontrados('PVC-XQZ'));
        $this->assertSame([$ficha->id], $this->idsEncontrados('LSZH-XQZ'));
    }

    /**
     * Lo que envia el formulario.
     *
     * @param array<int, array{nombre: string, valor: string}> $filas
     * @param array<string, string> $version
     */
    private function post(string $titulo, array $filas, array $version = []): array
    {
        return [
            'Especificacion' => ['titulo' => $titulo, 'idCategoria' => (string) $this->idCategoria, 'idSubcatalogo' => '', 'activo' => '1'],
            'EspecificacionVersion' => $version + ['modeloSugerido' => '', 'garantia' => '', 'adjuntoRuta' => '', 'notaCambio' => ''],
            'Caracteristicas' => $filas,
        ];
    }

    /**
     * @return int[]
     */
    private function idsEncontrados(string $texto): array
    {
        return array_map(static fn ($fila) => (int) $fila['especificacion']->id, $this->service->listar(['buscar' => $texto]));
    }

    private function emitir(int $idVersion): void
    {
        $proyecto = Proyecto::find()->one();
        if ($proyecto === null) {
            $this->markTestSkipped('La base local no tiene ningún proyecto.');
        }

        (new EspecificacionEmision([
            'numeroNet' => (int) EspecificacionEmision::find()->max('numeroNet') + 1,
            'anio' => 26,
            'idVersion' => $idVersion,
            'idProyecto' => $proyecto->id,
            'titulo' => 'test',
            'cantidad' => 1,
            'fechaEmision' => '2026-10-17',
            'vigenciaDias' => 30,
        ]))->save(false);
    }
}
