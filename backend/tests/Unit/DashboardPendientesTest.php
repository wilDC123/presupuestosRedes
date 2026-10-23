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
use backend\models\SolicitudCotizacion;
use backend\models\SubcatalogoItem;
use backend\services\DashboardService;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * Tarjeta "Que tengo pendiente" del panel general
 * (DashboardService::pendientes()): cada aviso aparece para un proyecto
 * abierto y desaparece cuando el presupuesto se aprueba o cancela.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar. Como la
 * base ya puede tener otros pendientes, cada test busca SU proyecto.
 */
class DashboardPendientesTest extends Unit
{
    private DashboardService $service;

    protected function _before(): void
    {
        $this->service = new DashboardService();
    }

    public function testPresupuestoObservadoApareceYAprobadoNo(): void
    {
        $observado = $this->crearPresupuesto(Presupuesto::ESTADO_OBSERVADO);
        $aprobado = $this->crearPresupuesto(Presupuesto::ESTADO_APROBADO);

        $this->assertNotNull($this->fila('Presupuestos observados', $observado->idProyecto));
        $this->assertNull($this->fila('Presupuestos observados', $aprobado->idProyecto));
    }

    public function testItemsSinPrecioSoloEnPresupuestoEnProceso(): void
    {
        $enProceso = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);
        $this->crearItem($enProceso->idProyecto);
        $this->crearItem($enProceso->idProyecto);
        $enRevision = $this->crearPresupuesto(Presupuesto::ESTADO_EN_REVISION);
        $this->crearItem($enRevision->idProyecto);

        $fila = $this->fila('Ítems sin precio', $enProceso->idProyecto);
        $this->assertNotNull($fila);
        $this->assertSame('2 ítems sin precio', $fila['detalle']);
        $this->assertNull($this->fila('Ítems sin precio', $enRevision->idProyecto));
    }

    public function testLineaConCotizacionVencida(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);
        $item = $this->crearItem($presupuesto->idProyecto);
        $proveedor = Proveedor::find()->one();
        if ($proveedor === null) {
            $this->markTestSkipped('La base local no tiene proveedores.');
        }

        // Validez de 10 dias desde el 01/09: vencida el 01/10.
        $cotizacion = new Cotizacion(['idProveedor' => $proveedor->id, 'fecha' => '2026-09-01', 'validezDias' => 10]);
        $cotizacion->save(false);
        $detalle = new CotizacionDetalle([
            'idCotizacion' => $cotizacion->id,
            'idSubcatalogo' => $item->idSubcatalogo,
            'idProyectoItem' => $item->id,
            'cantidad' => 1,
            'precioUnitario' => 10,
        ]);
        $detalle->save(false);
        (new PresupuestoDetalle([
            'idPresupuesto' => $presupuesto->id,
            'idSubcatalogo' => $item->idSubcatalogo,
            'idProyectoItem' => $item->id,
            'idCotizacionDetalle' => $detalle->id,
            'cantidad' => 1,
            'precioUnitario' => 10,
        ]))->save(false);

        $fila = $this->fila('Cotizaciones vencidas', $presupuesto->idProyecto, new DateTimeImmutable('2026-10-01'));
        $this->assertNotNull($fila);
        $this->assertSame('1 línea con cotización vencida', $fila['detalle']);
        $this->assertNull($this->fila('Cotizaciones vencidas', $presupuesto->idProyecto, new DateTimeImmutable('2026-09-05')));
        // Con precio, el item ya no cuenta como "sin precio".
        $this->assertNull($this->fila('Ítems sin precio', $presupuesto->idProyecto));
    }

    public function testSolicitudSinRespuestaHastaEnviarARevision(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);
        (new SolicitudCotizacion(['idProyecto' => $presupuesto->idProyecto, 'numero' => 1, 'nombre' => 'Ferretería', 'fecha' => '2026-10-20']))->save(false);

        $fila = $this->fila('Solicitudes sin respuesta', $presupuesto->idProyecto);
        $this->assertNotNull($fila);
        $this->assertSame('SC-01 Ferretería', $fila['detalle']);

        $presupuesto->estado = Presupuesto::ESTADO_EN_REVISION;
        $presupuesto->save(false);
        $this->assertNull($this->fila('Solicitudes sin respuesta', $presupuesto->idProyecto));
    }

    /**
     * Fila del grupo con ese titulo para el proyecto, o null si no aparece.
     */
    private function fila(string $titulo, int $idProyecto, ?DateTimeImmutable $hoy = null): ?array
    {
        foreach ($this->service->pendientes($hoy) as $grupo) {
            if ($grupo['titulo'] !== $titulo) {
                continue;
            }
            foreach ($grupo['filas'] as $fila) {
                if ((int) $fila['proyecto']->id === $idProyecto) {
                    return $fila;
                }
            }
        }

        return null;
    }

    private function crearPresupuesto(string $estado): Presupuesto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test pendientes', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);

        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => $estado, 'numeroPresupuesto' => 'TEST-' . $proyecto->id]);
        $presupuesto->save(false);

        return $presupuesto;
    }

    private function crearItem(int $idProyecto): ProyectoItem
    {
        $itemSubcatalogo = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        if ($itemSubcatalogo === null) {
            $this->markTestSkipped('La base local no tiene ítems activos en el subcatálogo.');
        }

        $item = new ProyectoItem([
            'idProyecto' => $idProyecto,
            'idSubcatalogo' => $itemSubcatalogo->id,
            'idCategoria' => $itemSubcatalogo->idCategoria,
            'cantidad' => 1,
            'orden' => 1,
        ]);
        $item->save(false);

        return $item;
    }
}
