<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\CorrelativoDocumento;
use backend\models\EspecificacionEmision;
use backend\models\Presupuesto;
use backend\models\Proceso;
use backend\models\Proyecto;
use backend\services\CodificacionService;
use backend\services\ElaborarPresupuestoService;
use backend\services\PresupuestoService;
use backend\services\ProyectoService;
use Codeception\Test\Unit;
use ReflectionMethod;

/**
 * Codificacion segun el lineamiento de la DTIC RD-GD-LI-001: RD-PI-PT,
 * RD-<proceso>-PR y RD-<proceso>-ET-.../AA (el ET vuelve a 1 cada año), con
 * correlativos que fija el encargado y que nunca bajan del ultimo usado; y la
 * fecha de aprobacion del presupuesto.
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
        $this->assertSame('RD-GR-PR-016', CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_PRESUPUESTO, 16, 'GR'));
        $this->assertSame('RD-PI-ET-001/27', CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_ESPECIFICACION, 1, 'PI', 27));
        // Si en un año pasa del 999 sigue: nunca se repite un codigo.
        $this->assertSame('RD-PI-ET-1000/26', CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_ESPECIFICACION, 1000, 'PI', 26));

        $this->assertSame(4, CorrelativoDocumento::numeroDe(CorrelativoDocumento::TIPO_PROYECTO, 'RD-PI-PT-004'));
        $this->assertSame(16, CorrelativoDocumento::numeroDe(CorrelativoDocumento::TIPO_PRESUPUESTO, 'RD-GR-PR-016'));
        $this->assertSame(['proceso' => 'DS', 'numero' => 15, 'anio' => 26], CorrelativoDocumento::partesDe(CorrelativoDocumento::TIPO_ESPECIFICACION, 'RD-DS-ET-015/26'));
        $this->assertSame(['proceso' => 'PI', 'numero' => 15, 'anio' => null], CorrelativoDocumento::partesDe(CorrelativoDocumento::TIPO_ESPECIFICACION, 'RD-PI-ET-015'));
        $this->assertNull(CorrelativoDocumento::numeroDe(CorrelativoDocumento::TIPO_PRESUPUESTO, 'RD-PI-PT-004'));
        $this->assertNull(CorrelativoDocumento::numeroDe(CorrelativoDocumento::TIPO_PRESUPUESTO, '690'));
    }

    public function testElEtVuelveAUnoElPrimeroDeEnero(): void
    {
        // 31/12/2026: va por el 912.
        CorrelativoDocumento::updateAll(['siguiente' => 912, 'anio' => 26], ['tipo' => CorrelativoDocumento::TIPO_ESPECIFICACION]);

        $this->assertSame(912, $this->service->tomar(CorrelativoDocumento::TIPO_ESPECIFICACION, 26));
        // 01/01/2027: el primero del año es el 001, y sigue el 002.
        $this->assertSame(1, $this->service->tomar(CorrelativoDocumento::TIPO_ESPECIFICACION, 27));
        $this->assertSame(2, $this->service->tomar(CorrelativoDocumento::TIPO_ESPECIFICACION, 27));
        $fila = CorrelativoDocumento::findOne(CorrelativoDocumento::TIPO_ESPECIFICACION);
        $this->assertSame([3, 27], [(int) $fila->siguiente, (int) $fila->anio]);

        // Presupuestos y proyectos no se reinician.
        CorrelativoDocumento::updateAll(['siguiente' => 40], ['tipo' => CorrelativoDocumento::TIPO_PRESUPUESTO]);
        $this->assertSame(40, $this->service->tomar(CorrelativoDocumento::TIPO_PRESUPUESTO, 27));
    }

    public function testLaPantallaMuestraElPrimeroDelAnioNuevoYElUltimoUsadoEsDeEsteAnio(): void
    {
        $anio = $this->service->anioActual();
        // El contador quedo en el año pasado.
        CorrelativoDocumento::updateAll(['siguiente' => 850, 'anio' => $anio - 1], ['tipo' => CorrelativoDocumento::TIPO_ESPECIFICACION]);

        $correlativos = $this->service->correlativos();
        $this->assertSame(1, (int) $correlativos[CorrelativoDocumento::TIPO_ESPECIFICACION]->siguiente);
        $this->assertSame(sprintf('RD-GR-ET-001/%02d', $anio), $this->service->proximo(CorrelativoDocumento::TIPO_ESPECIFICACION, 'GR'));

        // Al guardar la pantalla queda en este año.
        $mayor = (int) EspecificacionEmision::find()->where(['serie' => EspecificacionEmision::SERIE_ET, 'anio' => $anio])->max('numeroNet');
        $this->assertTrue($this->service->guardar($correlativos, $this->datos($correlativos, [CorrelativoDocumento::TIPO_ESPECIFICACION => (string) ($mayor + 5)])));
        $fila = CorrelativoDocumento::findOne(CorrelativoDocumento::TIPO_ESPECIFICACION);
        $this->assertSame([$mayor + 5, $anio], [(int) $fila->siguiente, (int) $fila->anio]);

        // Una ET de otro año con numero alto no cuenta como "ultimo usado".
        $this->assertSame($mayor === 0 ? null : $mayor, $this->service->ultimoUsado(CorrelativoDocumento::TIPO_ESPECIFICACION));
        $this->assertSame(sprintf('%03d/%02d', 7, $anio), $this->service->numeroTexto(CorrelativoDocumento::TIPO_ESPECIFICACION, 7));
        $this->assertSame('007', $this->service->numeroTexto(CorrelativoDocumento::TIPO_PRESUPUESTO, 7));
    }

    public function testElPresupuestoLlevaElProcesoDelProyecto(): void
    {
        $gr = Proceso::findOne(['codigo' => 'GR']);
        if ($gr === null) {
            $this->markTestSkipped('La base local no tiene el proceso GR.');
        }
        $siguiente = $this->fijar(CorrelativoDocumento::TIPO_PRESUPUESTO);
        $proyecto = $this->proyecto();
        Proyecto::updateAll(['idProceso' => $gr->id], ['id' => $proyecto->id]);

        $presupuesto = (new ElaborarPresupuestoService())->delProyecto(Proyecto::findOne($proyecto->id));

        $this->assertSame(sprintf('RD-GR-PR-%03d', $siguiente), $presupuesto->numeroPresupuesto);
        $this->assertSame('GR', $presupuesto->codigoProceso());
        $this->assertSame($gr->nombre, Proceso::nombreDe($presupuesto->codigoProceso()));
        // El numero es uno solo para todos los procesos.
        $this->assertSame($siguiente, $this->service->ultimoUsado(CorrelativoDocumento::TIPO_PRESUPUESTO));
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
