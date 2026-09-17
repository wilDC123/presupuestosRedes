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
    public function generarDesdeHtml(string $html): string
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
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Ej: "presupuesto_PRUEBA-001_v1.pdf". Si no hay numeroPresupuesto
     * cargado todavia, usa el id como respaldo para que el nombre nunca
     * quede vacio.
     */
    public function nombreArchivo(Presupuesto $presupuesto): string
    {
        $numero = $presupuesto->numeroPresupuesto !== null && $presupuesto->numeroPresupuesto !== ''
            ? $presupuesto->numeroPresupuesto
            : (string) $presupuesto->id;

        $numeroSeguro = preg_replace('/[^A-Za-z0-9_-]/', '_', $numero);

        return "presupuesto_{$numeroSeguro}_v{$presupuesto->numeroVersion}.pdf";
    }
}
