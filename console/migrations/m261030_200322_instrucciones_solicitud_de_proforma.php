<?php

use yii\db\Migration;

/**
 * La solicitud de cotizacion pasa a ser la "Solicitud de proforma de
 * cotizacion" (revision del Ing. de DTIC del 2026-10-29, paso 10): una lista
 * sencilla (N.°, descripcion, unidad, cantidad) y la firma del proveedor, sin
 * columnas de precio. El texto de instrucciones por defecto pedia "indicar"
 * precios en la tabla; ahora se le pide al proveedor que envie su proforma.
 *
 * Solo se cambia si el texto sigue siendo el ORIGINAL: si el encargado ya lo
 * edito en "Configuración del documento", se respeta el suyo. safeDown()
 * vuelve al original, tambien solo si sigue siendo el nuevo.
 */
class m261030_200322_instrucciones_solicitud_de_proforma extends Migration
{
    private const ANTES = 'Se solicita cotizar los siguientes ítems, indicando precio unitario y precio total en bolivianos, marca y modelo ofertados, tiempo de entrega y validez de la oferta. La marca y el modelo indicados entre paréntesis en la descripción son la referencia solicitada.';

    private const AHORA = 'Se solicita enviar su proforma de cotización de los siguientes ítems, con precio unitario y precio total en bolivianos, marca y modelo ofertados, tiempo de entrega y validez de la oferta. La marca y el modelo indicados entre paréntesis en la descripción son la referencia solicitada.';

    public function safeUp()
    {
        $this->update('redes.ConfiguracionSolicitudCotizacion', ['textoInstrucciones' => self::AHORA], ['id' => 1, 'textoInstrucciones' => self::ANTES]);
    }

    public function safeDown()
    {
        $this->update('redes.ConfiguracionSolicitudCotizacion', ['textoInstrucciones' => self::ANTES], ['id' => 1, 'textoInstrucciones' => self::AHORA]);
    }
}
