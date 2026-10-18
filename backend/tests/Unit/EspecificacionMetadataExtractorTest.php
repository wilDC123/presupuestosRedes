<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\components\EspecificacionMetadataExtractor;
use Codeception\Test\Unit;
use ZipArchive;

/**
 * Lectura del formulario DTIC desde un DOCX. El documento se arma en el test
 * con la misma estructura que los del banco de especificaciones (tabla con
 * secciones I, II y III), para no guardar documentos institucionales en el
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
     * Arma un DOCX minimo: parrafos antes de la tabla, una tabla de 2
     * columnas (la segunda vacia, como "Propuesta") y parrafos despues.
     *
     * @param string[] $antes
     * @param array<int, string[]> $filas cada fila: los parrafos de la primera celda
     * @param string[] $despues
     */
    private function extraer(array $antes, array $filas, array $despues = []): array
    {
        $p = static fn (string $texto): string => '<w:p><w:r><w:t xml:space="preserve">' . htmlspecialchars($texto, ENT_XML1) . '</w:t></w:r></w:p>';

        $tabla = '<w:tbl>';
        foreach ($filas as $parrafos) {
            $tabla .= '<w:tr><w:tc>' . implode('', array_map($p, $parrafos)) . '</w:tc><w:tc><w:p/></w:tc></w:tr>';
        }
        $tabla .= '</w:tbl>';

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . implode('', array_map($p, $antes)) . $tabla . implode('', array_map($p, $despues))
            . '</w:body></w:document>';

        $this->archivo = tempnam(sys_get_temp_dir(), 'esp') . '.docx';
        $zip = new ZipArchive();
        $zip->open($this->archivo, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return (new EspecificacionMetadataExtractor())->extraerDesdeArchivo($this->archivo, 'docx');
    }
}
