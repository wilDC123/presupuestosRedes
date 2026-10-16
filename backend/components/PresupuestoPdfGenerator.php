<?php

declare(strict_types=1);

namespace backend\components;

use backend\models\Presupuesto;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

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
     * $marcaDeAgua: texto en diagonal, muy claro, detras de cada pagina (ej.
     * "BORRADOR"), para que un presupuesto sin aprobar no se confunda con el
     * definitivo. null = sin marca.
     */
    public function generarDesdeHtml(string $html, ?string $marcaDeAgua = null): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 20,
            'margin_bottom' => 18,
            'margin_left' => 15,
            'margin_right' => 15,
        ]);

        $mpdf->SetFooter('Página {PAGENO} de {nbpg}');

        if ($marcaDeAgua !== null) {
            $mpdf->SetWatermarkText($marcaDeAgua, 0.08);
            $mpdf->showWatermarkText = true;
        }
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Nombre del archivo PDF: usa el codigo del proyecto (RD-PI-PT-08), que
     * es unico. Si el proyecto no tiene codigo, usa el numero de presupuesto
     * y, como ultimo respaldo, su id, para que el nombre nunca quede vacio.
     * Se reemplaza cualquier caracter que no sea seguro en un nombre de archivo.
     */
    public function nombreArchivo(Presupuesto $presupuesto): string
    {
        $referencia = $presupuesto->proyecto->codigo ?? null;

        if ($referencia === null || $referencia === '') {
            $referencia = $presupuesto->numeroPresupuesto !== null && $presupuesto->numeroPresupuesto !== ''
                ? $presupuesto->numeroPresupuesto
                : (string) $presupuesto->id;
        }

        return 'presupuesto_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $referencia) . '.pdf';
    }
}
