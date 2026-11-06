<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Personal;
use backend\models\Proyecto;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use backend\services\RolProyectoService;
use Codeception\Test\Unit;
use Yii;

/**
 * Ficha tecnica del proyecto (revision del 2026-10-29, paso 7, formato
 * provisional): lleva los datos del proyecto, el equipo con su responsable,
 * las fechas y los documentos de la carpeta economica.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class FichaProyectoTest extends Unit
{
    public function testLaFichaLlevaDatosEquipoFechasYDocumentos(): void
    {
        (new Personal(['nombre' => 'Ing. Test Ficha']))->save(false);
        $proyectos = new ProyectoService();
        $proyecto = new Proyecto();
        $this->assertTrue($proyectos->crear($proyecto, ['Proyecto' => [
            'nombre' => 'Proyecto test ficha',
            'idProceso' => (new ProcesoService())->idPorDefecto(),
            'unidadDependencia' => 'Canal Universitario test',
            'unidadSolicitante' => 'Rectorado test',
            'areas' => [['manual' => '1', 'edificio' => 'Edificio en construcción test']],
            'equipo' => [['nombre' => 'Ing. Test Ficha', 'idRol' => (string) (new RolProyectoService())->idProyectista()]],
            'equipoResponsable' => '0',
        ]]), json_encode($proyecto->errors));
        $proyecto = Proyecto::findOne($proyecto->id);

        $datos = $proyectos->datosFicha($proyecto);
        $this->assertNull($datos['monto']);
        $this->assertSame([], $datos['especificaciones']);
        $this->assertNotNull($datos['fechas']['creacion']);

        $html = Yii::$app->view->render('@backend/views/proyecto/ficha-pdf.php', ['model' => $proyecto, 'datos' => $datos, 'usuario' => 'encargado']);

        foreach ([
            'FICHA TÉCNICA DEL PROYECTO', $proyecto->codigo, 'Proyecto test ficha', 'Canal Universitario test',
            'Rectorado test', 'Edificio en construcción test', 'Ing. Test Ficha', 'Proyectista', 'Todavía no tiene.', 'por encargado',
        ] as $esperado) {
            $this->assertStringContainsString($esperado, $html);
        }

        // Revision del 2026-11-05: documento normado, en el orden nuevo, sin "Fase" ni lugar de entrega.
        $this->assertFalse($datos['aprobada']);
        $this->assertFalse($datos['variasFases']);
        $this->assertStringContainsString('ELABORADO POR', $html);
        $this->assertStringContainsString('APROBADO POR', $html);
        $this->assertStringContainsString('Versión: 1.0', $html);
        $this->assertStringNotContainsString('<th>Fase</th>', $html);
        $this->assertStringNotContainsString('Lugar de entrega', $html);
        $this->assertStringNotContainsString('Reporte de control', $html);
        $orden = array_map(static fn (string $titulo) => strpos($html, $titulo), ['1. Datos', '2. Unidades', '3. Fechas', '4. Área', '5. Equipo', '6. Documentos']);
        $this->assertNotContains(false, $orden);
        $ordenado = $orden;
        sort($ordenado);
        $this->assertSame($ordenado, $orden);

        // Con una fase siguiente ya muestra "Fase".
        // (misma regla que el sistema: la fase 2 lleva el mismo numero de proyecto)
        (new Proyecto(['nombre' => 'Proyecto test ficha fase 2', 'idProyectoAnterior' => $proyecto->id, 'numeroProyecto' => $proyecto->numeroProyecto, 'numeroFase' => 2, 'estadoLista' => Proyecto::LISTA_EDICION]))->save(false);
        $proyecto = Proyecto::findOne($proyecto->id);
        $datos = $proyectos->datosFicha($proyecto);
        $this->assertTrue($datos['variasFases']);
        $this->assertStringContainsString('<th>Fase</th>', Yii::$app->view->render('@backend/views/proyecto/ficha-pdf.php', ['model' => $proyecto, 'datos' => $datos, 'usuario' => 'encargado']));
    }
}
