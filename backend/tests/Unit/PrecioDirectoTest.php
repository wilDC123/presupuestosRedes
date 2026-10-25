<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use backend\services\ElaborarPresupuestoService;
use Codeception\Test\Unit;

/**
 * Precio directo (sin cotizacion) en el paso Presupuesto: se guarda con su
 * fuente opcional, cambiar solo la fuente no vuelve a congelar el precio, y
 * el atajo SIGMA solo llena los items sin precio.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class PrecioDirectoTest extends Unit
{
    private ElaborarPresupuestoService $service;

    protected function _before(): void
    {
        $this->service = new ElaborarPresupuestoService();
    }

    public function testPrecioDirectoConFuente(): void
    {
        [$presupuesto, $item] = $this->crearConItem(SubcatalogoItem::find()->andWhere(['activo' => true])->one());

        $this->assertNull($this->service->elegir($presupuesto, $item->id, ElaborarPresupuestoService::DIRECTO, '12.50', '', '  compra 2025  '));

        $linea = $this->linea($presupuesto, $item);
        $this->assertTrue($linea->esPrecioDirecto());
        $this->assertSame(12.5, (float) $linea->precioUnitario);
        $this->assertSame('compra 2025', $linea->fuentePrecio);
    }

    public function testCambiarSoloLaFuenteNoReemplazaLaLinea(): void
    {
        [$presupuesto, $item] = $this->crearConItem(SubcatalogoItem::find()->andWhere(['activo' => true])->one());
        $this->service->elegir($presupuesto, $item->id, ElaborarPresupuestoService::DIRECTO, '10', '', 'web');
        $antes = $this->linea($presupuesto, $item);

        $this->assertNull($this->service->elegir($presupuesto, $item->id, ElaborarPresupuestoService::DIRECTO, '10', '', ''));

        $despues = $this->linea($presupuesto, $item);
        $this->assertSame((int) $antes->id, (int) $despues->id);
        $this->assertNull($despues->fuentePrecio);
    }

    public function testPrecioDirectoInvalido(): void
    {
        [$presupuesto, $item] = $this->crearConItem(SubcatalogoItem::find()->andWhere(['activo' => true])->one());

        $this->assertNotNull($this->service->elegir($presupuesto, $item->id, ElaborarPresupuestoService::DIRECTO, '-1'));
        $this->assertNotNull($this->service->elegir($presupuesto, $item->id, ElaborarPresupuestoService::DIRECTO, ''));
        $this->assertNull($this->linea($presupuesto, $item));
    }

    public function testAtajoSigmaSoloLlenaLosItemsSinPrecio(): void
    {
        $conPrecioSigma = SubcatalogoItem::find()
            ->alias('s')
            ->innerJoinWith('catalogoSigma c')
            ->andWhere(['s.activo' => true])
            ->andWhere(['>', 'c.PrecioReferencial', 0])
            ->limit(2)
            ->all();
        if (count($conPrecioSigma) < 2) {
            $this->markTestSkipped('La base local no tiene 2 ítems con precio referencial en SIGMA.');
        }

        [$presupuesto, $conSigma] = $this->crearConItem($conPrecioSigma[0]);
        $yaConPrecio = $this->agregarItem($presupuesto, $conPrecioSigma[1]);
        $this->service->elegir($presupuesto, $yaConPrecio->id, ElaborarPresupuestoService::DIRECTO, '1.00');

        $aplicados = 0;
        $this->assertNull($this->service->usarPrecioSigma($presupuesto, $aplicados));
        $this->assertSame(1, $aplicados);

        $linea = $this->linea($presupuesto, $conSigma);
        $this->assertSame((float) $conPrecioSigma[0]->catalogoSigma->PrecioReferencial, (float) $linea->precioUnitario);
        $this->assertSame(PresupuestoDetalle::FUENTE_SIGMA, $linea->fuentePrecio);
        // El que ya tenia precio no cambio.
        $this->assertSame(1.0, (float) $this->linea($presupuesto, $yaConPrecio)->precioUnitario);
    }

    public function testFueraDeBorradorNoSeCambia(): void
    {
        [$presupuesto, $item] = $this->crearConItem(SubcatalogoItem::find()->andWhere(['activo' => true])->one());
        $presupuesto->updateAttributes(['estado' => Presupuesto::ESTADO_EN_REVISION]);

        $this->assertNotNull($this->service->elegir($presupuesto, $item->id, ElaborarPresupuestoService::DIRECTO, '5'));
        $this->assertNotNull($this->service->usarPrecioSigma($presupuesto));
    }

    /**
     * @return array{0: Presupuesto, 1: ProyectoItem}
     */
    private function crearConItem(?SubcatalogoItem $itemSubcatalogo): array
    {
        if ($itemSubcatalogo === null) {
            $this->markTestSkipped('La base local no tiene ítems activos en el subcatálogo.');
        }

        $proyecto = new Proyecto(['nombre' => 'Proyecto test precio directo', 'estadoLista' => Proyecto::LISTA_EN_COTIZACION]);
        $proyecto->save(false);
        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => Presupuesto::ESTADO_EN_PROCESO]);
        $presupuesto->save(false);

        return [$presupuesto, $this->agregarItem($presupuesto, $itemSubcatalogo)];
    }

    private function agregarItem(Presupuesto $presupuesto, SubcatalogoItem $itemSubcatalogo): ProyectoItem
    {
        $item = new ProyectoItem([
            'idProyecto' => $presupuesto->idProyecto,
            'idSubcatalogo' => $itemSubcatalogo->id,
            'idCategoria' => $itemSubcatalogo->idCategoria,
            'cantidad' => 2,
            'orden' => 1,
        ]);
        $item->save(false);

        return $item;
    }

    private function linea(Presupuesto $presupuesto, ProyectoItem $item): ?PresupuestoDetalle
    {
        return PresupuestoDetalle::findOne(['idPresupuesto' => $presupuesto->id, 'idProyectoItem' => $item->id]);
    }
}
