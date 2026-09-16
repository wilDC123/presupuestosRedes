<?php

use yii\db\Migration;

/**
 * Handles the creation of table `redes.Cotizacion`.
 */
class m260905_020426_create_cotizacion_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.Cotizacion', [
            'id' => $this->primaryKey(),
            'idProveedor' => $this->integer()->notNull(),
            'numeroReferencia' => $this->string(100),
            'fecha' => $this->date(),
            'documentoRuta' => $this->string(500),
            'validezDias' => $this->integer(),
        ]);

        $this->addForeignKey(
            'fk-cotizacion-idproveedor',
            'redes.Cotizacion',
            'idProveedor',
            'redes.Proveedor',
            'id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-cotizacion-idproveedor', 'redes.Cotizacion');
        $this->dropTable('redes.Cotizacion');
    }
}
