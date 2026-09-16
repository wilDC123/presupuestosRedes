<?php

use yii\db\Migration;

/**
 * Handles the creation of table `redes.CotizacionDetalle`.
 */
class m260905_021313_create_cotizacion_detalle_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.CotizacionDetalle', [
            'id' => $this->primaryKey(),
            'idCotizacion' => $this->integer()->notNull(),
            'idSubcatalogo' => $this->integer()->notNull(),
            'idEspecificacion' => $this->integer()->null(),
            'marcaModelo' => $this->string(300),
            'cantidad' => $this->decimal(10, 2)->notNull(),
            'precioUnitario' => $this->decimal(10, 2)->notNull(),
            // Columna CALCULADA por SQL Server, no se escribe nunca desde la
            // aplicacion. PERSISTED significa que el valor se guarda fisicamente
            // en disco (no se recalcula en cada SELECT), por eso se puede
            // indexar/filtrar igual que una columna normal.
            'precioTotal' => 'AS (CAST(cantidad * precioUnitario AS decimal(10,2))) PERSISTED',
        ]);

        $this->addForeignKey(
            'fk-cotizaciondetalle-idcotizacion',
            'redes.CotizacionDetalle',
            'idCotizacion',
            'redes.Cotizacion',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-cotizaciondetalle-idsubcatalogo',
            'redes.CotizacionDetalle',
            'idSubcatalogo',
            'redes.SubcatalogoItem',
            'id'
        );

        $this->addForeignKey(
            'fk-cotizaciondetalle-idespecificacion',
            'redes.CotizacionDetalle',
            'idEspecificacion',
            'redes.Especificacion',
            'id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-cotizaciondetalle-idespecificacion', 'redes.CotizacionDetalle');
        $this->dropForeignKey('fk-cotizaciondetalle-idsubcatalogo', 'redes.CotizacionDetalle');
        $this->dropForeignKey('fk-cotizaciondetalle-idcotizacion', 'redes.CotizacionDetalle');
        $this->dropTable('redes.CotizacionDetalle');
    }
}
