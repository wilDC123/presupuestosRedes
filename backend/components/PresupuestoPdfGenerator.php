<?php

declare(strict_types=1);

namespace backend\components;

use backend\models\Presupuesto;
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
     * $marcaDeAgua: texto en diagonal, muy claro, detras de cada pagina (ej.
     * "BORRADOR"), para que un presupuesto sin aprobar no se confunda con el
     * definitivo. null = sin marca.
     *
     * $formato: tamaño de hoja de mPDF; 'A4-L' = A4 horizontal (para tablas
     * con muchas columnas, como la relacion con el catalogo SIGMA).
     */
    public function generarDesdeHtml(string $html, ?string $marcaDeAgua = null, string $formato = 'A4'): string
    {
        // mPDF escribe archivos temporales. Por defecto lo hace dentro de
        // vendor/, que en el servidor no deberia ser escribible: se usa la
        // carpeta runtime de la aplicacion (la misma de logs y cache).
        $carpetaTemporal = Yii::getAlias('@runtime/mpdf');
        FileHelper::createDirectory($carpetaTemporal);

        $configuracion = [
            'tempDir' => $carpetaTemporal,
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

        if ($marcaDeAgua !== null) {
            $mpdf->SetWatermarkText($marcaDeAgua, 0.08);
            $mpdf->showWatermarkText = true;
        }
        $partes = explode(self::MARCA_AL_PIE, $html, 2);
        $mpdf->WriteHTML($partes[0]);

        if (isset($partes[1])) {
            $this->escribirAlFondo($mpdf, $partes[1], $this->estilos($html), $configuracion);
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
