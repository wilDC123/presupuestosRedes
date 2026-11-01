<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SolicitudCotizacion;
use backend\models\SolicitudCotizacionItem;
use backend\models\SubcatalogoItem;
use backend\services\ListaItemsService;
use Codeception\Test\Unit;

/**
 * Lo que ListaItemsService::guardar() hace fuera de la lista (hallazgos 6 y 7
 * de la auditoria de code smells): mientras el presupuesto es un borrador sus
 * lineas siguen a la lista; con el presupuesto en revision, un item con linea
 * no se quita; un item quitado sale de su solicitud de cotizacion.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class ListaGuardarTest extends Unit
{
    private ListaItemsService $lista;
    private SubcatalogoItem $itemSubcatalogo;

    protected function _before(): void
    {
        $item = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        if ($item === null) {
            $this->markTestSkipped('La base local no tiene ítems activos en el subcatálogo.');
        }
        $this->itemSubcatalogo = $item;
        $this->lista = new ListaItemsService();
    }

    public function testEnBorradorQuitarUnItemBorraSuLinea(): void
    {
        [$proyecto, $queda, $sale] = $this->proyectoConDosLineas(Presupuesto::ESTADO_EN_PROCESO);

        $this->guardar($proyecto, [$this->fila($queda)]);

        $this->assertNull(ProyectoItem::findOne($sale->id));
        $this->assertNull(PresupuestoDetalle::findOne(['idProyectoItem' => $sale->id]));
        $this->assertNotNull(PresupuestoDetalle::findOne(['idProyectoItem' => $queda->id]));
    }

    public function testEnRevisionNoSeQuitaUnItemConLinea(): void
    {
        [$proyecto, $queda, $sale] = $this->proyectoConDosLineas(Presupuesto::ESTADO_EN_REVISION);

        $errores = $this->guardar($proyecto, [$this->fila($queda)], false);

        $this->assertStringContainsString('ya tiene una línea en el presupuesto', implode(' ', $errores));
        $this->assertNotNull(ProyectoItem::findOne($sale->id));
        $this->assertNotNull(PresupuestoDetalle::findOne(['idProyectoItem' => $sale->id]));
    }

    public function testEnBorradorLaLineaCopiaCantidadYObservacionPeroNoElPrecio(): void
    {
        [$proyecto, $item, $segundo] = $this->proyectoConDosLineas(Presupuesto::ESTADO_EN_PROCESO);
        [, $otro] = $this->proyectoConDosLineas(Presupuesto::ESTADO_EN_PROCESO);

        $this->guardar($proyecto, [$this->fila($item, '7', 'según plano'), $this->fila($segundo)]);

        $linea = PresupuestoDetalle::findOne(['idProyectoItem' => $item->id]);
        $this->assertSame(7.0, (float) $linea->cantidad);
        $this->assertSame('según plano', $linea->observacion);
        $this->assertSame(100.0, (float) $linea->precioUnitario);
        // La linea de otro proyecto no se toca.
        $this->assertSame(2.0, (float) PresupuestoDetalle::findOne(['idProyectoItem' => $otro->id])->cantidad);
    }

    public function testQuitarUnItemLoSacaDeSuSolicitud(): void
    {
        $proyecto = $this->proyecto();
        $queda = $this->item($proyecto, 1);
        $sale = $this->item($proyecto, 2);
        $solicitud = new SolicitudCotizacion(['idProyecto' => $proyecto->id, 'numero' => 1, 'nombre' => 'Ferretería']);
        $solicitud->save(false);
        foreach ([$queda, $sale] as $item) {
            (new SolicitudCotizacionItem(['idSolicitud' => $solicitud->id, 'idProyectoItem' => $item->id]))->save(false);
        }

        $this->guardar($proyecto, [$this->fila($queda)]);

        $this->assertNull(SolicitudCotizacionItem::findOne(['idProyectoItem' => $sale->id]));
        $this->assertNotNull(SolicitudCotizacionItem::findOne(['idProyectoItem' => $queda->id]));
    }

    /**
     * Proyecto en edicion con dos items, cada uno con su linea (2 x 100.00).
     *
     * @return array{0: Proyecto, 1: ProyectoItem, 2: ProyectoItem}
     */
    private function proyectoConDosLineas(string $estado): array
    {
        $proyecto = $this->proyecto();
        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => $estado]);
        $presupuesto->save(false);
        $items = [$this->item($proyecto, 1), $this->item($proyecto, 2)];

        foreach ($items as $item) {
            (new PresupuestoDetalle([
                'idPresupuesto' => $presupuesto->id,
                'idProyectoItem' => $item->id,
                'idSubcatalogo' => $item->idSubcatalogo,
                'cantidad' => 2,
                'precioUnitario' => 100,
            ]))->save(false);
        }

        return [Proyecto::findOne($proyecto->id), ...$items];
    }

    private function proyecto(): Proyecto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test guardar lista', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);

        return $proyecto;
    }

    private function item(Proyecto $proyecto, int $orden): ProyectoItem
    {
        $item = new ProyectoItem([
            'idProyecto' => $proyecto->id,
            'idSubcatalogo' => $this->itemSubcatalogo->id,
            'idCategoria' => $this->itemSubcatalogo->idCategoria,
            'cantidad' => 2,
            'orden' => $orden,
        ]);
        $item->save(false);

        return $item;
    }

    private function fila(ProyectoItem $item, string $cantidad = '2', string $observacion = ''): array
    {
        return ['id' => (string) $item->id, 'cantidad' => $cantidad, 'observacion' => $observacion, 'idEspecificacion' => ''];
    }

    /**
     * @return string[] errores
     */
    private function guardar(Proyecto $proyecto, array $filas, bool $debeGuardar = true): array
    {
        $errores = [];
        $enviados = [];
        $this->assertSame($debeGuardar, $this->lista->guardar(Proyecto::findOne($proyecto->id), $filas, $errores, $enviados), implode(' ', $errores));

        return $errores;
    }
}
