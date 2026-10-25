<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\components\EspecificacionMetadataExtractor;
use backend\tests\Support\DocxDePrueba;
use Codeception\Test\Unit;

/**
 * Lectura del formulario DTIC desde un DOCX. El documento se arma en el test
 * (Support\DocxDePrueba) con la misma estructura que los del banco de
 * especificaciones, para no guardar documentos institucionales en el
 * repositorio.
 */
class EspecificacionMetadataExtractorTest extends Unit
{
    private string $archivo;

    protected function _after(): void
    {
        if (isset($this->archivo) && is_file($this->archivo)) {
            unlink($this->archivo);
        }
    }

    public function testLeeCabeceraCaracteristicasYGarantia(): void
    {
        $datos = $this->extraer([
            'Universidad Mayor, Real y Pontificia de San Francisco Xavier de Chuquisaca',
            'Cable de red Categoría 6, 100% cobre tipo 23 AWGNET:880/26',
            'Cantidad: 2 caja',
            'Dependencia: Facultad de Ciencias y Tecnología',
        ], [
            ['ESPECIFICACIONES TÉCNICAS'],
            ['I. REQUISITOS NECESARIOS'],
            ['Especifique marca y modelo a ofertar:', 'Sugerencia: NEXXT SOLUTIONS (MPN: PCGUCC6LZGR) o similar/superior.'],
            ['URL: URL del Fabricante'],
            ['Calibre del Conductor: 23 AWG.'],
            ['Soporte PoE (Power over Ethernet):', 'IEEE 802.3af', 'IEEE 802.3at'],
            ['Tipo de cable: UTP categoría 6', 'Color: Azul'],
            ['II GARANTÍA TÉCNICAS'],
            ['Garantías de buen funcionamiento: Como mínimo 1 año.'],
            ['III CONDICIONES COMPLEMENTARIAS'],
            ['Validez de la oferta: 10 días como mínimo.'],
        ], ['Fecha de emisión: 07 de octubre de 2026']);

        $this->assertSame('NET:880/26', $datos['codigoNet']);
        // El codigo NET pegado al titulo no forma parte del titulo.
        $this->assertSame('Cable de red Categoría 6, 100% cobre tipo 23 AWG', $datos['titulo']);
        $this->assertSame('NEXXT SOLUTIONS (MPN: PCGUCC6LZGR)', $datos['modeloSugerido']);
        $this->assertSame('2026-10-07', $datos['fechaEmision']);
        $this->assertSame('Garantías de buen funcionamiento: Como mínimo 1 año.', $datos['garantia']);
        $this->assertSame([
            ['nombre' => 'Calibre del Conductor', 'valor' => '23 AWG.'],
            ['nombre' => 'Soporte PoE (Power over Ethernet)', 'valor' => "IEEE 802.3af\nIEEE 802.3at"],
            ['nombre' => 'Tipo de cable', 'valor' => 'UTP categoría 6'],
            ['nombre' => 'Color', 'valor' => 'Azul'],
        ], $datos['caracteristicas']);
    }

    public function testModeloSugeridoTerminaDondeEmpiezaOSimilar(): void
    {
        $casos = [
            'Se sugiere Cisco C1200-24P-4G, o con superiores características.' => 'Cisco C1200-24P-4G',
            'Se sugiere U6 PRO similar o superiores características' => 'U6 PRO',
            'Sugerencia: Mikrotik CRS310-1G-5S-4S+IN o superior.' => 'Mikrotik CRS310-1G-5S-4S+IN',
            'Se sugiere la marca Furukawa 2340019 o similar.' => 'Furukawa 2340019',
            'Marca de referencia: Rowland o superior.' => 'Rowland',
            'Marca de referencia: Siemax o de superior característica.' => 'Siemax',
            'Marca de referencia: Corning o equivalente con características superiores.' => 'Corning',
            'Marca de referencia: Surlink o corning  100% cobre' => 'Surlink o corning',
            'Marca de referencia: Furukawa cod. 23400196 o con mejores características.' => 'Furukawa cod. 23400196',
            'Marca de referencia:  DIMAX  C706  o similar con mejores características.' => 'DIMAX C706',
        ];

        foreach ($casos as $texto => $esperado) {
            $datos = $this->extraer(['Equipo', 'Cantidad: 1'], [
                ['I. REQUISITOS NECESARIOS'],
                ['Marca: Especificar', $texto],
                ['Puertos: 24'],
            ]);
            $this->assertSame($esperado, $datos['modeloSugerido'], $texto);
        }
    }

    public function testMarcaDeReferenciaDeUnComponenteNoEsElModeloSugerido(): void
    {
        $datos = $this->extraer(['Gabinete 42U', 'Cantidad: 1'], [
            ['I. REQUISITOS NECESARIOS'],
            ['Accesorios', 'Panel de parcheo descargado con 24 jack hembra cat6 marca de referencia Furukawa o de mejor calidad.'],
        ]);

        $this->assertNull($datos['modeloSugerido']);
    }

    public function testFormato2023SinGarantiaNoMezclaLasCondiciones(): void
    {
        $datos = $this->extraer(['Conector RJ-45', 'Cantidad: 100 unidades'], [
            ['I. REQUISITOS NECESARIOS'],
            ['Categoría: 6'],
            ['II CONDICIONES COMPLEMENTARIAS'],
            ['Validez de la oferta: 1 mes como mínimo.'],
        ]);

        $this->assertNull($datos['garantia']);
        $this->assertSame([['nombre' => 'Categoría', 'valor' => '6']], $datos['caracteristicas']);
    }

    public function testCondicionesNumeradasSinTituloDeSeccionTambienCortan(): void
    {
        $datos = $this->extraer(['Switch', 'Cantidad: 1'], [
            ['I. REQUISITOS NECESARIOS'],
            ['Puertos: 24'],
            ['IV. GARANTIAS TECNICAS'],
            ['Como mínimo 2 años.'],
            ['1. Validez de la oferta: 10 días como mínimo.'],
        ]);

        $this->assertSame('Como mínimo 2 años.', $datos['garantia']);
        $this->assertSame([['nombre' => 'Puertos', 'valor' => '24']], $datos['caracteristicas']);
    }

    /**
     * @param string[] $antes
     * @param array<int, string[]> $filas cada fila: los parrafos de la primera celda
     * @param string[] $despues
     */
    private function extraer(array $antes, array $filas, array $despues = []): array
    {
        $this->_after();
        // tempnam() crea el archivo sin extension: se usa solo su nombre.
        $base = tempnam(sys_get_temp_dir(), 'esp');
        unlink($base);
        $this->archivo = $base . '.docx';
        DocxDePrueba::escribir($this->archivo, $antes, $filas, $despues);

        return (new EspecificacionMetadataExtractor())->extraerDesdeArchivo($this->archivo, 'docx');
    }
}
