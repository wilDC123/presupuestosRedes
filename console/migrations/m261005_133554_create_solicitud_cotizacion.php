<?php

use yii\db\Migration;

/**
 * Solicitudes de cotizacion: grupos de items de la lista de un proyecto, uno
 * por rubro de proveedor (ferreteria, equipamiento de red...). Cada solicitud
 * genera su propio documento para enviar.
 */
class m261005_133554_create_solicitud_cotizacion extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.SolicitudCotizacion', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            // Correlativo dentro del proyecto: SC-01, SC-02...
            'numero' => $this->integer()->notNull(),
            // Rubro al que se envia, en texto libre ("Ferreteria y ducteo").
            'nombre' => $this->string(200)->notNull(),
            'nota' => $this->string(500),
            'fecha' => $this->dateTime()->notNull()->defaultExpression('GETDATE()'),
        ]);
        $this->addForeignKey('fk-solicitudcotizacion-idproyecto', 'redes.SolicitudCotizacion', 'idProyecto', 'redes.Proyecto', 'id', 'CASCADE');
        $this->createIndex('uq-solicitudcotizacion-numero', 'redes.SolicitudCotizacion', ['idProyecto', 'numero'], true);

        // Que item de la lista va en que solicitud. Las cantidades no se
        // copian: se leen de redes.ProyectoItem.
        $this->createTable('redes.SolicitudCotizacionItem', [
            'id' => $this->primaryKey(),
            'idSolicitud' => $this->integer()->notNull(),
            'idProyectoItem' => $this->integer()->notNull(),
        ]);
        $this->addForeignKey('fk-solicitudcotizacionitem-idsolicitud', 'redes.SolicitudCotizacionItem', 'idSolicitud', 'redes.SolicitudCotizacion', 'id', 'CASCADE');
        // Sin CASCADE aqui: SQL Server no admite dos caminos de borrado en
        // cascada hacia la misma tabla (Proyecto -> Solicitud -> esta, y
        // Proyecto -> ProyectoItem -> esta). Al quitar un item de la lista,
        // ListaItemsService borra antes su fila de aqui.
        $this->addForeignKey('fk-solicitudcotizacionitem-idproyectoitem', 'redes.SolicitudCotizacionItem', 'idProyectoItem', 'redes.ProyectoItem', 'id');
        // Indice unico: cada item de la lista va en UNA sola solicitud.
        $this->createIndex('uq-solicitudcotizacionitem-idproyectoitem', 'redes.SolicitudCotizacionItem', 'idProyectoItem', true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // dropTable elimina tambien las llaves foraneas que salen de la tabla.
        $this->dropTable('redes.SolicitudCotizacionItem');
        $this->dropTable('redes.SolicitudCotizacion');
    }
}
