<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Cotizacion;
use backend\models\CotizacionDetalle;
use backend\models\Proveedor;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SolicitudCotizacion;
use backend\models\SolicitudCotizacionItem;
use backend\models\SubcatalogoItem;
use backend\services\CotizacionService;
use Codeception\Test\Unit;
use Throwable;
use RuntimeException;
use Yii;
use yii\base\Event;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * Hallazgo 15 de la auditoria de code smells: el PDF de una cotizacion se
 * escribe AL FINAL, dentro de la transaccion: si la base falla, no queda un
 * archivo huerfano en backend/uploads/cotizaciones.
 *
 * La base se deshace sola al terminar cada test; el disco no: _after() borra
 * los archivos que el test haya dejado.
 */
class CotizacionArchivoTest extends Unit
{
    /**
     * Un precio normal: la falla de la base se SIMULA al guardar la linea (ver
     * assertFallaSinArchivo()). Hasta el 2026-11-12 se usaba un precio que no
     * cabia en decimal(10,2), pero desde la auditoria (hallazgo 3) la
     * validacion lo detiene antes de llegar a la base.
     */
    private const PRECIO = '20';

    private CotizacionService $service;
    private Proveedor $proveedor;
    private SubcatalogoItem $item;
    /** @var string[] archivos que habia antes del test */
    private array $antes;

    protected function _before(): void
    {
        $proveedor = Proveedor::find()->one();
        $item = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        if ($proveedor === null || $item === null) {
            $this->markTestSkipped('La base local no tiene un proveedor y un ítem activo.');
        }
        $this->proveedor = $proveedor;
        $this->item = $item;
        $this->service = new CotizacionService();
        $this->antes = $this->archivos();
    }

    protected function _after(): void
    {
        foreach (array_diff($this->archivos(), $this->antes ?? []) as $archivo) {
            @unlink($archivo);
        }
    }

    public function testActualizarQueFallaNoDejaArchivo(): void
    {
        $cotizacion = new Cotizacion(['idProveedor' => $this->proveedor->id, 'fecha' => '2026-10-31']);
        $cotizacion->save(false);
        $detalle = new CotizacionDetalle(['idCotizacion' => $cotizacion->id, 'idSubcatalogo' => $this->item->id, 'cantidad' => 1, 'precioUnitario' => 10]);
        $detalle->save(false);
        $cotizacion = Cotizacion::findOne($cotizacion->id);
        $detalle = CotizacionDetalle::findOne($detalle->id);
        $detalle->precioUnitario = self::PRECIO;

        $this->assertFallaSinArchivo(fn () => $this->service->actualizar($cotizacion, [$detalle], ['Cotizacion' => ['idProveedor' => (string) $this->proveedor->id]], $this->pdf()));
    }

    public function testDesdeSolicitudQueFallaNoDejaArchivo(): void
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test PDF cotización', 'estadoLista' => Proyecto::LISTA_EN_COTIZACION]);
        $proyecto->save(false);
        $itemLista = new ProyectoItem(['idProyecto' => $proyecto->id, 'idSubcatalogo' => $this->item->id, 'idCategoria' => $this->item->idCategoria, 'cantidad' => 1, 'orden' => 1]);
        $itemLista->save(false);
        $solicitud = new SolicitudCotizacion(['idProyecto' => $proyecto->id, 'numero' => 1, 'nombre' => 'Ferretería']);
        $solicitud->save(false);
        (new SolicitudCotizacionItem(['idSolicitud' => $solicitud->id, 'idProyectoItem' => $itemLista->id]))->save(false);
        $solicitud = SolicitudCotizacion::findOne($solicitud->id);

        $this->assertFallaSinArchivo(function () use ($solicitud, $itemLista) {
            $filas = [];
            $errores = [];

            return $this->service->guardarDesdeSolicitud(
                $solicitud,
                $this->service->nuevaDesdeSolicitud($solicitud),
                ['Cotizacion' => ['idProveedor' => (string) $this->proveedor->id], 'filas' => [$itemLista->id => ['precioUnitario' => self::PRECIO, 'marcaModelo' => '']]],
                $this->pdf(),
                $filas,
                $errores
            );
        });
    }

    public function testGuardadoExitosoDejaArchivoYRuta(): void
    {
        $cotizacion = new Cotizacion();

        $this->assertTrue($this->service->crear($cotizacion, ['Cotizacion' => ['idProveedor' => (string) $this->proveedor->id, 'fecha' => '2026-10-31']], $this->pdf()), json_encode($cotizacion->errors));

        $this->assertNotEmpty($cotizacion->documentoRuta);
        $this->assertSame($cotizacion->documentoRuta, Cotizacion::findOne($cotizacion->id)->documentoRuta);
        $this->assertFileExists(Yii::getAlias('@backend') . '/' . $cotizacion->documentoRuta);
        $this->assertCount(1, array_diff($this->archivos(), $this->antes));
    }

    private function assertFallaSinArchivo(callable $guardar): void
    {
        // Falla de la base al guardar una linea, despues de validar todo (como un error de SQL Server).
        $falla = static function (): void {
            throw new RuntimeException('Falla simulada de la base al guardar la línea.');
        };
        Event::on(CotizacionDetalle::class, ActiveRecord::EVENT_BEFORE_INSERT, $falla);
        Event::on(CotizacionDetalle::class, ActiveRecord::EVENT_BEFORE_UPDATE, $falla);

        try {
            $guardar();
            $this->fail('El guardado debía fallar en la base.');
        } catch (Throwable $e) {
            $this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e, $e->getMessage());
        } finally {
            Event::off(CotizacionDetalle::class, ActiveRecord::EVENT_BEFORE_INSERT, $falla);
            Event::off(CotizacionDetalle::class, ActiveRecord::EVENT_BEFORE_UPDATE, $falla);
        }

        $this->assertSame([], array_values(array_diff($this->archivos(), $this->antes)), 'Quedó un PDF huérfano en uploads/cotizaciones.');
    }

    /**
     * Un PDF "subido" de mentira: Yii lo copia desde tempResource (sin
     * move_uploaded_file, que solo funciona con subidas reales).
     */
    private function pdf(): UploadedFile
    {
        $temporal = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($temporal, "%PDF-1.4\n% test\n");

        return new UploadedFile([
            'name' => 'cotizacion.pdf',
            'tempName' => $temporal,
            'type' => 'application/pdf',
            'size' => filesize($temporal),
            'error' => UPLOAD_ERR_OK,
            'tempResource' => fopen($temporal, 'rb'),
        ]);
    }

    /**
     * @return string[]
     */
    private function archivos(): array
    {
        return glob(Yii::getAlias('@backend') . '/uploads/cotizaciones/*') ?: [];
    }
}
