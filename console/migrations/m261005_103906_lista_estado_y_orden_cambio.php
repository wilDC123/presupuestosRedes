<?php

use yii\db\Migration;

/**
 * Estado de la lista de items del proyecto, ordenes de cambio y enlace entre
 * cada linea del presupuesto y el item de la lista del que nacio.
 */
class m261005_103906_lista_estado_y_orden_cambio extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Estado de la lista: edicion -> en_cotizacion -> cerrada. Va en el
        // proyecto porque cada proyecto tiene una sola lista.
        $this->addColumn('redes.Proyecto', 'estadoLista', $this->string(20)->notNull()->defaultValue('edicion'));

        // Orden de cambio: registro simple de por que se reabrio algo que ya
        // estaba bloqueado. solicitadoPor es texto (el nombre de usuario)
        // porque los usuarios los administra el sistema institucional.
        $this->createTable('redes.OrdenCambio', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'alcance' => $this->string(20)->notNull(), // 'lista' | 'presupuesto'
            'motivo' => $this->string(500)->notNull(),
            'solicitadoPor' => $this->string(100)->notNull(),
            'fecha' => $this->dateTime()->notNull()->defaultExpression('GETDATE()'),
            'estado' => $this->string(20)->notNull()->defaultValue('abierta'), // 'abierta' | 'cerrada'
            'fechaCierre' => $this->dateTime()->null(),
        ]);
        $this->addForeignKey('fk-ordencambio-idproyecto', 'redes.OrdenCambio', 'idProyecto', 'redes.Proyecto', 'id', 'CASCADE');

        // De que item de la lista nacio la linea del presupuesto. Permite
        // saber que items faltan presupuestar y no presupuestar uno dos veces.
        // Sin CASCADE: un item de la lista con linea en el presupuesto no se
        // puede quitar.
        $this->addColumn('redes.PresupuestoDetalle', 'idProyectoItem', $this->integer()->null());
        $this->addForeignKey('fk-presupuestodetalle-idproyectoitem', 'redes.PresupuestoDetalle', 'idProyectoItem', 'redes.ProyectoItem', 'id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-presupuestodetalle-idproyectoitem', 'redes.PresupuestoDetalle');
        $this->dropColumn('redes.PresupuestoDetalle', 'idProyectoItem');

        // dropTable elimina tambien la llave foranea que sale de la tabla.
        $this->dropTable('redes.OrdenCambio');

        $this->dropColumn('redes.Proyecto', 'estadoLista');
    }
}
