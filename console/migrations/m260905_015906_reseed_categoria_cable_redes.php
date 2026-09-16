<?php

use yii\db\Migration;

class m260905_015906_reseed_categoria_cable_redes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $exists = (new \yii\db\Query())
            ->from('redes.Categoria')
            ->where(['id' => 1])
            ->exists();

        if ($exists) {
            return;
        }

        // La fila semilla "Cable redes" (id 1) se borró manualmente durante
        // pruebas del CRUD. Se reinserta forzando el id original con
        // IDENTITY_INSERT para no romper el orden 1-7 de la spec.
        $this->execute("
            SET IDENTITY_INSERT redes.Categoria ON;
            INSERT INTO redes.Categoria (id, nombre, orden) VALUES (1, 'Cable redes', 1);
            SET IDENTITY_INSERT redes.Categoria OFF;
        ");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('redes.Categoria', ['id' => 1]);
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260905_015906_reseed_categoria_cable_redes cannot be reverted.\n";

        return false;
    }
    */
}
