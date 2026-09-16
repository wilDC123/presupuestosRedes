<?php

use yii\db\Migration;

/**
 * Handles the creation of table `redes.Proyecto`.
 */
class m260905_013825_create_proyecto_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.Proyecto', [
            'id' => $this->primaryKey(),
            'codigo' => $this->string(50),
            'nombre' => $this->string(300)->notNull(),
            'facultad' => $this->string(200),
            'edificio' => $this->string(200),
            'bloque' => $this->string(100),
            'fechaCreacion' => $this->dateTime()->notNull()->defaultExpression('GETDATE()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('redes.Proyecto');
    }
}
