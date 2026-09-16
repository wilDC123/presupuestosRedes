<?php

use yii\db\Migration;

class m260831_014824_create_schema_redes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute("
            IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'redes')
            BEGIN
                EXEC('CREATE SCHEMA redes')
            END
        ");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->execute("
            IF EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'redes')
            BEGIN
                EXEC('DROP SCHEMA redes')
            END
        ");
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260831_014824_create_schema_redes cannot be reverted.\n";

        return false;
    }
    */
}
