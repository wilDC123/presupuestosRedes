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
    }
}
