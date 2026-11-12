<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\ProyectoService;
use Codeception\Test\Unit;

/**
 * La franja "FASES" de la vista del proyecto (ProyectoService::franjaFases(),
 * auditoria del 2026-11-11, hallazgo 2): una pastilla por fase, sus ordenes de
 * cambio despues, la que se esta viendo marcada; con fase unica y sin ordenes
 * no sale.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class FranjaFasesTest extends Unit
{
    /** Un numero de proyecto que la base local no usa. */
    private const NUMERO = 99903;

    private ProyectoService $service;

    protected function _before(): void
    {
        $this->service = new ProyectoService();
    }

    public function testConFaseUnicaNoSale(): void
    {
        $fase1 = $this->fase(1);

        $this->assertSame([], $this->service->franjaFases($fase1));
    }

    public function testUnaPastillaPorFaseConSusOrdenesDeCambioDespues(): void
    {
        $fase1 = $this->fase(1);
        $fase2 = $this->fase(2, $fase1);
        $orden = $this->ordenDeCambio($fase1);
        (new Presupuesto(['idProyecto' => $fase1->id, 'estado' => Presupuesto::ESTADO_APROBADO]))->save(false);

        $grupos = $this->service->franjaFases(Proyecto::findOne($fase2->id));

        // Un grupo por fase; la orden de cambio va dentro del grupo de su fase, despues de ella.
        $this->assertSame([[$fase1->id, $orden->id], [$fase2->id]], array_map(static fn (array $g) => array_column($g, 'id'), $grupos));
        $this->assertSame(['Fase 1', 'Orden de cambio N.° 1'], array_column($grupos[0], 'texto'));
        // La que se esta viendo, marcada.
        $this->assertSame([false, false, true], array_merge(array_column($grupos[0], 'actual'), array_column($grupos[1], 'actual')));
        // Sin presupuesto: sin monto ni estado.
        $this->assertNull($grupos[1][0]['presupuesto']);
        $this->assertNull($grupos[1][0]['monto']);
        $this->assertSame(0.0, $grupos[0][0]['monto'], 'Presupuesto sin líneas: monto 0.');
    }

    public function testDesdeLaOrdenDeCambioSeVeLaMismaFranja(): void
    {
        $fase1 = $this->fase(1);
        $orden = $this->ordenDeCambio($fase1);

        $grupos = $this->service->franjaFases(Proyecto::findOne($orden->id));

        // Fase unica pero con una orden de cambio: la franja sale, con la orden marcada.
        $this->assertCount(1, $grupos);
        $this->assertSame([false, true], array_column($grupos[0], 'actual'));
    }

    private function fase(int $numero, ?Proyecto $anterior = null): Proyecto
    {
        $fase = new Proyecto([
            'nombre' => 'Proyecto test franja',
            'numeroProyecto' => self::NUMERO,
            'numeroFase' => $numero,
            'idProyectoAnterior' => $anterior?->id,
            'estadoLista' => Proyecto::LISTA_EDICION,
        ]);
        $fase->save(false);

        return Proyecto::findOne($fase->id);
    }

    private function ordenDeCambio(Proyecto $fase): Proyecto
    {
        $orden = new Proyecto([
            'nombre' => $fase->nombre,
            'numeroProyecto' => $fase->numeroProyecto,
            'numeroFase' => $fase->numeroFase,
            'idFaseOrigen' => $fase->id,
            'numeroOrdenCambio' => 1,
            'estadoLista' => Proyecto::LISTA_EDICION,
        ]);
        $orden->save(false);

        return Proyecto::findOne($orden->id);
    }
}
