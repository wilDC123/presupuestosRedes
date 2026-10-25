<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\components\ArchivoSubido;
use Codeception\Test\Unit;
use Yii;
use yii\helpers\FileHelper;

/**
 * Los archivos subidos solo se entregan si estan dentro de backend/uploads:
 * una ruta escrita a mano no puede sacar otros archivos del servidor.
 */
class ArchivoSubidoTest extends Unit
{
    private string $carpeta;
    private string $archivo;

    protected function _before(): void
    {
        $this->carpeta = Yii::getAlias('@backend/uploads/test-' . uniqid());
        FileHelper::createDirectory($this->carpeta);
        $this->archivo = $this->carpeta . '/documento.pdf';
        file_put_contents($this->archivo, '%PDF-prueba');
    }

    protected function _after(): void
    {
        FileHelper::removeDirectory($this->carpeta);
    }

    public function testEntregaUnArchivoDentroDeUploads(): void
    {
        $relativa = 'uploads/' . basename($this->carpeta) . '/documento.pdf';

        $this->assertSame(realpath($this->archivo), ArchivoSubido::rutaAbsoluta($relativa));
    }

    public function testRechazaRutasFueraDeUploads(): void
    {
        foreach ([
            '../common/config/main-local.php',
            'uploads/../../common/config/main-local.php',
            'config/main.php',
            '/etc/passwd',
            'uploads',
            'uploads/no-existe.pdf',
            '',
            null,
            'https://drive.google.com/archivo',
        ] as $ruta) {
            $this->assertNull(ArchivoSubido::rutaAbsoluta($ruta), (string) $ruta);
        }
    }

    public function testRutaNuevaQuedaDentroDeUploads(): void
    {
        $ruta = ArchivoSubido::rutaNueva('cotizaciones', 'cot_', 'PDF');

        $this->assertMatchesRegularExpression('#^uploads/cotizaciones/cot_[0-9a-f.]+\.pdf$#', $ruta);
    }
}
