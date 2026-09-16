<?php

use yii\db\Migration;

/**
 * Handles the creation of table `redes.Categoria`.
 */
class m260905_011320_create_categoria_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.Categoria', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(100)->notNull(),
            'orden' => $this->integer()->notNull(),
        ]);

        $this->batchInsert('redes.Categoria', ['nombre', 'orden'], [
            ['Cable redes', 1],
            ['Materiales y accesorios para redes', 2],
            ['Materiales ducteado de redes', 3],
            ['Cableado de fibra optica', 4],
            ['Armario de telecomunicaciones', 5],
            ['Dispositivos de comunicacion', 6],
            ['Mano de obra', 7],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('redes.Categoria');
    }
}
