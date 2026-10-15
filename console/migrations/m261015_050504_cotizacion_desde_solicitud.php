<?php

use yii\db\Migration;

/**
 * La cotizacion de un proveedor se registra DESDE la solicitud del proyecto
 * a la que responde, y cada linea apunta al item de la lista que cotiza.
 *
 * Las dos columnas permiten NULL: las cotizaciones "sueltas" (que no salen
 * de ninguna solicitud) siguen siendo validas y se pueden usar en el
 * presupuesto de cualquier proyecto, por su item del subcatalogo.
 */
class m261015_050504_cotizacion_desde_solicitud extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Sin ON DELETE: una solicitud que ya tiene cotizaciones registradas
        // no se puede borrar (SolicitudCotizacionService::eliminar() lo
        // explica con un mensaje antes de que la base lo rechace).
        $this->addColumn('redes.Cotizacion', 'idSolicitudCotizacion', $this->integer()->null());
        $this->addForeignKey('fk-cotizacion-idsolicitudcotizacion', 'redes.Cotizacion', 'idSolicitudCotizacion', 'redes.SolicitudCotizacion', 'id');
        // SQL Server no crea solo el indice de una FK.
        $this->createIndex('idx-cotizacion-idsolicitudcotizacion', 'redes.Cotizacion', 'idSolicitudCotizacion');

        // ON DELETE SET NULL: si el item se quita de la lista (orden de
        // cambio), la linea cotizada se conserva en la cotizacion del
        // proveedor (sigue siendo un precio real de ese item del subcatalogo)
        // y solo pierde el enlace con la lista.
        $this->addColumn('redes.CotizacionDetalle', 'idProyectoItem', $this->integer()->null());
        $this->addForeignKey('fk-cotizaciondetalle-idproyectoitem', 'redes.CotizacionDetalle', 'idProyectoItem', 'redes.ProyectoItem', 'id', 'SET NULL');
        $this->createIndex('idx-cotizaciondetalle-idproyectoitem', 'redes.CotizacionDetalle', 'idProyectoItem');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-cotizaciondetalle-idproyectoitem', 'redes.CotizacionDetalle');
        $this->dropForeignKey('fk-cotizaciondetalle-idproyectoitem', 'redes.CotizacionDetalle');
        $this->dropColumn('redes.CotizacionDetalle', 'idProyectoItem');

        $this->dropIndex('idx-cotizacion-idsolicitudcotizacion', 'redes.Cotizacion');
        $this->dropForeignKey('fk-cotizacion-idsolicitudcotizacion', 'redes.Cotizacion');
        $this->dropColumn('redes.Cotizacion', 'idSolicitudCotizacion');
    }
}
