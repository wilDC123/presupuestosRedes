<?php

use yii\db\Migration;

/**
 * Handles the creation of table `redes.Proveedor`.
 */
class m260905_013204_create_proveedor_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.Proveedor', [
            'id' => $this->primaryKey(),
            'razonSocial' => $this->string(200)->notNull(),
            'nit' => $this->string(50),
            'contacto' => $this->string(200),
            'telefono' => $this->string(100),
            'direccion' => $this->string(300),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('redes.Proveedor');
    }
}
