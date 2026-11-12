<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Cotizacion;
use backend\models\CotizacionDetalle;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proveedor;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use backend\services\ElaborarPresupuestoService;
use backend\services\PresupuestoService;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * Las reglas de DINERO y de ESTADOS del presupuesto:
 *  - al entrar al paso Presupuesto, cada item sin linea recibe el MENOR precio
 *    VIGENTE (si todos estan vencidos, el mas barato igual), y uno que ya tiene
 *    precio no se toca (ElaborarPresupuestoService::completarSinLinea()),
 *  - los atajos "menor precio por item" y "todo a un proveedor" (adjudicar()),
 *  - el resumen por partida suma lo mismo que el total por categorias,
 *  - que transiciones de estado se permiten y cuales no,
 *  - cuando vence una cotizacion (Cotizacion::estaVencida()).
 *
 * Usa la base local (proveedores e items del subcatalogo); el modulo Yii2
 * deshace cada test al terminar.
 */
class PresupuestoDineroTest extends Unit
{
    /** Una fecha de hace anos: con 10 dias de validez, la cotizacion esta vencida. */
    private const FECHA_VENCIDA = '2020-01-01';

    private ElaborarPresupuestoService $elaborar;
    private PresupuestoService $presupuestos;
    /** @var Proveedor[] dos proveedores de la base local */
    private array $proveedores;

    protected function _before(): void
    {
        $this->proveedores = Proveedor::find()->orderBy(['id' => SORT_ASC])->limit(2)->all();
        if (count($this->proveedores) < 2) {
            $this->markTestSkipped('La base local no tiene dos proveedores.');
        }
        $this->elaborar = new ElaborarPresupuestoService();
        $this->presupuestos = new PresupuestoService();
    }

    public function testAlEntrarCadaItemRecibeElMenorPrecioVigente(): void
    {
        [$presupuesto, $items] = $this->presupuestoConItems(4);
        [$conVigente, $todoVencido, $sinCotizar, $yaConPrecio] = $items;
        [$a, $b] = $this->proveedores;

        // El de 80 es mas barato, pero esta vencido: gana el de 100, el menor VIGENTE.
        $this->cotizar($conVigente, $a, '100');
        $this->cotizar($conVigente, $b, '80', self::FECHA_VENCIDA);
        // Todos vencidos: el mas barato igual ("es lo mejor que hay").
        $this->cotizar($todoVencido, $a, '60', self::FECHA_VENCIDA);
        $this->cotizar($todoVencido, $b, '50', self::FECHA_VENCIDA);
        // Ya tenia un precio directo: no se toca aunque haya una cotizacion mas barata.
        $this->cotizar($yaConPrecio, $a, '1');
        $this->assertNull($this->elaborar->elegir($presupuesto, $yaConPrecio->id, ElaborarPresupuestoService::DIRECTO, '999'));

        $creadas = $this->elaborar->completarSinLinea($presupuesto);

        $this->assertSame(2, $creadas);
        $this->assertSame(100.0, $this->precio($presupuesto, $conVigente));
        $this->assertSame(50.0, $this->precio($presupuesto, $todoVencido));
        $this->assertNull($this->linea($presupuesto, $sinCotizar), 'Sin cotizaciones no se inventa un precio.');
        $this->assertSame(999.0, $this->precio($presupuesto, $yaConPrecio));
    }

    public function testFueraDeBorradorNoCompletaNada(): void
    {
        [$presupuesto, [$item]] = $this->presupuestoConItems(1);
        $this->cotizar($item, $this->proveedores[0], '10');
        $presupuesto->updateAttributes(['estado' => Presupuesto::ESTADO_EN_REVISION]);

        $this->assertSame(0, $this->elaborar->completarSinLinea($presupuesto));
        $this->assertNull($this->linea($presupuesto, $item));
    }

    public function testAtajoMenorPrecioPorItem(): void
    {
        [$presupuesto, [$item]] = $this->presupuestoConItems(1);
        [$a, $b] = $this->proveedores;
        $caro = $this->cotizar($item, $a, '120');
        $this->cotizar($item, $b, '90');
        $this->assertNull($this->elaborar->elegir($presupuesto, $item->id, (string) $caro->id, '', 'mejor garantía'));

        $cambiadas = 0;
        $this->assertNull($this->elaborar->adjudicar($presupuesto, 'menor', null, '', $cambiadas));

        $this->assertSame(1, $cambiadas);
        $this->assertSame(90.0, $this->precio($presupuesto, $item));
        $this->assertNull($this->linea($presupuesto, $item)->justificacion, 'El menor precio no lleva justificación.');
    }

    public function testAtajoTodoAUnProveedor(): void
    {
        [$presupuesto, [$ambos, $soloA]] = $this->presupuestoConItems(2);
        [$a, $b] = $this->proveedores;
        $this->cotizar($ambos, $a, '70');
        $this->cotizar($ambos, $b, '75');
        $this->cotizar($soloA, $a, '30');
        $this->elaborar->completarSinLinea($presupuesto);

        $cambiadas = 0;
        $sinCotizar = 0;
        $this->assertNull($this->elaborar->adjudicar($presupuesto, 'proveedor', (int) $b->id, 'todo a un solo proveedor', $cambiadas, $sinCotizar));

        $this->assertSame(75.0, $this->precio($presupuesto, $ambos));
        $this->assertSame('todo a un solo proveedor', $this->linea($presupuesto, $ambos)->justificacion, 'No es el menor: lleva la justificación.');
        // El proveedor B no cotizo este item: se queda como estaba y se cuenta.
        $this->assertSame(30.0, $this->precio($presupuesto, $soloA));
        $this->assertSame(1, $cambiadas);
        $this->assertSame(1, $sinCotizar);
    }

    public function testAtajoConUnProveedorQueNoCotizoNada(): void
    {
        [$presupuesto, [$item]] = $this->presupuestoConItems(1);
        $this->cotizar($item, $this->proveedores[0], '10');

        $error = $this->elaborar->adjudicar($presupuesto, 'proveedor', (int) $this->proveedores[1]->id, '');

        $this->assertSame('Ese proveedor no cotizó ningún ítem de la lista.', $error);
        $this->assertNull($this->linea($presupuesto, $item));
    }

    public function testElResumenPorPartidaSumaLoMismoQueLasCategorias(): void
    {
        [$presupuesto, $items] = $this->presupuestoConItems(3, true);
        foreach ($items as $n => $item) {
            // Precios con centavos y cantidades distintas, para que se note un error de redondeo.
            ProyectoItem::updateAll(['cantidad' => 3 + $n], ['id' => $item->id]);
            $this->cotizar(ProyectoItem::findOne($item->id), $this->proveedores[0], (string) (10.33 + $n * 7.17));
        }
        $this->elaborar->completarSinLinea($presupuesto);

        $porCategoria = $this->presupuestos->totalGeneral($this->presupuestos->agruparPorCategoria($presupuesto));
        $porPartida = array_sum(array_column($this->presupuestos->resumenPorPartida($presupuesto), 'subtotal'));
        $lineas = (float) PresupuestoDetalle::find()->andWhere(['idPresupuesto' => $presupuesto->id])->sum('precioTotal');

        $this->assertGreaterThan(0, $lineas);
        $this->assertEqualsWithDelta($lineas, $porCategoria, 0.001);
        $this->assertEqualsWithDelta($lineas, $porPartida, 0.001);
        $this->assertGreaterThan(1, count($this->presupuestos->resumenPorPartida($presupuesto)), 'Los ítems tienen partidas distintas.');
    }

    public function testTransicionesDeEstado(): void
    {
        $permitidas = [
            [Presupuesto::ESTADO_EN_PROCESO, Presupuesto::ESTADO_EN_REVISION],
            [Presupuesto::ESTADO_EN_REVISION, Presupuesto::ESTADO_APROBADO],
            [Presupuesto::ESTADO_EN_REVISION, Presupuesto::ESTADO_OBSERVADO],
            [Presupuesto::ESTADO_EN_REVISION, Presupuesto::ESTADO_CANCELADO],
            [Presupuesto::ESTADO_OBSERVADO, Presupuesto::ESTADO_EN_PROCESO],
        ];
        $prohibidas = [
            // No se aprueba sin pasar por revision.
            [Presupuesto::ESTADO_EN_PROCESO, Presupuesto::ESTADO_APROBADO],
            [Presupuesto::ESTADO_OBSERVADO, Presupuesto::ESTADO_APROBADO],
            // Aprobado y cancelado son finales: un cambio va como fase u orden de cambio.
            [Presupuesto::ESTADO_APROBADO, Presupuesto::ESTADO_EN_PROCESO],
            [Presupuesto::ESTADO_APROBADO, Presupuesto::ESTADO_CANCELADO],
            [Presupuesto::ESTADO_CANCELADO, Presupuesto::ESTADO_EN_PROCESO],
        ];

        foreach ($permitidas as [$desde, $hacia]) {
            $this->assertTrue($this->presupuestos->puedeTransicionarA(new Presupuesto(['estado' => $desde]), $hacia), "$desde → $hacia");
        }
        foreach ($prohibidas as [$desde, $hacia]) {
            $this->assertFalse($this->presupuestos->puedeTransicionarA(new Presupuesto(['estado' => $desde]), $hacia), "$desde → $hacia");
        }
    }

    public function testAprobadoNoCambiaDeEstado(): void
    {
        [$presupuesto] = $this->presupuestoConItems(1);
        $presupuesto->updateAttributes(['estado' => Presupuesto::ESTADO_APROBADO]);

        $this->assertFalse($this->presupuestos->cambiarEstado($presupuesto, Presupuesto::ESTADO_EN_PROCESO));
        $this->assertSame(Presupuesto::ESTADO_APROBADO, Presupuesto::findOne($presupuesto->id)->estado);
    }

    public function testCuandoVenceUnaCotizacion(): void
    {
        // Validez de 30 dias desde el 01-09: vale hasta el 01-10 inclusive.
        $cotizacion = new Cotizacion(['fecha' => '2026-09-01', 'validezDias' => 30]);

        $this->assertFalse($cotizacion->estaVencida(new DateTimeImmutable('2026-10-01')));
        $this->assertTrue($cotizacion->estaVencida(new DateTimeImmutable('2026-10-02')));
        // Sin fecha o sin validez no se puede saber: no se considera vencida.
        $this->assertFalse((new Cotizacion(['fecha' => '2026-09-01']))->estaVencida(new DateTimeImmutable('2030-01-01')));
        $this->assertFalse((new Cotizacion(['validezDias' => 1]))->estaVencida(new DateTimeImmutable('2030-01-01')));
    }

    /**
     * Un proyecto con su lista en cotizacion y el presupuesto en borrador.
     *
     * @param bool $partidasDistintas items con partidas distintas (para el resumen por partida)
     * @return array{0: Presupuesto, 1: ProyectoItem[]}
     */
    private function presupuestoConItems(int $cuantos, bool $partidasDistintas = false): array
    {
        $subcatalogo = $this->itemsDelSubcatalogo($cuantos, $partidasDistintas);

        $proyecto = new Proyecto(['nombre' => 'Proyecto test dinero', 'estadoLista' => Proyecto::LISTA_EN_COTIZACION]);
        $proyecto->save(false);
        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => Presupuesto::ESTADO_EN_PROCESO, 'numeroPresupuesto' => 'RD-PI-PR-TEST']);
        $presupuesto->save(false);

        $items = [];
        foreach ($subcatalogo as $n => $itemSubcatalogo) {
            $item = new ProyectoItem([
                'idProyecto' => $proyecto->id,
                'idSubcatalogo' => $itemSubcatalogo->id,
                'idCategoria' => $itemSubcatalogo->idCategoria,
                'cantidad' => 2,
                'orden' => $n + 1,
            ]);
            $item->save(false);
            $items[] = ProyectoItem::findOne($item->id);
        }

        return [Presupuesto::findOne($presupuesto->id), $items];
    }

    /**
     * @return SubcatalogoItem[]
     */
    private function itemsDelSubcatalogo(int $cuantos, bool $partidasDistintas): array
    {
        $activos = SubcatalogoItem::find()->andWhere(['activo' => true])->orderBy(['id' => SORT_ASC])->all();
        $elegidos = [];
        $partidas = [];

        foreach ($activos as $item) {
            $partida = trim((string) $item->partida);
            if ($partidasDistintas && ($partida === '' || isset($partidas[$partida]))) {
                continue;
            }
            $partidas[$partida] = true;
            $elegidos[] = $item;
            if (count($elegidos) === $cuantos) {
                return $elegidos;
            }
        }

        $this->markTestSkipped("La base local no tiene $cuantos ítems activos" . ($partidasDistintas ? ' con partidas distintas.' : '.'));
    }

    /**
     * Una cotizacion del proveedor para ese item de la lista (validez de 10 dias).
     */
    private function cotizar(ProyectoItem $item, Proveedor $proveedor, string $precio, ?string $fecha = null): CotizacionDetalle
    {
        $cotizacion = new Cotizacion(['idProveedor' => $proveedor->id, 'fecha' => $fecha ?? date('Y-m-d'), 'validezDias' => 10]);
        $cotizacion->save(false);
        $detalle = new CotizacionDetalle([
            'idCotizacion' => $cotizacion->id,
            'idProyectoItem' => $item->id,
            'idSubcatalogo' => $item->idSubcatalogo,
            'cantidad' => $item->cantidad,
            'precioUnitario' => $precio,
        ]);
        $detalle->save(false);

        return $detalle;
    }

    private function linea(Presupuesto $presupuesto, ProyectoItem $item): ?PresupuestoDetalle
    {
        return PresupuestoDetalle::findOne(['idPresupuesto' => $presupuesto->id, 'idProyectoItem' => $item->id]);
    }

    private function precio(Presupuesto $presupuesto, ProyectoItem $item): ?float
    {
        $linea = $this->linea($presupuesto, $item);

        return $linea === null ? null : (float) $linea->precioUnitario;
    }
}
