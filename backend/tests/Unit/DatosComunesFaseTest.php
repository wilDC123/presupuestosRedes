<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\ProyectoService;
use Codeception\Test\Unit;

/**
 * La nota "se editan en la Fase 1" de la vista del proyecto (revision de
 * codigo del 2026-11-11): ProyectoService::faseParaDatosComunes() da la fase
 * 1 solo si hoy deja cambiar los datos comunes.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class DatosComunesFaseTest extends Unit
{
    /** Un numero de proyecto que la base local no usa. */
    private const NUMERO = 99901;

    private ProyectoService $service;

    protected function _before(): void
    {
        $this->service = new ProyectoService();
    }

    public function testSinNadaAprobadoDaLaFaseUno(): void
    {
        [$fase1, $fase2] = $this->dosFases();

        $this->assertSame($fase1->id, $this->service->faseParaDatosComunes($fase2)?->id);
    }

    public function testConOtraFaseAprobadaNoDaNinguna(): void
    {
        [, $fase2] = $this->dosFases();
        $this->presupuesto($fase2, Presupuesto::ESTADO_APROBADO);

        $this->assertNull($this->service->faseParaDatosComunes(Proyecto::findOne($fase2->id)));
    }

    public function testConLaFaseUnoAprobadaNoDaNinguna(): void
    {
        [$fase1, $fase2] = $this->dosFases();
        $this->presupuesto($fase1, Presupuesto::ESTADO_APROBADO);

        $this->assertNull($this->service->faseParaDatosComunes(Proyecto::findOne($fase2->id)));
    }

    public function testEnRevisionTodaviaSeEditan(): void
    {
        // En revision el presupuesto no se edita, pero los datos del proyecto si (Presupuesto::puedeEditarse()).
        [$fase1, $fase2] = $this->dosFases();
        $this->presupuesto($fase1, Presupuesto::ESTADO_EN_REVISION);

        $this->assertSame($fase1->id, $this->service->faseParaDatosComunes(Proyecto::findOne($fase2->id))?->id);
    }

    /**
     * @return Proyecto[] [fase 1, fase 2]
     */
    private function dosFases(): array
    {
        $fase1 = new Proyecto(['nombre' => 'Proyecto test datos comunes', 'numeroProyecto' => self::NUMERO, 'numeroFase' => 1, 'estadoLista' => Proyecto::LISTA_EDICION]);
        $fase1->save(false);
        $fase2 = new Proyecto(['nombre' => 'Proyecto test datos comunes', 'numeroProyecto' => self::NUMERO, 'numeroFase' => 2, 'idProyectoAnterior' => $fase1->id, 'estadoLista' => Proyecto::LISTA_EDICION]);
        $fase2->save(false);

        return [Proyecto::findOne($fase1->id), Proyecto::findOne($fase2->id)];
    }

    private function presupuesto(Proyecto $fase, string $estado): void
    {
        (new Presupuesto(['idProyecto' => $fase->id, 'estado' => $estado]))->save(false);
    }
}
