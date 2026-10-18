<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Columna de la tabla de usuarios de prueba de la plantilla de Yii. Si la
 * tabla no se creo (produccion, ver m130524_201442_init), no hace nada.
 */
class m190124_110200_add_verification_token_column_to_user_table extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->getTableSchema('{{%user}}', true) === null) {
            return;
        }

        $this->addColumn('{{%user}}', 'verification_token', $this->string()->defaultValue(null));
    }

    public function safeDown(): void
    {
        if ($this->db->getTableSchema('{{%user}}', true) === null) {
            return;
        }

        $this->dropColumn('{{%user}}', 'verification_token');
    }
}
