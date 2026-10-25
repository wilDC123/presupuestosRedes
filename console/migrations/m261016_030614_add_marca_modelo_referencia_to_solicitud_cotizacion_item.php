<?php

use yii\db\Migration;

/**
 * Marca y modelo de REFERENCIA que la DTIC pide para cada item de una
 * solicitud de cotizacion (texto libre, opcional). Sale en el documento de
 * la solicitud junto a la descripcion: "Cable UTP cat. 6 (marca X modelo Y)".
 *
 * No confundir con CotizacionDetalle.marcaModelo, que es lo que el
 * proveedor OFERTA en su cotizacion.
 */
class m261016_030614_add_marca_modelo_referencia_to_solicitud_cotizacion_item extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('redes.SolicitudCotizacionItem', 'marcaModeloReferencia', $this->string(300)->null());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('redes.SolicitudCotizacionItem', 'marcaModeloReferencia');
    }
}
