<?php

use yii\db\Migration;

/**
 * Quita los computos metricos del sistema (decidido el 2026-10-21): la tabla
 * redes.ComputoMetrico nunca se uso y el modulo estaba fuera del flujo del
 * proyecto.
 *
 * Por seguridad, si la tabla tiene datos la migracion se detiene en vez de
 * borrarlos: en ese caso hay que decidir antes que hacer con ellos.
 *
 * safeDown() vuelve a crear la tabla tal como la dejaron
 * m260905_024121_create_computo_metrico_table y m261015_233726_create_unidad_medida
 * (vacia).
 */
class m261021_215240_eliminar_computo_metrico extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $filas = (int) $this->db->createCommand('SELECT COUNT(*) FROM redes.ComputoMetrico')->queryScalar();

        if ($filas > 0) {
            throw new RuntimeException("redes.ComputoMetrico tiene $filas cómputo(s) guardado(s): no se borra la tabla. Revise esos datos antes de aplicar esta migración.");
        }

        $this->dropForeignKey('fk-computometrico-idproyecto', 'redes.ComputoMetrico');
        $this->dropTable('redes.ComputoMetrico');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->createTable('redes.ComputoMetrico', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'descripcion' => $this->string(300),
            'valor' => $this->decimal(10, 2),
            'unidad' => $this->string(20),
            'referenciaOrigen' => $this->string(500),
        ]);

        $this->addForeignKey('fk-computometrico-idproyecto', 'redes.ComputoMetrico', 'idProyecto', 'redes.Proyecto', 'id');
    }
}
