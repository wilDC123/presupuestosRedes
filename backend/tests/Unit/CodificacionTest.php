<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\CorrelativoDocumento;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\CodificacionService;
use backend\services\ElaborarPresupuestoService;
use backend\services\PresupuestoService;
use backend\services\ProyectoService;
use Codeception\Test\Unit;
use ReflectionMethod;

/**
 * Codificacion segun el lineamiento de la DTIC RD-GD-LI-001: RD-PI-PT,
 * RD-PI-PR y RD-PI-ET, con correlativos que fija el encargado y que nunca
 * bajan del ultimo usado; y la fecha de aprobacion del presupuesto.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar (tambien
 * los correlativos).
 */
class CodificacionTest extends Unit
{
    private CodificacionService $service;

    protected function _before(): void
    {
        $this->service = new CodificacionService();
    }

    public function testFormatoDelCodigo(): void
    {
        $this->assertSame('RD-PI-PT-004', CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_PROYECTO, 4));
        $this->assertSame('RD-PI-PR-015', CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_PRESUPUESTO, 15));
        // Despues del 999 sigue: nunca se repite un codigo.
        $this->assertSame('RD-PI-ET-1000', CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_ESPECIFICACION, 1000));

        $this->assertSame(4, CorrelativoDocumento::numeroDe(CorrelativoDocumento::TIPO_PROYECTO, 'RD-PI-PT-004'));
        $this->assertNull(CorrelativoDocumento::numeroDe(CorrelativoDocumento::TIPO_PRESUPUESTO, 'RD-PI-PT-004'));
        $this->assertNull(CorrelativoDocumento::numeroDe(CorrelativoDocumento::TIPO_PRESUPUESTO, '690'));
    }

    public function testElProyectoRecibeElSiguienteCodigoPt(): void
    {
        $siguiente = $this->fijar(CorrelativoDocumento::TIPO_PROYECTO);
        $proyecto = new Proyecto(['nombre' => 'Proyecto test codigo', 'estadoLista' => Proyecto::LISTA_EDICION]);

        // Es privado: crear() exige facultad, edificio, etc. de los parametros.
        (new ReflectionMethod(ProyectoService::class, 'insertarConCodigo'))->invoke(new ProyectoService(), $proyecto);

        $this->assertSame(CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_PROYECTO, $siguiente), $proyecto->codigo);
        $this->assertSame($siguiente + 1, $this->siguiente(CorrelativoDocumento::TIPO_PROYECTO));
    }

    public function testElPresupuestoRecibeSuCodigoPrAlCrearseUnaSolaVez(): void
    {
        $siguiente = $this->fijar(CorrelativoDocumento::TIPO_PRESUPUESTO);
        $proyecto = $this->proyecto();
        $service = new ElaborarPresupuestoService();

        $presupuesto = $service->delProyecto($proyecto);
        $this->assertSame(Presupuesto::ESTADO_EN_PROCESO, $presupuesto->estado);
        $this->assertSame(sprintf('RD-PI-PR-%03d', $siguiente), $presupuesto->numeroPresupuesto);

        // Volver a entrar no gasta otro numero.
        $proyecto = Proyecto::findOne($proyecto->id);
        $this->assertSame($presupuesto->id, $service->delProyecto($proyecto)->id);
        $this->assertSame($siguiente + 1, $this->siguiente(CorrelativoDocumento::TIPO_PRESUPUESTO));
    }

    public function testUnPresupuestoViejoSinNumeroLoRecibeYUnoEscritoAManoSeConserva(): void
    {
        $siguiente = $this->fijar(CorrelativoDocumento::TIPO_PRESUPUESTO);
        $service = new ElaborarPresupuestoService();

        $sinNumero = $this->proyecto();
        (new Presupuesto(['idProyecto' => $sinNumero->id, 'estado' => Presupuesto::ESTADO_EN_PROCESO]))->save(false);
        $conNumero = $this->proyecto();
        (new Presupuesto(['idProyecto' => $conNumero->id, 'estado' => Presupuesto::ESTADO_APROBADO, 'numeroPresupuesto' => '690']))->save(false);

        $this->assertSame(sprintf('RD-PI-PR-%03d', $siguiente), $service->delProyecto($sinNumero)->numeroPresupuesto);
        $this->assertSame('690', $service->delProyecto($conNumero)->numeroPresupuesto);
    }

    public function testElSiguienteNoPuedeBajarDelUltimoUsado(): void
    {
        $siguiente = $this->fijar(CorrelativoDocumento::TIPO_PRESUPUESTO);
        $usado = (new ElaborarPresupuestoService())->delProyecto($this->proyecto());
        $ultimo = CorrelativoDocumento::numeroDe(CorrelativoDocumento::TIPO_PRESUPUESTO, $usado->numeroPresupuesto);
        $this->assertSame($siguiente, $ultimo);
        $this->assertSame($ultimo, $this->service->ultimoUsado(CorrelativoDocumento::TIPO_PRESUPUESTO));

        $correlativos = $this->service->correlativos();
        $datos = $this->datos($correlativos, [CorrelativoDocumento::TIPO_PRESUPUESTO => (string) $ultimo]);
        $this->assertFalse($this->service->guardar($correlativos, $datos));
        $this->assertTrue($correlativos[CorrelativoDocumento::TIPO_PRESUPUESTO]->hasErrors('siguiente'));
        // No se guardo ninguno.
        $this->assertSame($ultimo + 1, $this->siguiente(CorrelativoDocumento::TIPO_PRESUPUESTO));

        $correlativos = $this->service->correlativos();
        $datos = $this->datos($correlativos, [CorrelativoDocumento::TIPO_PRESUPUESTO => (string) ($ultimo + 10)]);
        $this->assertTrue($this->service->guardar($correlativos, $datos));
        $this->assertSame($ultimo + 10, $this->siguiente(CorrelativoDocumento::TIPO_PRESUPUESTO));
    }

    public function testAlAprobarSeGuardaLaFechaDeAprobacion(): void
    {
        $proyecto = $this->proyecto();
        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => Presupuesto::ESTADO_EN_REVISION]);
        $presupuesto->save(false);
        $this->assertSame('', $presupuesto->fechaAprobacionFormateada());

        $this->assertTrue((new PresupuestoService())->cambiarEstado($presupuesto, Presupuesto::ESTADO_APROBADO));

        $presupuesto = Presupuesto::findOne($presupuesto->id);
        $this->assertSame(date('Y-m-d'), substr((string) $presupuesto->fechaAprobacion, 0, 10));
        $this->assertSame(date('d/m/Y'), $presupuesto->fechaAprobacionFormateada());
    }

    /**
     * Pone el correlativo por encima de todo lo usado en la base local y
     * devuelve ese numero.
     */
    private function fijar(string $tipo): int
    {
        $numero = (int) $this->service->ultimoUsado($tipo) + 500;
        CorrelativoDocumento::updateAll(['siguiente' => $numero], ['tipo' => $tipo]);

        return $numero;
    }

    private function siguiente(string $tipo): int
    {
        return (int) CorrelativoDocumento::findOne($tipo)->siguiente;
    }

    private function proyecto(): Proyecto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test codigo', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);

        return $proyecto;
    }

    /**
     * Lo que manda el formulario: el valor actual de cada correlativo,
     * con los cambios de $cambios.
     *
     * @param CorrelativoDocumento[] $correlativos
     * @param array<string, string> $cambios
     */
    private function datos(array $correlativos, array $cambios): array
    {
        $datos = [];
        foreach ($correlativos as $tipo => $correlativo) {
            $datos[$tipo] = ['siguiente' => $cambios[$tipo] ?? (string) $correlativo->siguiente];
        }

        return ['CorrelativoDocumento' => $datos];
    }
}
