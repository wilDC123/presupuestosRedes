<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Categoria;
use backend\models\CorrelativoDocumento;
use backend\models\Cotizacion;
use backend\models\CotizacionDetalle;
use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\HistorialProyecto;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proveedor;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use backend\services\EspecificacionService;
use backend\services\ListaItemsService;
use Codeception\Test\Unit;

/**
 * "Cambiar ítem" en la lista (2026-11-04): otro item del subcatalogo en la
 * MISMA fila. Corrige un error (ej. el cable en metros en vez de cajas) sin
 * anular el formulario de especificacion: se reemite con el mismo codigo y
 * la version siguiente. No se permite si la fila ya tiene cotizaciones.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class CambiarItemListaTest extends Unit
{
    private ListaItemsService $lista;
    private Proyecto $proyecto;
    private SubcatalogoItem $equivocado;
    private SubcatalogoItem $correcto;

    protected function _before(): void
    {
        $this->lista = new ListaItemsService();

        $items = SubcatalogoItem::find()->andWhere(['activo' => true])->orderBy(['id' => SORT_ASC])->limit(2)->all();
        if (count($items) < 2 || Categoria::find()->count() == 0) {
            $this->markTestSkipped('La base local necesita dos ítems activos en el subcatálogo.');
        }
        [$this->equivocado, $this->correcto] = $items;

        $this->proyecto = new Proyecto(['nombre' => 'Proyecto test cambiar ítem', 'unidadDependencia' => 'Carrera de Trabajo Social', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $this->proyecto->save(false);
        $this->proyecto->refresh();

        // Correlativo conocido, por encima de cualquier emision de la base local.
        CorrelativoDocumento::updateAll(
            ['siguiente' => (int) EspecificacionEmision::find()->max('numeroNet') + 1000, 'anio' => (int) date('y')],
            ['tipo' => CorrelativoDocumento::TIPO_ESPECIFICACION]
        );
    }

    public function testCambiarElItemReemiteElMismoCodigoConLaVersionSiguiente(): void
    {
        $ficha = $this->ficha();
        $this->guardar([$this->fila(null, $this->equivocado, $ficha)]);
        $item = $this->unicoItem();
        $anterior = $item->emisionVigente;
        $siguiente = $this->siguienteEt();

        $this->guardar([$this->fila($item, $this->correcto, $ficha)]);

        $item = $this->unicoItem();
        $this->assertSame((int) $this->correcto->id, (int) $item->idSubcatalogo);
        $this->assertSame((int) $this->correcto->idCategoria, (int) $item->idCategoria);
        $this->assertEquals($this->correcto->abreviaturaUnidad(), $item->unidadMedida);

        // El mismo documento, version 02, con el nombre y la unidad del item nuevo;
        // no se gasta un numero del correlativo.
        $nueva = $item->emisionVigente;
        $this->assertSame($anterior->codigo(), $nueva->codigo());
        $this->assertSame(2, (int) $nueva->versionDocumento);
        $this->assertSame($this->correcto->descripcion, $nueva->titulo);
        $this->assertEquals($this->correcto->abreviaturaUnidad(), $nueva->unidad);
        $this->assertSame($siguiente, $this->siguienteEt());

        $anterior->refresh();
        $this->assertSame(EspecificacionEmision::ESTADO_REEMPLAZADA, $anterior->estado);
        $this->assertSame((int) $nueva->id, (int) $anterior->idReemplazadaPor);
        $this->assertStringContainsString('Se cambió el ítem: ' . $this->equivocado->descripcion, $anterior->motivo);

        $historial = $this->historial();
        $this->assertNotNull($historial);
        $this->assertStringContainsString($this->equivocado->descripcion, $historial->detalle);
        $this->assertStringContainsString($this->correcto->descripcion, $historial->detalle);
        $this->assertStringContainsString($nueva->codigoConVersion(), $historial->detalle);
    }

    public function testConCotizacionesNoSeCambia(): void
    {
        $proveedor = Proveedor::find()->one();
        if ($proveedor === null) {
            $this->markTestSkipped('La base local no tiene proveedores.');
        }

        $this->guardar([$this->fila(null, $this->equivocado, null)]);
        $item = $this->unicoItem();
        $cotizacion = new Cotizacion(['idProveedor' => $proveedor->id, 'fecha' => date('Y-m-d'), 'validezDias' => 30]);
        $cotizacion->save(false);
        (new CotizacionDetalle([
            'idCotizacion' => $cotizacion->id,
            'idSubcatalogo' => $item->idSubcatalogo,
            'idProyectoItem' => $item->id,
            'cantidad' => 1,
            'precioUnitario' => 10,
        ]))->save(false);

        $errores = $this->guardar([$this->fila($item, $this->correcto, null)], false);

        $this->assertStringContainsString('ya tiene cotizaciones', implode(' ', $errores));
        $this->assertSame((int) $this->equivocado->id, (int) $this->unicoItem()->idSubcatalogo);
    }

    public function testConPresupuestoEnBorradorSeBorraElPrecioDeLaLinea(): void
    {
        $this->guardar([$this->fila(null, $this->equivocado, null)]);
        $item = $this->unicoItem();
        $presupuesto = new Presupuesto(['idProyecto' => $this->proyecto->id, 'estado' => Presupuesto::ESTADO_EN_PROCESO]);
        $presupuesto->save(false);
        (new PresupuestoDetalle([
            'idPresupuesto' => $presupuesto->id,
            'idSubcatalogo' => $item->idSubcatalogo,
            'idProyectoItem' => $item->id,
            'cantidad' => 5,
            'precioUnitario' => 2550,
            'fuentePrecio' => 'Presupuesto 649',
        ]))->save(false);
        $this->proyecto->refresh();

        $this->guardar([$this->fila($item, $this->correcto, null)]);

        // El precio era del item anterior: la linea se borra y el item queda sin precio.
        $this->assertFalse(PresupuestoDetalle::find()->andWhere(['idProyectoItem' => $item->id])->exists());
        $this->assertSame((int) $this->correcto->id, (int) $this->unicoItem()->idSubcatalogo);
    }

    public function testCambiarItemYFichaALaVezEsOtroDocumento(): void
    {
        $fichaA = $this->ficha();
        $fichaB = $this->ficha();
        $this->guardar([$this->fila(null, $this->equivocado, $fichaA)]);
        $item = $this->unicoItem();
        $anterior = $item->emisionVigente;

        $this->guardar([$this->fila($item, $this->correcto, $fichaB)]);

        $anterior->refresh();
        $this->assertSame(EspecificacionEmision::ESTADO_ANULADA, $anterior->estado);
        $nueva = $this->unicoItem()->emisionVigente;
        $this->assertSame((int) $anterior->numeroNet + 1, (int) $nueva->numeroNet);
        $this->assertSame(1, (int) $nueva->versionDocumento);
    }

    public function testSinEspecificacionSoloCambiaElItem(): void
    {
        $this->guardar([$this->fila(null, $this->equivocado, null)]);
        $item = $this->unicoItem();

        $this->guardar([$this->fila($item, $this->correcto, null)]);

        $this->assertSame((int) $this->correcto->id, (int) $this->unicoItem()->idSubcatalogo);
        $this->assertSame(0, (int) EspecificacionEmision::find()->andWhere(['idProyecto' => $this->proyecto->id])->count());
        $this->assertStringNotContainsString('formulario', (string) $this->historial()?->detalle);
    }

    public function testVolverAGuardarSinCambiarNoAnotaNada(): void
    {
        $this->guardar([$this->fila(null, $this->equivocado, null)]);

        $this->guardar([$this->fila($this->unicoItem(), $this->equivocado, null)]);

        $this->assertNull($this->historial());
    }

    private function ficha(): Especificacion
    {
        $ficha = new Especificacion(['titulo' => 'Cable test cambiar ítem', 'activo' => true]);
        $ficha->save(false);
        (new EspecificacionService())->guardarVersion($ficha, [], [['nombre' => 'Calibre', 'valor' => '23 AWG']]);

        return $ficha;
    }

    private function fila(?ProyectoItem $item, SubcatalogoItem $subcatalogo, ?Especificacion $ficha): array
    {
        return [
            'id' => $item === null ? '' : (string) $item->id,
            'idSubcatalogo' => (string) $subcatalogo->id,
            'cantidad' => '9',
            'observacion' => '',
            'idEspecificacion' => $ficha === null ? '' : (string) $ficha->id,
        ];
    }

    /**
     * @return string[] errores (vacio si se guardo)
     */
    private function guardar(array $filas, bool $debeGuardar = true): array
    {
        $errores = [];
        $enviados = [];
        $guardado = $this->lista->guardar($this->proyecto, $filas, $errores, $enviados, 'encargado');
        $this->assertSame($debeGuardar, $guardado, implode(' ', $errores));

        return $errores;
    }

    private function unicoItem(): ProyectoItem
    {
        /** @var ProyectoItem[] $items */
        $items = $this->proyecto->getItems()->all();
        $this->assertCount(1, $items);

        return $items[0];
    }

    private function historial(): ?HistorialProyecto
    {
        return HistorialProyecto::findOne(['idProyecto' => $this->proyecto->id, 'accion' => HistorialProyecto::ACCION_LISTA_ITEM_CAMBIADO]);
    }

    private function siguienteEt(): int
    {
        return (int) CorrelativoDocumento::findOne(CorrelativoDocumento::TIPO_ESPECIFICACION)->siguiente;
    }
}
