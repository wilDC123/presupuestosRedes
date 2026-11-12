<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Cotizacion;
use backend\models\Proveedor;
use backend\services\CotizacionService;
use Codeception\Test\Unit;
use Yii;
use yii\web\UploadedFile;

/**
 * La proforma de una cotizacion se adjunta como PDF O como ENLACE (Google
 * Drive), 2026-11-11: los dos van en documentoRuta; el enlace, tal cual.
 *
 * La base se deshace sola al terminar cada test; _after() borra los PDF que
 * el test haya escrito.
 */
class CotizacionEnlaceTest extends Unit
{
    private const DRIVE = 'https://drive.google.com/file/d/1AbCdEf/view?usp=sharing';

    private CotizacionService $service;
    private Proveedor $proveedor;
    /** @var string[] */
    private array $antes;

    protected function _before(): void
    {
        $proveedor = Proveedor::find()->one();
        if ($proveedor === null) {
            $this->markTestSkipped('La base local no tiene proveedores.');
        }
        $this->proveedor = $proveedor;
        $this->service = new CotizacionService();
        $this->antes = $this->archivos();
    }

    protected function _after(): void
    {
        foreach (array_diff($this->archivos(), $this->antes ?? []) as $archivo) {
            @unlink($archivo);
        }
    }

    public function testCrearConEnlaceLoGuardaSinEscribirArchivo(): void
    {
        $cotizacion = $this->crear(self::DRIVE);

        $leida = Cotizacion::findOne($cotizacion->id);
        $this->assertSame(self::DRIVE, $leida->documentoRuta);
        $this->assertTrue($leida->tieneEnlace());
        $this->assertSame(self::DRIVE, $leida->urlDocumento());
        $this->assertSame(self::DRIVE, $leida->documentoEnlace, 'Al leerla, el campo trae el enlace guardado.');
        $this->assertSame('Abrir enlace', $leida->textoDocumento());
        $this->assertSame('Enlace', $leida->textoDocumento(true));
        $this->assertSame('Abrir la proforma en el Drive', $leida->ayudaDocumento());
        $this->assertSame([], array_values(array_diff($this->archivos(), $this->antes)));
    }

    public function testEnlaceInvalidoNoSeGuarda(): void
    {
        $cotizacion = new Cotizacion();

        $this->assertFalse($this->service->crear($cotizacion, $this->datos('drive.google.com/archivo'), null));
        $this->assertArrayHasKey('documentoEnlace', $cotizacion->errors);
    }

    public function testEnlaceYArchivoALaVezNoSePermite(): void
    {
        $cotizacion = new Cotizacion();

        $this->assertFalse($this->service->crear($cotizacion, $this->datos(self::DRIVE), $this->pdf()));
        $this->assertArrayHasKey('documentoEnlace', $cotizacion->errors);
        $this->assertSame([], array_values(array_diff($this->archivos(), $this->antes)));
    }

    public function testBorrarElEnlaceLoQuita(): void
    {
        $cotizacion = Cotizacion::findOne($this->crear(self::DRIVE)->id);

        $this->assertTrue($this->service->actualizar($cotizacion, [], $this->datos(''), null), json_encode($cotizacion->errors));

        $this->assertNull(Cotizacion::findOne($cotizacion->id)->documentoRuta);
    }

    public function testUnPdfNuevoReemplazaElEnlaceGuardado(): void
    {
        // El campo viene lleno con el enlace guardado: subir un PDF lo reemplaza sin pedir que se borre.
        $cotizacion = Cotizacion::findOne($this->crear(self::DRIVE)->id);

        $this->assertTrue($this->service->actualizar($cotizacion, [], $this->datos(self::DRIVE), $this->pdf()), json_encode($cotizacion->errors));

        $leida = Cotizacion::findOne($cotizacion->id);
        $this->assertFalse($leida->tieneEnlace());
        $this->assertFileExists(Yii::getAlias('@backend') . '/' . $leida->documentoRuta);
        $this->assertNull($leida->documentoEnlace);
    }

    public function testSinEnlaceSeConservaElPdfSubido(): void
    {
        $cotizacion = new Cotizacion();
        $this->assertTrue($this->service->crear($cotizacion, $this->datos(''), $this->pdf()));
        $ruta = $cotizacion->documentoRuta;
        $cotizacion = Cotizacion::findOne($cotizacion->id);

        $this->assertTrue($this->service->actualizar($cotizacion, [], $this->datos(''), null), json_encode($cotizacion->errors));

        $this->assertSame($ruta, Cotizacion::findOne($cotizacion->id)->documentoRuta);
    }

    public function testDocumentoRutaNoLlegaDesdeElFormulario(): void
    {
        $cotizacion = new Cotizacion();
        $cotizacion->load(['Cotizacion' => ['idProveedor' => '1', 'documentoRuta' => '../../config/main-local.php']]);

        $this->assertNull($cotizacion->documentoRuta);
    }

    private function crear(string $enlace): Cotizacion
    {
        $cotizacion = new Cotizacion();
        $this->assertTrue($this->service->crear($cotizacion, $this->datos($enlace), null), json_encode($cotizacion->errors));

        return $cotizacion;
    }

    private function datos(string $enlace): array
    {
        return ['Cotizacion' => ['idProveedor' => (string) $this->proveedor->id, 'fecha' => '2026-11-11', 'documentoEnlace' => $enlace]];
    }

    private function pdf(): UploadedFile
    {
        $temporal = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($temporal, "%PDF-1.4\n% test\n");

        return new UploadedFile([
            'name' => 'proforma.pdf',
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
