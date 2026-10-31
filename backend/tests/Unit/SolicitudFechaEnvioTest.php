<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Cotizacion;
use backend\models\Proveedor;
use backend\models\Proyecto;
use backend\models\SolicitudCotizacion;
use backend\services\SolicitudCotizacionService;
use Codeception\Test\Unit;

/**
 * Fecha de envio de la solicitud de cotizacion: opcional, es la que sale en
 * el PDF (sin ella, la del dia en que se imprime) y queda fija cuando la
 * solicitud ya tiene cotizaciones.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class SolicitudFechaEnvioTest extends Unit
{
    private SolicitudCotizacionService $servicio;
    private SolicitudCotizacion $solicitud;

    protected function _before(): void
    {
        $this->servicio = new SolicitudCotizacionService();

        $proyecto = new Proyecto(['nombre' => 'Proyecto test fecha de envío', 'unidadSolicitante' => 'Facultad de Tecnología', 'estadoLista' => Proyecto::LISTA_EN_COTIZACION]);
        $proyecto->save(false);

        // "fecha" es la de creacion: no tiene que ver con el documento.
        $this->solicitud = new SolicitudCotizacion(['idProyecto' => $proyecto->id, 'numero' => 1, 'nombre' => 'Ferretería', 'fecha' => '2026-10-20']);
        $this->solicitud->save(false);
        $this->solicitud->refresh();
    }

    public function testSinFechaDeEnvioElDocumentoLlevaLaDeHoy(): void
    {
        $this->assertNull($this->solicitud->fechaEnvio);
        $this->assertSame(date('d/m/Y'), $this->solicitud->fechaDelDocumento());
    }

    public function testConFechaDeEnvioElDocumentoLlevaEsaFecha(): void
    {
        $this->assertTrue($this->actualizar('2026-10-23'));

        $this->solicitud->refresh();
        $this->assertSame('2026-10-23', $this->solicitud->fechaEnvio);
        $this->assertSame('23/10/2026', $this->solicitud->fechaDelDocumento());

        $html = (new \yii\web\View())->renderFile(\Yii::getAlias('@backend/views/solicitud-cotizacion/pdf.php'), [
            'solicitud' => $this->solicitud,
            'configuracion' => \backend\models\ConfiguracionSolicitudCotizacion::obtener(),
        ]);
        $this->assertStringContainsString('23/10/2026', $html);

        // Solicitud de proforma (2026-10-30, paso 10): lista sencilla y firma del proveedor, sin precios.
        $this->assertStringContainsString('SOLICITUD DE PROFORMA DE COTIZACIÓN', $html);
        $this->assertStringContainsString('Firma y sello del proveedor', $html);
        $this->assertStringContainsString('Nombre / empresa', $html);
        foreach (['Precio unitario', 'TOTAL Bs.', 'NIT', 'Validez de la oferta'] as $quitado) {
            $this->assertStringNotContainsString($quitado, $html);
        }
    }

    public function testDejarlaVaciaVuelveALaDelDiaDeImpresion(): void
    {
        $this->actualizar('2026-10-23');

        $this->assertTrue($this->actualizar(''));

        $this->solicitud->refresh();
        $this->assertNull($this->solicitud->fechaEnvio);
    }

    public function testTambienAceptaDiaMesAnio(): void
    {
        $this->assertTrue($this->actualizar('5/11/2026'));

        $this->solicitud->refresh();
        $this->assertSame('2026-11-05', $this->solicitud->fechaEnvio);
    }

    public function testUnaFechaInvalidaNoSeGuarda(): void
    {
        $this->assertFalse($this->actualizar('31/02/2026'));
        $this->assertNotEmpty($this->solicitud->getErrors('fechaEnvio'));
    }

    public function testConCotizacionesLaFechaYaNoCambia(): void
    {
        $this->actualizar('2026-10-23');
        $proveedor = Proveedor::find()->one();
        if ($proveedor === null) {
            $this->markTestSkipped('La base local no tiene proveedores.');
        }
        (new Cotizacion(['idProveedor' => $proveedor->id, 'idSolicitudCotizacion' => $this->solicitud->id, 'fecha' => '2026-10-25', 'validezDias' => 10]))->save(false);

        $this->assertFalse($this->actualizar('2026-10-30'));
        $this->assertStringContainsString('ya tiene cotizaciones', $this->solicitud->getFirstError('fechaEnvio'));

        // Otra peticion (el controlador vuelve a leer la solicitud). Sin tocar
        // la fecha (el campo llega deshabilitado), el nombre si se cambia.
        $this->solicitud->refresh();
        $this->assertTrue($this->servicio->actualizar($this->solicitud, ['SolicitudCotizacion' => ['nombre' => 'Ferretería y ducteo']]));
        $this->solicitud->refresh();
        $this->assertSame('2026-10-23', $this->solicitud->fechaEnvio);
        $this->assertSame('Ferretería y ducteo', $this->solicitud->nombre);
    }

    private function actualizar(string $fecha): bool
    {
        return $this->servicio->actualizar($this->solicitud, ['SolicitudCotizacion' => ['nombre' => $this->solicitud->nombre, 'fechaEnvio' => $fecha]]);
    }
}
