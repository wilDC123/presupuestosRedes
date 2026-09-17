<?php

declare(strict_types=1);

namespace backend\components;

use DateTime;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/**
 * Extrae metadatos (codigoNet, dependencia, modeloSugerido, fechaEmision, titulo)
 * desde el texto de un documento de especificacion tecnica (PDF o DOCX) de DTIC,
 * usando los patrones regex definidos en el objetivo especifico del proyecto.
 *
 * Es una ayuda de PRELLENADO, no una automatizacion ciega: el usuario siempre
 * revisa y puede corregir los valores antes de guardar. Si un patron no
 * encuentra nada (ej. modeloSugerido, que muchos documentos reales no traen),
 * el campo correspondiente queda simplemente en null.
 *
 * Nota sobre DOCX: el codigo NET vive dentro de un cuadro de texto flotante
 * (textbox) en el XML interno del .docx, no en el flujo normal de parrafos.
 * Por eso NO se usa una libreria de lectura simple de Word (ignoran los
 * textboxes) -- ver extraerTextoDocx().
 */
class EspecificacionMetadataExtractor
{
    private const MESES = [
        'enero' => 1,
        'febrero' => 2,
        'marzo' => 3,
        'abril' => 4,
        'mayo' => 5,
        'junio' => 6,
        'julio' => 7,
        'agosto' => 8,
        'septiembre' => 9,
        'setiembre' => 9,
        'octubre' => 10,
        'noviembre' => 11,
        'diciembre' => 12,
    ];

    /**
     * @return array{codigoNet: ?string, dependencia: ?string, modeloSugerido: ?string, fechaEmision: ?string, titulo: ?string}
     */
    public function extraerDesdeArchivo(string $rutaArchivo, string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        $lineas = match ($extension) {
            'pdf' => $this->extraerLineasPdf($rutaArchivo),
            'docx' => $this->extraerLineasDocx($rutaArchivo),
            default => throw new InvalidArgumentException("Formato no soportado para extracción: {$extension}"),
        };

        return $this->extraerMetadatosDeLineas($lineas);
    }

    /**
     * @return string[] Lineas de texto, en el orden en que aparecen en el PDF.
     */
    private function extraerLineasPdf(string $rutaArchivo): array
    {
        $parser = new PdfParser();
        $documento = $parser->parseFile($rutaArchivo);

        $texto = $documento->getText();

        return $this->dividirEnLineas($texto);
    }

    /**
     * Abre el .docx como archivo ZIP y lee word/document.xml directamente,
     * en vez de usar una libreria de alto nivel (como PhpWord con extraccion
     * basica de parrafos), porque esas ignoran el contenido de los textboxes
     * flotantes -- y el codigo NET de los documentos de DTIC vive justo ahi.
     *
     * DOMDocument::getElementsByTagName recorre el arbol XML completo sin
     * importar el nivel de anidamiento, asi que SI encuentra los <w:t> que
     * estan dentro de un <w:txbxContent> (el contenido de un textbox).
     *
     * @return string[] Lineas de texto (una por cada <w:p>, en orden de documento).
     */
    private function extraerLineasDocx(string $rutaArchivo): array
    {
        $zip = new ZipArchive();

        if ($zip->open($rutaArchivo) !== true) {
            throw new RuntimeException("No se pudo abrir el archivo .docx como ZIP: {$rutaArchivo}");
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('El archivo .docx no contiene word/document.xml (¿está corrupto o no es un .docx válido?)');
        }

        $dom = new DOMDocument();
        $cargoOk = $dom->loadXML($xml, LIBXML_NONET);

        if (!$cargoOk) {
            throw new RuntimeException('No se pudo parsear word/document.xml como XML');
        }

        $wNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

        // Cada <w:p> es un parrafo -- incluye tanto los parrafos "normales"
        // del cuerpo del documento como los que estan dentro de un textbox
        // (<w:txbxContent><w:p>...), porque getElementsByTagNameNS busca en
        // TODO el arbol, sin importar el anidamiento.
        $parrafos = $dom->getElementsByTagNameNS($wNamespace, 'p');

        $lineas = [];
        foreach ($parrafos as $parrafo) {
            $lineas[] = $this->textoDeNodo($parrafo, $wNamespace);
        }

        return $lineas;
    }

    /**
     * Concatena el texto de todos los nodos <w:t> descendientes de $nodo,
     * en el orden en que aparecen (esto es lo que arma el texto plano real
     * de un parrafo, incluyendo el de un textbox anidado dentro de el).
     */
    private function textoDeNodo(DOMElement $nodo, string $wNamespace): string
    {
        $texto = '';

        foreach ($nodo->getElementsByTagNameNS($wNamespace, 't') as $nodoTexto) {
            $texto .= $nodoTexto->textContent;
        }

        return $texto;
    }

    /**
     * @return string[]
     */
    private function dividirEnLineas(string $texto): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);

        return explode("\n", $texto);
    }

    /**
     * @param string[] $lineas
     * @return array{codigoNet: ?string, dependencia: ?string, modeloSugerido: ?string, fechaEmision: ?string, titulo: ?string}
     */
    private function extraerMetadatosDeLineas(array $lineas): array
    {
        $textoCompleto = implode("\n", $lineas);

        return [
            'codigoNet' => $this->extraerCodigoNet($textoCompleto),
            'dependencia' => $this->extraerDependencia($textoCompleto),
            'modeloSugerido' => $this->extraerModeloSugerido($textoCompleto),
            'fechaEmision' => $this->extraerFechaEmision($textoCompleto),
            'titulo' => $this->extraerTitulo($lineas),
        ];
    }

    /**
     * Ej: "NET:851/26" -> "NET:851/26"
     */
    private function extraerCodigoNet(string $texto): ?string
    {
        if (preg_match('/NET:\s*(\d+)\/(\d+)/', $texto, $m) === 1) {
            return "NET:{$m[1]}/{$m[2]}";
        }

        return null;
    }

    /**
     * El campo puede llamarse "Dependencia:" o "Unidad:" segun el documento.
     */
    private function extraerDependencia(string $texto): ?string
    {
        if (preg_match('/(?:Dependencia|Unidad):\s*(.+)/', $texto, $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * OPCIONAL: muchos documentos reales no traen ningun modelo sugerido.
     * En ese caso, simplemente no hay match y se devuelve null (no es un error).
     */
    private function extraerModeloSugerido(string $texto): ?string
    {
        if (preg_match('/(?:Se sugiere|Sugerencia)\s+(.+?)(?:\.|,|$)/u', $texto, $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Captura el texto en español (ej. "14 de septiembre 2026" o
     * "28 de julio de 2026") y lo convierte a Y-m-d.
     */
    private function extraerFechaEmision(string $texto): ?string
    {
        if (preg_match('/Fecha de emisión:\s*(.+)/u', $texto, $m) !== 1) {
            return null;
        }

        return $this->parsearFechaEnEspanol(trim($m[1]));
    }

    /**
     * Traduce una fecha en español ("28 de julio de 2026", "14 de septiembre 2026")
     * a formato Y-m-d. Devuelve null si no logra reconocer el patron dia/mes/anio.
     */
    private function parsearFechaEnEspanol(string $texto): ?string
    {
        $mesesRegex = implode('|', array_keys(self::MESES));

        $patron = '/(\d{1,2})\s+de\s+(' . $mesesRegex . ')\s*(?:de\s+)?(\d{4})/ui';

        if (preg_match($patron, $texto, $m) !== 1) {
            return null;
        }

        [, $dia, $mesTexto, $anio] = $m;
        $mes = self::MESES[mb_strtolower($mesTexto)] ?? null;

        if ($mes === null) {
            return null;
        }

        $fecha = DateTime::createFromFormat('Y-n-j', "{$anio}-{$mes}-{$dia}");

        return $fecha !== false ? $fecha->format('Y-m-d') : null;
    }

    /**
     * El titulo no tiene una etiqueta fija como los demas campos. Estrategia:
     * es el parrafo de texto no vacio INMEDIATAMENTE ANTERIOR a la linea que
     * contiene "Cantidad:". Se recorren las lineas ya divididas (respetando
     * el orden real del documento) buscando la primera que contenga
     * "Cantidad:", y se retrocede hasta encontrar la linea anterior con
     * contenido (se saltan lineas vacias intermedias, que son comunes por
     * como word separa parrafos).
     *
     * @param string[] $lineas
     */
    private function extraerTitulo(array $lineas): ?string
    {
        $indiceCantidad = null;

        foreach ($lineas as $i => $linea) {
            if (stripos($linea, 'Cantidad:') !== false) {
                $indiceCantidad = $i;
                break;
            }
        }

        if ($indiceCantidad === null) {
            return null;
        }

        for ($i = $indiceCantidad - 1; $i >= 0; $i--) {
            $candidato = trim($lineas[$i]);

            if ($candidato !== '') {
                return $candidato;
            }
        }

        return null;
    }
}
