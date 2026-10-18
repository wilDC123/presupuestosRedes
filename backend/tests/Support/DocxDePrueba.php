<?php

declare(strict_types=1);

namespace backend\tests\Support;

use ZipArchive;

/**
 * Arma un .docx minimo con la estructura de los formularios del banco de
 * especificaciones de DTIC (parrafos, una tabla de 2 columnas y parrafos
 * finales), para los tests del extractor y de la importacion. Asi no hace
 * falta guardar documentos institucionales en el repositorio.
 */
class DocxDePrueba
{
    /**
     * @param string $ruta donde escribir el .docx
     * @param string[] $antes parrafos antes de la tabla
     * @param array<int, string[]> $filas cada fila: los parrafos de la primera celda (la segunda va vacia, como "Propuesta")
     * @param string[] $despues parrafos despues de la tabla
     */
    public static function escribir(string $ruta, array $antes, array $filas, array $despues = []): void
    {
        $p = static fn (string $texto): string => '<w:p><w:r><w:t xml:space="preserve">' . htmlspecialchars($texto, ENT_XML1) . '</w:t></w:r></w:p>';

        $tabla = '';
        if ($filas !== []) {
            $tabla = '<w:tbl>';
            foreach ($filas as $parrafos) {
                $tabla .= '<w:tr><w:tc>' . implode('', array_map($p, $parrafos)) . '</w:tc><w:tc><w:p/></w:tc></w:tr>';
            }
            $tabla .= '</w:tbl>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . implode('', array_map($p, $antes)) . $tabla . implode('', array_map($p, $despues))
            . '</w:body></w:document>';

        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
    }
}
