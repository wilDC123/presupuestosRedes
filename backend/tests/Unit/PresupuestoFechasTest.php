<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Presupuesto;
use backend\models\Proyecto;
use Codeception\Test\Unit;

/**
 * Fechas del presupuesto (revision del Ing. de DTIC del 2026-10-29, paso 9):
 * arriba la de CREACION del documento (no la escrita a mano al enviar a
 * revision, que ya no se pide) y abajo la de aprobacion. (El año del oficio
 * lo calcula la carta de entrega: ver CartaPresupuestoTest.)
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class PresupuestoFechasTest extends Unit
{
    public function testLaFechaDelDocumentoEsLaDeCreacion(): void
    {
        $presupuesto = $this->presupuesto();
        $presupuesto->fecha = '2026-01-15';

        $this->assertSame(date('d/m/Y'), $presupuesto->fechaFormateada());
    }

    public function testEnviarARevisionYaNoPideFecha(): void
    {
        $presupuesto = $this->presupuesto();
        $presupuesto->setAttributes(['numeroPresupuesto' => 'TEST-1', 'oficioDtic' => '1', 'areaIntervencion' => 'Bloque A'], false);
        $presupuesto->scenario = Presupuesto::SCENARIO_ENVIAR;

        $this->assertTrue($presupuesto->validate(), implode(' ', $presupuesto->getFirstErrors()));
    }

    private function presupuesto(): Presupuesto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test fechas', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);
        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id]);
        $presupuesto->save(false);

        // Se vuelve a leer para tener la fechaCreacion que pone la base (GETDATE()).
        return Presupuesto::findOne($presupuesto->id);
    }
}
