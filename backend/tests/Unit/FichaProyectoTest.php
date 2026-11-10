<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Personal;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\models\TipoOrdenCambio;
use backend\services\OrdenCambioFaseService;
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
            'FICHA TÉCNICA DEL PROYECTO', $proyecto->codigo, $proyecto->numeroTexto(), 'PROYECTO TEST FICHA', 'Canal Universitario test',
            'Rectorado test', 'Edificio en construcción test', 'Ing. Test Ficha', 'Proyectista', 'Todavía no tiene.', 'por encargado',
        ] as $esperado) {
            $this->assertStringContainsString($esperado, $html);
        }

        // Formato de los formatos corregidos v3 (2026-11-09): sin "Fase", sin lugar de entrega y SIN firmas.
        $this->assertFalse($datos['aprobada']);
        $this->assertFalse($datos['variasFases']);
        $this->assertSame('1.0', $datos['version']);
        $this->assertStringNotContainsString('ELABORADO POR', $html);
        $this->assertStringNotContainsString('APROBADO POR', $html);
        $this->assertStringContainsString('Versión: 1.0', $html);
        $this->assertStringContainsString('Todavía no hay presupuestos aprobados', $html);
        $this->assertStringNotContainsString('FASE 1', $html);
        $this->assertStringNotContainsString('Lugar de entrega', $html);
        $this->assertStringNotContainsString('Reporte de control', $html);
        $this->assertEnOrden($html, ['1. Información general', 'Costo total del proyecto', 'Fecha de creación', '2. Unidades', '3. Área', '4. Equipo', '5. Carpeta económica', 'Lista de materiales y condiciones previas (BoM)']);
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
            $this->assertEnOrden($html, ['1. Información general', 'Fechas', '<th class="centro">Fase 1</th>', '<th class="centro">Fase 2</th>', '2. Unidades', '3. FASE 1', '3.1 Área', 'Edificio test fase 1', '3.3 Carpeta económica', '4. FASE 2', '4.1 Área', 'Edificio test fase 2']);
            $this->assertStringNotContainsString('5. Carpeta', $html);
        }

        // Al aprobarse la fase 2, la 1.1 queda aprobada con su fecha.
        $this->aprobar($fase2, '2026-11-04');
        $datos = $this->proyectos->datosFicha(Proyecto::findOne($fase2->id));
        $this->assertSame(['1.1', true, '04/11/2026'], [$datos['version'], $datos['aprobada'], $datos['fechaAprobacion']]);
    }

    /**
     * Varias fases a la vez (2026-11-09): la version cuenta las fases en el
     * orden en que se aprobaron, y la ficha tecnica del proyecto sigue en
     * BORRADOR mientras alguna fase este sin aprobar (una cancelada no cuenta).
     */
    public function testConFasesALaVezLaVersionSigueElOrdenDeAprobacion(): void
    {
        $fase1 = $this->crear(null, 'Edificio test simultánea 1');
        $fase2 = $this->crear($fase1->id, 'Edificio test simultánea 2');
        $datos = $this->proyectos->datosFicha(Proyecto::findOne($fase1->id));
        $this->assertSame(['1.0', false], [$datos['version'], $datos['aprobada']]);

        // Se aprueba primero la fase 2: es la 1.0, pero la ficha sigue en borrador (falta la fase 1).
        $this->aprobar($fase2, '2026-11-04');
        $datos = $this->proyectos->datosFicha(Proyecto::findOne($fase1->id));
        $this->assertSame(['1.1', false, ''], [$datos['version'], $datos['aprobada'], $datos['fechaAprobacion']]);

        // Con la fase 1 aprobada despues, la 1.1 queda aprobada con la fecha mas reciente.
        $this->aprobar($fase1, '2026-11-08');
        $datos = $this->proyectos->datosFicha(Proyecto::findOne($fase2->id));
        $this->assertSame(['1.1', true, '08/11/2026'], [$datos['version'], $datos['aprobada'], $datos['fechaAprobacion']]);

        // Una fase 3 cancelada no deja la ficha en borrador.
        $fase3 = $this->crear($fase2->id, 'Edificio test simultánea 3');
        (new Presupuesto(['idProyecto' => $fase3->id, 'estado' => Presupuesto::ESTADO_CANCELADO]))->save(false);
        $datos = $this->proyectos->datosFicha(Proyecto::findOne($fase3->id));
        $this->assertSame(['1.1', true], [$datos['version'], $datos['aprobada']]);
    }

    /**
     * Parte 6c (formatos corregidos v3): la orden de cambio va dentro de la
     * carpeta de su fase, y el costo total suma los presupuestos aprobados de
     * fases y ordenes.
     */
    public function testLaOrdenDeCambioVaEnLaCarpetaDeSuFaseYSumaAlCostoTotal(): void
    {
        $fase1 = $this->crear(null, 'Edificio test orden ficha');
        $this->aprobar($fase1, '2026-10-23');
        $fase1 = Proyecto::findOne($fase1->id);
        $ordenes = new OrdenCambioFaseService();
        $orden = $ordenes->nueva($fase1);
        $tipo = TipoOrdenCambio::find()->select('id')->scalar();
        $this->assertTrue($ordenes->crear($fase1, $orden, ['Proyecto' => [
            'descripcionCambio' => 'Dos cámaras más', 'motivoCambio' => 'El sector de gradas queda sin cobertura', 'idTipoOrdenCambio' => (string) $tipo,
        ]]), json_encode($orden->errors));

        // Sin aprobar: el costo total es solo el de la fase.
        $this->assertSame(['Presupuesto'], array_column($this->proyectos->costoTotal($fase1)['partes'], 'rotulo'));

        $this->aprobar(Proyecto::findOne($orden->id), '2026-11-09');
        // Leida de nuevo: la de arriba ya tiene en cache sus ordenes sin aprobar (como un pedido nuevo del navegador).
        $fase1 = Proyecto::findOne($fase1->id);
        $datos = $this->proyectos->datosFicha($fase1);
        // La orden no cambia la version ni cuenta como fase.
        $this->assertSame(['1.0', false], [$datos['version'], $datos['variasFases']]);
        $this->assertCount(1, $datos['fases'][0]['ordenes']);
        $this->assertSame(['Presupuesto', 'orden de cambio N.° 1'], array_column($datos['costo']['partes'], 'rotulo'));

        $html = $this->html($fase1, $datos);
        $this->assertEnOrden($html, [
            '5. Carpeta económica', $fase1->codigoLista, 'Orden de cambio N.° 1 · solicitada el', 'aprobada el 09-11-2026',
            'Motivo: El sector de gradas queda sin cobertura', Proyecto::findOne($orden->id)->codigoLista,
        ]);
        $this->assertStringContainsString('Presupuesto: 0.00 + orden de cambio N.° 1: 0.00 (presupuestos aprobados)', $html);
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
