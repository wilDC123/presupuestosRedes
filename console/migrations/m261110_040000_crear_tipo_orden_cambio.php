<?php

use yii\db\Migration;

/**
 * Catalogo de tipos de orden de cambio (redes.TipoOrdenCambio), para que el
 * encargado los administre desde el sistema (pedido del desarrollador el
 * 2026-11-09; hasta ese dia estaban escritos en Proyecto::tiposCambio()).
 *
 * Se carga con la lista 7.1.1 "Tipos de ordenes de cambio a considerar" del
 * formato RD-GD-FR-006, con su texto tal cual.
 *
 * redes.Proyecto: la columna de texto tipoCambio (la clave del codigo, ej.
 * "solicitud") se reemplaza por la FK idTipoOrdenCambio. Con la FK, la base
 * impide borrar un tipo en uso: se desactiva.
 */
class m261110_040000_crear_tipo_orden_cambio extends Migration
{
    /** Clave que guardaba tipoCambio => nombre (RD-GD-FR-006, 7.1.1). */
    private const TIPOS = [
        'solicitud' => 'Nuevas solicitudes de los usuarios',
        'omision' => 'Errores u omisiones en la planificación y propuestas técnicas',
        'presupuesto' => 'Presupuestos imprecisos',
        'alcance' => 'Cambios en el alcance de los proyectos',
        'costos' => 'Costos de recursos',
        'imprevisto' => 'Eventos no planificados',
    ];

    public function safeUp()
    {
        $this->createTable('redes.TipoOrdenCambio', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(150)->notNull(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
        ]);
        $this->createIndex('ux-tipoordencambio-nombre', 'redes.TipoOrdenCambio', 'nombre', true);

        $this->addColumn('redes.Proyecto', 'idTipoOrdenCambio', $this->integer()->null());
        $this->addForeignKey('fk-proyecto-idtipoordencambio', 'redes.Proyecto', 'idTipoOrdenCambio', 'redes.TipoOrdenCambio', 'id');

        foreach (self::TIPOS as $clave => $nombre) {
            $this->insert('redes.TipoOrdenCambio', ['nombre' => $nombre]);
            $id = (int) $this->db->getLastInsertID();
            $this->update('redes.Proyecto', ['idTipoOrdenCambio' => $id], ['tipoCambio' => $clave]);
        }

        $this->dropColumn('redes.Proyecto', 'tipoCambio');
    }

    public function safeDown()
    {
        $this->addColumn('redes.Proyecto', 'tipoCambio', $this->string(40)->null());

        foreach (self::TIPOS as $clave => $nombre) {
            $this->execute(
                'UPDATE p SET tipoCambio = :clave FROM redes.Proyecto p JOIN redes.TipoOrdenCambio t ON t.id = p.idTipoOrdenCambio WHERE t.nombre = :nombre',
                [':clave' => $clave, ':nombre' => $nombre]
            );
        }

        $this->dropForeignKey('fk-proyecto-idtipoordencambio', 'redes.Proyecto');
        $this->dropColumn('redes.Proyecto', 'idTipoOrdenCambio');
        $this->dropTable('redes.TipoOrdenCambio');
    }
}
