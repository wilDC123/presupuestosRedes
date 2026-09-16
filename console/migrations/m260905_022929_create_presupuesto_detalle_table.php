<?php

use yii\db\Migration;

/**
 * Handles the creation of table `redes.PresupuestoDetalle`.
 */
class m260905_022929_create_presupuesto_detalle_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.PresupuestoDetalle', [
            'id' => $this->primaryKey(),
            'idPresupuesto' => $this->integer()->notNull(),
            'idSubcatalogo' => $this->integer()->notNull(),
            // NULL permitido: a veces se ingresa un precio manual sin tener
            // todavia una cotizacion registrada.
            'idCotizacionDetalle' => $this->integer()->null(),
            'cantidad' => $this->decimal(10, 2)->notNull(),
            'unidadMedida' => $this->string(20),
            // CONGELADO: se copia una sola vez desde CotizacionDetalle al
            // crear la fila (ver PresupuestoDetalle::beforeSave() en el
            // modelo). Nunca se recalcula aunque cambie el precio de origen.
            'precioUnitario' => $this->decimal(10, 2)->notNull(),
            'precioTotal' => 'AS (CAST(cantidad * precioUnitario AS decimal(10,2))) PERSISTED',
        ]);

        $this->addForeignKey(
            'fk-presupuestodetalle-idpresupuesto',
            'redes.PresupuestoDetalle',
            'idPresupuesto',
            'redes.Presupuesto',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-presupuestodetalle-idsubcatalogo',
            'redes.PresupuestoDetalle',
            'idSubcatalogo',
            'redes.SubcatalogoItem',
            'id'
        );

        $this->addForeignKey(
            'fk-presupuestodetalle-idcotizaciondetalle',
            'redes.PresupuestoDetalle',
            'idCotizacionDetalle',
            'redes.CotizacionDetalle',
            'id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-presupuestodetalle-idcotizaciondetalle', 'redes.PresupuestoDetalle');
        $this->dropForeignKey('fk-presupuestodetalle-idsubcatalogo', 'redes.PresupuestoDetalle');
        $this->dropForeignKey('fk-presupuestodetalle-idpresupuesto', 'redes.PresupuestoDetalle');
        $this->dropTable('redes.PresupuestoDetalle');
    }
}
