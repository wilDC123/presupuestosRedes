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

    private const W_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Un nombre de caracteristica ("Calibre del conductor") es corto; si lo
     * que esta antes de los dos puntos es mas largo, la fila es un solo texto.
     */
    private const LARGO_MAXIMO_NOMBRE = 60;

    /**
     * Ademas de los metadatos de cabecera, en un DOCX lee la tabla del
     * formulario: las filas de la seccion "I. Requisitos necesarios"
     * (caracteristicas) y la de "II Garantia". En un PDF esas dos quedan
     * vacias: el texto de un PDF pierde la estructura de la tabla.
     *
     * @return array{codigoNet: ?string, dependencia: ?string, modeloSugerido: ?string, fechaEmision: ?string, titulo: ?string, caracteristicas: array<int, array{nombre: ?string, valor: string}>, garantia: ?string}
     */
    public function extraerDesdeArchivo(string $rutaArchivo, string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        if ($extension === 'pdf') {
            return $this->extraerMetadatosDeLineas($this->extraerLineasPdf($rutaArchivo))
                + ['caracteristicas' => [], 'garantia' => null];
        }

        if ($extension !== 'docx') {
            throw new InvalidArgumentException("Formato no soportado para extracción: {$extension}");
        }

        $dom = $this->cargarDocx($rutaArchivo);

        return $this->extraerMetadatosDeLineas($this->extraerLineasDocx($dom))
            + $this->extraerTablaDocx($dom);
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
    private function extraerLineasDocx(DOMDocument $dom): array
    {
        // Cada <w:p> es un parrafo -- incluye tanto los parrafos "normales"
        // del cuerpo del documento como los que estan dentro de un textbox
        // (<w:txbxContent><w:p>...), porque getElementsByTagNameNS busca en
        // TODO el arbol, sin importar el anidamiento.
        $parrafos = $dom->getElementsByTagNameNS(self::W_NAMESPACE, 'p');

        $lineas = [];
        foreach ($parrafos as $parrafo) {
            $lineas[] = $this->textoDeNodo($parrafo, self::W_NAMESPACE);
        }

        return $lineas;
    }

    private function cargarDocx(string $rutaArchivo): DOMDocument
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

        return $dom;
    }

    /**
     * Lee la tabla del formulario DTIC. Se mira solo la PRIMERA celda de cada
     * fila (las otras son "Propuesta / Cumple / No cumple", que llena el
     * proveedor):
     *
     *   I. REQUISITOS NECESARIOS        <- empieza la seccion I
     *   Especifique marca y modelo...   <- se salta (el formulario la agrega sola)
     *   URL: ...                        <- se salta (idem)
     *   Calibre del Conductor: 23 AWG   <- caracteristica
     *   II GARANTIA TECNICAS            <- termina la seccion I
     *   Garantias de buen funcionamiento: ...   <- garantia
     *   III CONDICIONES COMPLEMENTARIAS <- fin (validez y entrega son de cada emision)
     *
     * Una celda puede tener varios parrafos. Cada parrafo "Nombre: valor"
     * empieza una caracteristica; un parrafo sin "Nombre:" se suma al valor de
     * la anterior (ej. la lista de normas PoE debajo de "Soporte PoE:").
     *
     * @return array{caracteristicas: array<int, array{nombre: ?string, valor: string}>, garantia: ?string}
     */
    private function extraerTablaDocx(DOMDocument $dom): array
    {
        $caracteristicas = [];
        $garantia = [];
        $seccion = null;

        foreach ($dom->getElementsByTagNameNS(self::W_NAMESPACE, 'tr') as $fila) {
            $celda = $fila->getElementsByTagNameNS(self::W_NAMESPACE, 'tc')->item(0);
            if ($celda === null) {
                continue;
            }

            $parrafos = [];
            foreach ($celda->getElementsByTagNameNS(self::W_NAMESPACE, 'p') as $parrafo) {
                $texto = $this->limpiarEspacios($this->textoDeNodo($parrafo, self::W_NAMESPACE));
                if ($texto !== '') {
                    $parrafos[] = $texto;
                }
            }

            if ($parrafos === []) {
                continue;
            }

            // El numero de la seccion cambia segun el año y el documento
            // ("II GARANTÍAS", "IV. GARANTIAS TECNICAS", "GARANTÍAS TÉCNICAS:",
            // "II CONDICIONES", "V. CONDICIONES", "1. Validez de la oferta"):
            // se ignora y se mira la palabra clave.
            $encabezado = (string) preg_replace('/^(?:[IVX]+|\d+)\s*\.?\s*/u', '', mb_strtoupper($parrafos[0]));

            if (str_starts_with($encabezado, 'REQUISITOS')) {
                $seccion = 'I';
                continue;
            }
            // Solo el titulo de la seccion ("GARANTÍAS", "GARANTÍA TÉCNICAS:"),
            // no una fila de contenido como "Garantías de buen funcionamiento: ...".
            if (preg_match('/^GARANT[ÍI]AS?(\s+T[ÉE]CNICAS?)?\s*:?$/u', $encabezado) === 1) {
                $seccion = 'II';
                // Si la garantia vino en la misma celda que el titulo.
                $garantia = array_merge($garantia, array_slice($parrafos, 1));
                continue;
            }
            // Validez, tiempo y lugar de entrega son de cada emision, no de la ficha.
            if (preg_match('/^(CONDICIONES COMPLEMENTARIAS|ENTREGA DE BIENES|(TIEMPO DE )?VALIDEZ DE LA OFERTA|TIEMPO DE ENTREGA|LUGAR DE ENTREGA)/u', $encabezado) === 1) {
                break;
            }

            if ($seccion === 'I') {
                if (preg_match('/^(ESPECIFI\w*\s+MARCA|MARCA\b|URL\b)/u', $encabezado) === 1) {
                    continue;
                }
                $caracteristicas = array_merge($caracteristicas, $this->caracteristicasDeCelda($parrafos));
            } elseif ($seccion === 'II') {
                $garantia = array_merge($garantia, $parrafos);
            }
        }

        return [
            'caracteristicas' => $caracteristicas,
            'garantia' => $garantia === [] ? null : implode(' ', $garantia),
        ];
    }

    /**
     * @param string[] $parrafos
     * @return array<int, array{nombre: ?string, valor: string}>
     */
    private function caracteristicasDeCelda(array $parrafos): array
    {
        $filas = [];

        foreach ($parrafos as $parrafo) {
            $posicion = mb_strpos($parrafo, ':');
            $nombre = $posicion !== false ? trim(mb_substr($parrafo, 0, $posicion)) : '';

            if ($nombre !== '' && mb_strlen($nombre) <= self::LARGO_MAXIMO_NOMBRE) {
                $filas[] = ['nombre' => $nombre, 'valor' => trim(mb_substr($parrafo, $posicion + 1))];
            } elseif ($filas !== []) {
                $ultima = array_key_last($filas);
                $filas[$ultima]['valor'] = trim($filas[$ultima]['valor'] . "\n" . $parrafo);
            } else {
                $filas[] = ['nombre' => null, 'valor' => $parrafo];
            }
        }

        // "Soporte PoE:" sin nada despues ni debajo: queda como texto suelto.
        foreach ($filas as $i => $fila) {
            if ($fila['valor'] === '') {
                $filas[$i] = ['nombre' => null, 'valor' => (string) $fila['nombre']];
            }
        }

        return $filas;
    }

    private function limpiarEspacios(string $texto): string
    {
        return trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $texto));
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
        // "Se sugiere Cisco C1200-24P-4G, o con superiores..." y
        // "Sugerencia: NEXXT SOLUTIONS (MPN: PCGUCC6LZGR) o similar/superior."
        // El modelo termina en la coma, en " o similar/superior/con/de/
        // equivalente...", en "100% cobre" o en un punto final (no en el
        // punto de "1.2" ni en el de "cod.").
        // Tambien "Se sugiere U6 PRO similar o superiores caracteristicas" y
        // "Marca de referencia: Rowland o superior." (formularios 2024-2026).
        // "Marca de referencia" solo cuenta al inicio de la linea: en medio de
        // una linea es la de un componente ("Panel de parcheo ... marca de
        // referencia Furukawa"), no la del item.
        $patron = '/(?:Se sugiere|Sugerencia|^\s*[Mm]arca de referencia)\s*:?\s*(.+?)'
            . '(?:,|\s+(?:o\s+)?(?:similar|superior)|\s+o\s+(?:con|de|equivalente)\b|\s+\d+\s*%|(?<!\b[Cc]od)\.(?:\s|$)|$)/mu';

        if (preg_match($patron, $texto, $m) === 1 && trim($m[1]) !== '') {
            // "Se sugiere la marca Furukawa 2340019" -> "Furukawa 2340019".
            $modelo = trim((string) preg_replace('/^(?:la\s+)?marca\s+(?:de\s+referencia\s*:?\s*)?/iu', '', trim($m[1])));
            // "DIMAX  C706" -> "DIMAX C706".
            $modelo = (string) preg_replace('/\s+/u', ' ', $modelo);

            return $modelo !== '' ? $modelo : null;
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
            // En algunos documentos el textbox del codigo NET queda pegado al
            // titulo en el mismo parrafo ("SWITCH 24 PUERTOS POENET:843/26").
            $candidato = trim((string) preg_replace('/NET:\s*\d+\/\d+/', '', $lineas[$i]));

            if ($candidato !== '') {
                return $candidato;
            }
        }

        return null;
    }
}
