<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Especificacion;
use backend\services\EspecificacionImportacionService;
use backend\tests\Support\DocxDePrueba;
use Codeception\Test\Unit;
use yii\helpers\FileHelper;

/**
 * Carga inicial desde el banco de especificaciones: familias, version mas
 * reciente, descartes, filtro por año, titulos repetidos y que volver a
 * correrla no duplique nada.
 *
 * Arma una carpeta de .docx de prueba; la base se deshace al terminar cada test.
 */
class EspecificacionImportacionTest extends Unit
{
    private string $carpeta;
    private EspecificacionImportacionService $servicio;

    protected function _before(): void
    {
        $this->servicio = new EspecificacionImportacionService();
        $this->carpeta = sys_get_temp_dir() . '/banco-test-' . uniqid();
        FileHelper::createDirectory($this->carpeta);

        // La misma ficha emitida en 2023 y en 2026: se usa la de 2026.
        $this->documento('013_23 Cable de red.docx', 'Cable de red Categoría 6 (test)', 'NET:013/23', [['Calibre: 24 AWG']]);
        $this->documento('880 Cable CAT6.docx', 'CABLE DE RED CATEGORIA 6 (TEST)', 'NET:880/26', [
            ['Calibre: 23 AWG'],
            ['Dimensiones: diámetro de $6.3 \\text{ mm} \\pm 0.3 \\text{ mm}$'],
        ], ['Sugerencia: Nexxt PCGUCC6 o similar/superior.']);
        // Mismo titulo, distinto modelo: dos fichas, con el modelo en el titulo.
        $this->documento('500 Antena.docx', 'Antena wifi (test)', 'NET:500/25', [['Bandas: 2.4 y 5 GHz']], ['Se sugiere EAP225, o con superiores características.']);
        $this->documento('501 Antena.docx', 'Antena wifi (test)', 'NET:501/25', [['Bandas: 2.4, 5 y 6 GHz']], ['Se sugiere U6 PRO similar o superiores características']);
        // Producto que no se pide desde 2023.
        $this->documento('100_23 Toner.docx', 'Toner 80A (test)', 'NET:100/23', [['Rendimiento: 2700 páginas']]);
        // Marcado como no usado, y uno sin tabla.
        $this->documento('243 NO Camara BALA.docx', 'Cámara bala (test)', 'NET:243/24', [['Resolución: 4MP']]);
        DocxDePrueba::escribir($this->carpeta . '/firma.docx', ['Firma del responsable'], []);
    }

    protected function _after(): void
    {
        FileHelper::removeDirectory($this->carpeta);
    }

    public function testArmaElPlanPorFamilia(): void
    {
        $plan = $this->porArchivo($this->servicio->analizar($this->carpeta));

        $this->assertSame(EspecificacionImportacionService::ACCION_CREAR, $plan['880 Cable CAT6.docx']['accion']);
        $this->assertSame(EspecificacionImportacionService::ACCION_VERSION_ANTERIOR, $plan['013_23 Cable de red.docx']['accion']);
        $this->assertSame('880 Cable CAT6.docx', $plan['013_23 Cable de red.docx']['elegido']);
        $this->assertSame(2, $plan['880 Cable CAT6.docx']['archivosFamilia']);
        $this->assertSame(EspecificacionImportacionService::ACCION_MARCADO_NO, $plan['243 NO Camara BALA.docx']['accion']);
        $this->assertSame(EspecificacionImportacionService::ACCION_SIN_TABLA, $plan['firma.docx']['accion']);
        $this->assertSame(EspecificacionImportacionService::ACCION_CREAR, $plan['100_23 Toner.docx']['accion']);

        // Titulos repetidos: se distinguen por el modelo.
        $this->assertSame('Antena wifi (test) — EAP225', $plan['500 Antena.docx']['titulo']);
        $this->assertSame('Antena wifi (test) — U6 PRO', $plan['501 Antena.docx']['titulo']);

        // Restos de formulas de Word, limpios.
        $this->assertSame(
            ['nombre' => 'Dimensiones', 'valor' => 'diámetro de 6.3 mm ± 0.3 mm'],
            $plan['880 Cable CAT6.docx']['caracteristicas'][1]
        );
    }

    public function testDesdeUnAnioNoCreaLasFamiliasViejas(): void
    {
        $plan = $this->porArchivo($this->servicio->analizar($this->carpeta, 2025));

        $this->assertSame(EspecificacionImportacionService::ACCION_ANTIGUA, $plan['100_23 Toner.docx']['accion']);
        $this->assertSame(EspecificacionImportacionService::ACCION_CREAR, $plan['880 Cable CAT6.docx']['accion']);
    }

    public function testImportaYAlVolverACorrerNoDuplica(): void
    {
        $plan = $this->servicio->analizar($this->carpeta);

        $this->assertSame(4, $this->servicio->importar($plan));

        $cable = Especificacion::findOne(['titulo' => 'CABLE DE RED CATEGORIA 6 (TEST)']);
        $this->assertNotNull($cable);
        $version = $cable->getVersionActual()->one();
        $this->assertSame('Nexxt PCGUCC6', $version->modeloSugerido);
        $this->assertSame(2, (int) $version->getCaracteristicas()->count());
        $this->assertStringContainsString('880 Cable CAT6.docx (NET:880/26)', $version->notaCambio);

        $otraVez = $this->servicio->resumen($this->servicio->analizar($this->carpeta));
        $this->assertArrayNotHasKey(EspecificacionImportacionService::ACCION_CREAR, $otraVez);
        $this->assertSame(4, $otraVez[EspecificacionImportacionService::ACCION_YA_EXISTE]);
    }

    public function testCsvDelPlan(): void
    {
        $ruta = $this->carpeta . '/plan.csv';

        $this->servicio->escribirCsv($this->servicio->analizar($this->carpeta), $ruta);

        $csv = file_get_contents($ruta);
        $this->assertStringStartsWith("\xEF\xBB\xBF" . 'Acción;Archivo', $csv);
        $this->assertStringContainsString('"880 Cable CAT6.docx"', $csv);
    }

    /**
     * @param array<int, string[]> $filas
     * @param string[] $requisitosExtra parrafos de la celda de marca y modelo
     */
    private function documento(string $archivo, string $titulo, string $net, array $filas, array $requisitosExtra = []): void
    {
        DocxDePrueba::escribir($this->carpeta . '/' . $archivo, [$titulo . $net, 'Cantidad: 1'], array_merge(
            [['I. REQUISITOS NECESARIOS'], array_merge(['Especifique marca y modelo a ofertar:'], $requisitosExtra)],
            $filas,
            [['III CONDICIONES COMPLEMENTARIAS'], ['Validez de la oferta: 10 días']]
        ));
    }

    /**
     * @return array<string, array> el plan indexado por nombre de archivo
     */
    private function porArchivo(array $plan): array
    {
        return array_column($plan, null, 'archivo');
    }
}
