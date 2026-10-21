<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\ElaborarPresupuestoService;
use Codeception\Test\Unit;

/**
 * Mano de obra a cargo de la DTIC (Presupuesto.manoDeObra): se pone y se
 * quita solo en borrador; incluida sin texto usa el texto de siempre.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class ManoDeObraTest extends Unit
{
    private ElaborarPresupuestoService $service;

    protected function _before(): void
    {
        $this->service = new ElaborarPresupuestoService();
    }

    public function testIncluirSinTextoUsaElTextoDeSiempreYQuitarLaDejaEnNull(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);

        $this->assertNull($this->service->guardarManoDeObra($presupuesto, true, '   '));
        $this->assertSame(Presupuesto::MANO_DE_OBRA_DTIC, Presupuesto::findOne($presupuesto->id)->manoDeObra);

        $this->assertNull($this->service->guardarManoDeObra($presupuesto, true, 'Instalación por personal de la DTIC'));
        $this->assertSame('Instalación por personal de la DTIC', Presupuesto::findOne($presupuesto->id)->manoDeObra);

        $this->assertNull($this->service->guardarManoDeObra($presupuesto, false, 'se ignora'));
        $this->assertNull(Presupuesto::findOne($presupuesto->id)->manoDeObra);
    }

    public function testNoSeCambiaFueraDeBorrador(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_REVISION);

        $this->assertNotNull($this->service->guardarManoDeObra($presupuesto, true, ''));
        $this->assertNull(Presupuesto::findOne($presupuesto->id)->manoDeObra);
    }

    private function crearPresupuesto(string $estado): Presupuesto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test mano de obra', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);

        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => $estado]);
        $presupuesto->save(false);

        return $presupuesto;
    }
}
