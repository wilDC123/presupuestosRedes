<?php

use yii\db\Migration;

/**
 * redes.ConfiguracionSolicitudCotizacion: una sola fila con los textos fijos
 * del documento "Solicitud de cotizacion" (cabecera, instrucciones al
 * proveedor y nota final), editables por el encargado. Antes estaban escritos
 * en la vista backend/views/solicitud-cotizacion/pdf.php.
 *
 * Mismo patron que redes.ConfiguracionEspecificacion.
 */
class m261019_151425_crear_configuracion_solicitud_cotizacion extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.ConfiguracionSolicitudCotizacion', [
            'id' => $this->integer()->notNull(),
            // Una linea por renglon: la primera sale en negrita.
            'textoCabecera' => $this->string(500)->notNull(),
            'textoInstrucciones' => $this->string(1000)->notNull(),
            // Opcional: sale antes de la firma del proveedor.
            'textoNotaFinal' => $this->string(500),
            'PRIMARY KEY ([[id]])',
        ]);
        // CHECK id = 1: garantiza que la tabla tenga una sola fila.
        $this->execute('ALTER TABLE redes.ConfiguracionSolicitudCotizacion ADD CONSTRAINT [ck-configuracionsolicitudcotizacion-unica] CHECK (id = 1)');

        // Los textos que tenia la vista, tal cual: el documento sale igual
        // que antes hasta que el encargado los cambie.
        $this->insert('redes.ConfiguracionSolicitudCotizacion', [
            'id' => 1,
            'textoCabecera' => "Universidad Mayor, Real y Pontificia de San Francisco Xavier de Chuquisaca\nD.T.I.C. - Unidad de Redes y Telecomunicaciones",
            'textoInstrucciones' => 'Se solicita cotizar los siguientes ítems, indicando precio unitario y precio total en bolivianos, marca y modelo ofertados, tiempo de entrega y validez de la oferta. La marca y el modelo indicados entre paréntesis en la descripción son la referencia solicitada.',
            'textoNotaFinal' => null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('redes.ConfiguracionSolicitudCotizacion');
    }
}
