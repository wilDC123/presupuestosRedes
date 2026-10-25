<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Tabla de usuarios de la PLANTILLA de Yii (dbo.user): solo sirve para el
 * login de prueba en desarrollo (admin / admin123). En produccion la
 * autenticacion es la del sistema institucional y este modulo no debe crear
 * tablas en dbo, asi que fuera del entorno de desarrollo no hace nada.
 */
class m130524_201442_init extends Migration
{
    public function safeUp(): void
    {
        if (!YII_ENV_DEV) {
            echo "    > Entorno que no es de desarrollo: no se crea la tabla de usuarios de prueba (dbo.user).\n";

            return;
        }

        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string()->notNull()->unique(),
            'auth_key' => $this->string(32)->notNull(),
            'password_hash' => $this->string()->notNull(),
            'password_reset_token' => $this->string()->unique(),
            'email' => $this->string()->notNull()->unique(),

            'status' => $this->smallInteger()->notNull()->defaultValue(10),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
    }

    public function safeDown(): void
    {
        if ($this->db->getTableSchema('{{%user}}', true) === null) {
            return;
        }

        $this->dropTable('{{%user}}');
    }
}
