<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Categoria;
use backend\models\Cotizacion;
use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\Plantilla;
use backend\models\Proveedor;
use backend\models\Proyecto;
use backend\services\CategoriaService;
use backend\services\PlantillaService;
use backend\services\ProveedorService;
use backend\services\ProyectoService;
use Codeception\Test\Unit;

/**
 * Hallazgo 9 de la auditoria de code smells: categoria, plantilla, proveedor
 * y proyecto cuentan sus usos antes de borrar y devuelven el motivo exacto
 * (como los demas catalogos), en vez de un mensaje fijo que adivina.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class EliminarEnUsoTest extends Unit
{
    public function testCategoriaUsadaSoloPorUnaEspecificacion(): void
    {
        $categoria = $this->categoria();
        (new Especificacion(['titulo' => 'Ficha test', 'idCategoria' => $categoria->id, 'activo' => true]))->save(false);

        $motivo = (new CategoriaService())->eliminar($categoria);

        $this->assertNotNull($motivo);
        $this->assertStringContainsString('1 especificación(es)', $motivo);
        $this->assertStringNotContainsString('subcatálogo', $motivo);
        $this->assertNotNull(Categoria::findOne($categoria->id));
    }

    public function testCategoriaSinUsoSeElimina(): void
    {
        $categoria = $this->categoria();

        $this->assertNull((new CategoriaService())->eliminar($categoria));
        $this->assertNull(Categoria::findOne($categoria->id));
    }

    public function testPlantillaUsadaPorUnProyecto(): void
    {
        $plantilla = new Plantilla(['nombre' => 'Plantilla test eliminar', 'activo' => true]);
        $plantilla->save(false);
        (new Proyecto(['nombre' => 'Proyecto test plantilla', 'idPlantillaOrigen' => $plantilla->id]))->save(false);

        $motivo = (new PlantillaService())->eliminar($plantilla);

        $this->assertStringContainsString('1 proyecto(s)', (string) $motivo);
        $this->assertNotNull(Plantilla::findOne($plantilla->id));
    }

    public function testProveedorConCotizaciones(): void
    {
        $proveedor = new Proveedor(['razonSocial' => 'Proveedor test eliminar']);
        $proveedor->save(false);
        (new Cotizacion(['idProveedor' => $proveedor->id, 'fecha' => '2026-10-31']))->save(false);

        $motivo = (new ProveedorService())->eliminar($proveedor);

        $this->assertStringContainsString('1 cotización(es)', (string) $motivo);
        $this->assertNotNull(Proveedor::findOne($proveedor->id));
    }

    public function testProyectoConEspecificacionEmitidaPeroSinPresupuesto(): void
    {
        $existente = EspecificacionEmision::find()->one();
        if ($existente === null) {
            $this->markTestSkipped('La base local no tiene especificaciones emitidas para copiar.');
        }
        $proyecto = new Proyecto(['nombre' => 'Proyecto test ET']);
        $proyecto->save(false);
        $emision = new EspecificacionEmision($existente->getAttributes(null, ['id', 'idProyectoItem', 'reemplazadaPor']));
        $emision->idProyecto = $proyecto->id;
        $emision->numeroNet = (int) EspecificacionEmision::find()->max('numeroNet') + 1000;
        $emision->save(false);

        $motivo = (new ProyectoService())->eliminar(Proyecto::findOne($proyecto->id));

        $this->assertNotNull($motivo);
        $this->assertStringContainsString('1 especificación(es) emitida(s)', $motivo);
        $this->assertStringNotContainsString('presupuesto', $motivo);
        $this->assertNotNull(Proyecto::findOne($proyecto->id));
    }

    private function categoria(): Categoria
    {
        $categoria = new Categoria(['nombre' => 'Categoría test eliminar', 'orden' => 999]);
        $categoria->save(false);

        return $categoria;
    }
}
