<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\ConfiguracionPresupuesto;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\PresupuestoService;
use Codeception\Test\Unit;

/**
 * Control de elaboracion del presupuesto (lineamiento RD-GD-LI-001,
 * documento controlado): al aprobar, las firmas se copian al presupuesto y
 * el PDF de un aprobado ya no cambia aunque se edite la configuracion. Los
 * no aprobados siguen leyendo la configuracion.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class FirmasPresupuestoAprobadoTest extends Unit
{
    private PresupuestoService $service;

    protected function _before(): void
    {
        $this->service = new PresupuestoService();

        ConfiguracionPresupuesto::updateAll([
            'firma1Prefijo' => null, 'firma1Nombre' => 'Ing. Quien Elabora', 'firma1Cargo' => 'PROYECTOS DE REDES',
            'firma2Prefijo' => 'V°B°', 'firma2Nombre' => 'Ing. Quien Aprueba', 'firma2Cargo' => 'RESPONSABLE DE REDES',
        ]);
    }

    public function testAlAprobarSeCopianLasFirmas(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_REVISION);

        $this->assertTrue($this->service->cambiarEstado($presupuesto, Presupuesto::ESTADO_APROBADO));

        $presupuesto = Presupuesto::findOne($presupuesto->id);
        $this->assertSame('Ing. Quien Elabora', $presupuesto->firma1Nombre);
        $this->assertSame('PROYECTOS DE REDES', $presupuesto->firma1Cargo);
        $this->assertSame('V°B°', $presupuesto->firma2Prefijo);
        $this->assertSame('Ing. Quien Aprueba', $presupuesto->firma2Nombre);
        $this->assertSame('RESPONSABLE DE REDES', $presupuesto->firma2Cargo);
    }

    public function testUnAprobadoNoCambiaAlEditarLaConfiguracion(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_REVISION);
        $this->service->cambiarEstado($presupuesto, Presupuesto::ESTADO_APROBADO);

        // Cambia el responsable de redes despues de aprobar.
        ConfiguracionPresupuesto::updateAll(['firma2Nombre' => 'Ing. Responsable Nuevo']);
        $configuracion = ConfiguracionPresupuesto::obtener();
        $presupuesto = Presupuesto::findOne($presupuesto->id);

        $this->assertSame('Ing. Quien Aprueba', $presupuesto->firmaDeControl(2, $configuracion)['nombre']);

        $html = $this->html($presupuesto, $configuracion);
        $this->assertStringContainsString('V°B° Ing. Quien Aprueba', $html);
        $this->assertStringNotContainsString('Ing. Responsable Nuevo', $html);
    }

    public function testUnBorradorUsaLaConfiguracionDeHoy(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);
        ConfiguracionPresupuesto::updateAll(['firma2Nombre' => 'Ing. Responsable Nuevo']);
        $configuracion = ConfiguracionPresupuesto::obtener();

        $this->assertNull($presupuesto->firma2Nombre);
        $this->assertSame('Ing. Responsable Nuevo', $presupuesto->firmaDeControl(2, $configuracion)['nombre']);
        $this->assertStringContainsString('V°B° Ing. Responsable Nuevo', $this->html($presupuesto, $configuracion));
    }

    public function testAprobadoConFirmaVaciaQuedaEnBlanco(): void
    {
        // Sin nombre en la configuracion al aprobar: queda en blanco para
        // llenarla a mano, aunque despues se escriba un nombre.
        ConfiguracionPresupuesto::updateAll(['firma1Prefijo' => null, 'firma1Nombre' => null, 'firma1Cargo' => null]);
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_REVISION);
        $this->service->cambiarEstado($presupuesto, Presupuesto::ESTADO_APROBADO);
        ConfiguracionPresupuesto::updateAll(['firma1Nombre' => 'Ing. Escrito Despues']);

        $presupuesto = Presupuesto::findOne($presupuesto->id);

        $this->assertNull($presupuesto->firmaDeControl(1, ConfiguracionPresupuesto::obtener()));
    }

    private function html(Presupuesto $presupuesto, ConfiguracionPresupuesto $configuracion): string
    {
        return (new \yii\web\View())->renderFile(\Yii::getAlias('@backend/views/presupuesto/pdf.php'), [
            'presupuesto' => $presupuesto,
            'grupos' => [],
            'totalGeneral' => 0.0,
            'configuracion' => $configuracion,
            'oficio' => '',
            'proceso' => 'Proceso test',
        ]);
    }

    private function crearPresupuesto(string $estado): Presupuesto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test firmas', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);

        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => $estado, 'numeroPresupuesto' => 'TEST-1', 'fecha' => '2026-10-20']);
        $presupuesto->save(false);

        return $presupuesto;
    }
}
