<?php

declare(strict_types=1);

namespace backend\components;

use backend\models\Presupuesto;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Yii;
use yii\helpers\FileHelper;

/**
 * Convierte el HTML del PDF consolidado del presupuesto (armado en la vista
 * backend/views/presupuesto/pdf.php) a un archivo PDF real, usando mPDF.
 *
 * mPDF no "imprime" la pagina como un navegador: interpreta el HTML/CSS que
 * se le pasa con su propio motor de layout (soporta un subconjunto de CSS,
 * pensado para documentos -- tablas, saltos de pagina, encabezados/pies de
 * pagina -- no para diseños complejos tipo aplicacion web). Por eso este
 * PDF usa HTML/CSS deliberadamente simple: mPDF lo interpreta de forma mas
 * predecible que un layout con flexbox/grid o CSS moderno.
 */
class PresupuestoPdfGenerator
{
    /**
     * Si el HTML trae esta marca, lo que va DESPUES de ella (firmas, pie) se
     * imprime pegado al FONDO de la ultima pagina, no a continuacion del
     * texto. Si no cabe en el espacio libre, va al fondo de una pagina nueva.
     */
    public const MARCA_AL_PIE = '<!-- al-pie-de-pagina -->';

    /**
     * Hoja oficio (8,5 x 13 pulgadas = 216 x 330 mm), la que usa la DTIC para
     * imprimir todo. En mPDF se llama "Folio"; "-L" = horizontal.
     */
    public const HOJA_OFICIO = 'Folio';
    public const HOJA_OFICIO_HORIZONTAL = 'Folio-L';

    /**
     * $marcaDeAgua: texto en diagonal, muy claro, detras de cada pagina (ej.
     * "BORRADOR"), para que un presupuesto sin aprobar no se confunda con el
     * definitivo. null = sin marca.
     *
     * $formato: tamaño de hoja de mPDF; HOJA_OFICIO_HORIZONTAL para tablas
     * con muchas columnas, como la relacion con el catalogo SIGMA.
     */
    public function generarDesdeHtml(string $html, ?string $marcaDeAgua = null, string $formato = self::HOJA_OFICIO): string
    {
        return $this->generarVarios([['html' => $html, 'marcaDeAgua' => $marcaDeAgua]], $formato);
    }

    /**
     * Junta varios documentos en UN PDF, cada uno desde una hoja nueva y con
     * su propia marca de agua (ej. todos los formularios NET de un proyecto).
     * Cada HTML se escribe igual que en generarDesdeHtml(), con su
     * MARCA_AL_PIE al fondo de su propia ultima pagina.
     *
     * @param array<int, array{html: string, marcaDeAgua: ?string}> $documentos
     */
    public function generarVarios(array $documentos, string $formato = self::HOJA_OFICIO): string
    {
        // mPDF escribe archivos temporales. Por defecto lo hace dentro de
        // vendor/, que en el servidor no deberia ser escribible: se usa la
        // carpeta runtime de la aplicacion (la misma de logs y cache).
        $carpetaTemporal = Yii::getAlias('@runtime/mpdf');
        FileHelper::createDirectory($carpetaTemporal);

        // Las fuentes que trae mPDF, mas "pagelladtic" (la de la carta de
        // entrega, en backend/fonts: ver su MANIFEST). mPDF solo carga una
        // fuente si un documento la usa.
        $variables = (new ConfigVariables())->getDefaults();
        $fuentes = (new FontVariables())->getDefaults();

        $configuracion = [
            'tempDir' => $carpetaTemporal,
            'fontDir' => array_merge($variables['fontDir'], [Yii::getAlias('@backend/fonts/pagella-dtic')]),
            'fontdata' => $fuentes['fontdata'] + [
                'pagelladtic' => [
                    'R' => 'PagellaDTIC-Regular.ttf',
                    'B' => 'PagellaDTIC-Bold.ttf',
                    'I' => 'PagellaDTIC-Italic.ttf',
                    'BI' => 'PagellaDTIC-BoldItalic.ttf',
                ],
            ],
            'mode' => 'utf-8',
            'format' => $formato,
            'margin_top' => 20,
            'margin_bottom' => 18,
            'margin_left' => 15,
            'margin_right' => 15,
        ];
        $mpdf = new Mpdf($configuracion);

        // Sin pie de pagina. El presupuesto y la solicitud de cotizacion
        // llevan su cabecera (con "pág. X de Y") desde la vista, con
        // @page { header: html_cabecera } -- ver views/pdf/_cabecera.php;
        // su margen superior lo fija esa misma regla @page.

        foreach (array_values($documentos) as $i => $documento) {
            // Primero se cierra la hoja anterior (con la marca de agua del
            // documento anterior) y recien despues se cambia la marca.
            // La hoja nueva vuelve a lo de por defecto: sin la cabecera ni el
            // margen superior que dejo la regla @page del documento anterior
            // (ej. la solicitud de cotizacion seguida de los formularios NET),
            // y con la numeracion reiniciada, para que el "pág. X de Y" de
            // esa cabecera cuente solo las paginas de su documento. Mientras
            // page_box['using'] esta encendido, mPDF vuelve a aplicar esa
            // regla @page en cada hoja nueva e ignora lo que se le pasa aqui;
            // por eso se apaga primero.
            if ($i > 0) {
                $mpdf->page_box['using'] = false;
                $mpdf->AddPageByArray([
                    'ohvalue' => -1,
                    'ehvalue' => -1,
                    'mgt' => $configuracion['margin_top'],
                    'resetpagenum' => 1,
                ]);
            }

            $mpdf->showWatermarkText = $documento['marcaDeAgua'] !== null;
            if ($documento['marcaDeAgua'] !== null) {
                $mpdf->SetWatermarkText($documento['marcaDeAgua'], 0.08);
            }

            $partes = explode(self::MARCA_AL_PIE, $documento['html'], 2);
            $mpdf->WriteHTML($partes[0]);

            if (isset($partes[1])) {
                $this->escribirAlFondo($mpdf, $partes[1], $this->estilos($documento['html']), $configuracion);
            }
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Escribe $html pegado al fondo de la pagina actual (o de una nueva, si
     * no cabe). mPDF no sabe "anclar abajo", asi que: 1) se mide el alto del
     * bloque escribiendolo en un PDF de prueba que se descarta; 2) se mueve
     * el cursor ($mpdf->y, en mm desde arriba) a "fondo - alto".
     */
    private function escribirAlFondo(Mpdf $mpdf, string $html, string $estilos, array $configuracion): void
    {
        // Se mide desde debajo de un bloque previo: al inicio de una pagina
        // mPDF ignora el margen superior del primer elemento, y en el
        // documento real el bloque nunca va al inicio.
        $prueba = new Mpdf($configuracion);
        $prueba->WriteHTML($estilos . '<div style="height: 1mm;"></div>');
        $desde = $prueba->y;
        $prueba->WriteHTML($html);
        // +2 mm de holgura por redondeos. Si faltara espacio, mPDF achicaria
        // las tablas (firmas, pie) para que entren.
        $alto = $prueba->y - $desde + 2;

        $fondo = $mpdf->h - $mpdf->bMargin;

        if ($mpdf->y + $alto > $fondo) {
            $mpdf->AddPage();
        }

        $mpdf->y = max($mpdf->y, $fondo - $alto);
        $mpdf->WriteHTML($html);
    }

    /**
     * Los <style> del documento (sin la regla @page de la cabecera), para
     * medir el bloque del fondo con los mismos estilos.
     */
    private function estilos(string $html): string
    {
        preg_match_all('#<style[^>]*>.*?</style>#is', $html, $bloques);

        return preg_replace('/@page\s*\{[^}]*\}/', '', implode('', $bloques[0]));
    }

    /**
     * Nombre del archivo PDF: usa el codigo del proyecto (RD-PI-PT-08), que
     * es unico. Si el proyecto no tiene codigo, usa el numero de presupuesto
     * y, como ultimo respaldo, su id, para que el nombre nunca quede vacio.
     * Se reemplaza cualquier caracter que no sea seguro en un nombre de archivo.
     */
    public function nombreArchivo(Presupuesto $presupuesto, string $prefijo = 'presupuesto_'): string
    {
        $referencia = $presupuesto->proyecto->codigo ?? null;

        if ($referencia === null || $referencia === '') {
            $referencia = $presupuesto->numeroPresupuesto !== null && $presupuesto->numeroPresupuesto !== ''
                ? $presupuesto->numeroPresupuesto
                : (string) $presupuesto->id;
        }

        return $prefijo . preg_replace('/[^A-Za-z0-9_-]/', '_', $referencia) . '.pdf';
    }
}
