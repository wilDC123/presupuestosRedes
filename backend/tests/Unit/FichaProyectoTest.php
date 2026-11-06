<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Personal;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use backend\services\RolProyectoService;
use Codeception\Test\Unit;
use Yii;

/**
 * Ficha tecnica del proyecto: documento normado (revision del 2026-11-05,
 * punto 2) y UNA por proyecto, con un bloque por fase (parte 5d): datos,
 * unidades y fechas del proyecto; area, equipo y documentos de cada fase.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class FichaProyectoTest extends Unit
{
    private ProyectoService $proyectos;

    protected function _before(): void
    {
        $this->proyectos = new ProyectoService();
        (new Personal(['nombre' => 'Ing. Test Ficha']))->save(false);
    }

    public function testLaFichaLlevaDatosEquipoFechasYDocumentos(): void
    {
        $proyecto = $this->crear(null, 'Edificio en construcción test');

        $datos = $this->proyectos->datosFicha($proyecto);
        $this->assertCount(1, $datos['fases']);
        $this->assertNull($datos['fases'][0]['monto']);
        $this->assertSame([], $datos['fases'][0]['especificaciones']);
        $this->assertNotNull($datos['fases'][0]['fechas']['creacion']);
        $this->assertSame($proyecto->codigo, $datos['codigo']);

        $html = $this->html($proyecto, $datos);

        foreach ([
            'FICHA TÉCNICA DEL PROYECTO', $proyecto->codigo, $proyecto->numeroTexto(), 'Proyecto test ficha', 'Canal Universitario test',
            'Rectorado test', 'Edificio en construcción test', 'Ing. Test Ficha', 'Proyectista', 'Todavía no tiene.', 'por encargado',
        ] as $esperado) {
            $this->assertStringContainsString($esperado, $html);
        }

        // Documento normado, en el orden nuevo, sin "Fase" ni lugar de entrega.
        $this->assertFalse($datos['aprobada']);
        $this->assertFalse($datos['variasFases']);
        $this->assertSame('1.0', $datos['version']);
        $this->assertStringContainsString('ELABORADO POR', $html);
        $this->assertStringContainsString('APROBADO POR', $html);
        $this->assertStringContainsString('Versión: 1.0', $html);
        $this->assertStringNotContainsString('FASE 1', $html);
        $this->assertStringNotContainsString('Lugar de entrega', $html);
        $this->assertStringNotContainsString('Reporte de control', $html);
        $this->assertEnOrden($html, ['1. Datos', '2. Unidades', '3. Fechas', '4. Área', '5. Equipo', '6. Documentos']);
    }

    /**
     * Parte 5d: un solo codigo PT por proyecto; la ficha se imprime igual
     * desde cualquier fase, con un bloque por fase, y cada fase aprobada es
     * una version (1.0, 1.1...).
     */
    public function testUnaSolaFichaConUnBloquePorFaseYSusVersiones(): void
    {
        $fase1 = $this->crear(null, 'Edificio test fase 1');
        $this->aprobar($fase1, '2026-10-23');
        $datos = $this->proyectos->datosFicha(Proyecto::findOne($fase1->id));
        $this->assertSame(['1.0', true, '23/10/2026'], [$datos['version'], $datos['aprobada'], $datos['fechaAprobacion']]);

        $fase2 = $this->crear($fase1->id, 'Edificio test fase 2');
        // La fase 2 no toma otro codigo PT: la ficha es la de la fase 1.
        $this->assertNull($fase2->codigo);
        $this->assertSame($fase1->codigo, $fase2->codigoFicha());

        // Desde cualquier fase, la misma ficha: version 1.1 en borrador, con las dos fases.
        foreach ([$fase1->id, $fase2->id] as $id) {
            $datos = $this->proyectos->datosFicha(Proyecto::findOne($id));
            $this->assertSame([$fase1->codigo, '1.1', false, ''], [$datos['codigo'], $datos['version'], $datos['aprobada'], $datos['fechaAprobacion']]);
            $html = $this->html(Proyecto::findOne($id), $datos);
            $this->assertEnOrden($html, ['1. Datos', '2. Unidades', '3. Fechas', '4. FASE 1', '4.1 Área', 'Edificio test fase 1', '4.3 Documentos', '5. FASE 2', '5.1 Área', 'Edificio test fase 2']);
            $this->assertStringNotContainsString('6. Documentos', $html);
        }

        // Al aprobarse la fase 2, la 1.1 queda aprobada con su fecha.
        $this->aprobar($fase2, '2026-11-04');
        $datos = $this->proyectos->datosFicha(Proyecto::findOne($fase2->id));
        $this->assertSame(['1.1', true, '04/11/2026'], [$datos['version'], $datos['aprobada'], $datos['fechaAprobacion']]);
    }

    private function crear(?int $idAnterior, string $edificio): Proyecto
    {
        $proyecto = new Proyecto();
        $this->assertTrue($this->proyectos->crear($proyecto, ['Proyecto' => [
            'nombre' => 'Proyecto test ficha',
            'idProceso' => (string) (new ProcesoService())->idPorDefecto(),
            'idProyectoAnterior' => $idAnterior === null ? '' : (string) $idAnterior,
            'unidadDependencia' => 'Canal Universitario test',
            'unidadSolicitante' => 'Rectorado test',
            'areas' => [['manual' => '1', 'edificio' => $edificio]],
            'equipo' => [['nombre' => 'Ing. Test Ficha', 'idRol' => (string) (new RolProyectoService())->idProyectista()]],
            'equipoResponsable' => '0',
        ]]), json_encode($proyecto->errors));

        return Proyecto::findOne($proyecto->id);
    }

    private function aprobar(Proyecto $proyecto, string $fecha): void
    {
        (new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => Presupuesto::ESTADO_APROBADO, 'fechaAprobacion' => $fecha]))->save(false);
    }

    private function html(Proyecto $proyecto, array $datos): string
    {
        return Yii::$app->view->render('@backend/views/proyecto/ficha-pdf.php', ['model' => $proyecto, 'datos' => $datos, 'usuario' => 'encargado']);
    }

    /** @param string[] $textos */
    private function assertEnOrden(string $html, array $textos): void
    {
        $posiciones = array_map(static fn (string $texto) => strpos($html, $texto), $textos);
        $this->assertNotContains(false, $posiciones, implode(' | ', $textos));
        $ordenadas = $posiciones;
        sort($ordenadas);
        $this->assertSame($ordenadas, $posiciones);
    }
}
