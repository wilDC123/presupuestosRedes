<?php

use yii\db\Migration;

/**
 * Usuarios TEMPORALES del modulo (redes.Usuario), para poder entrar al
 * servidor de prueba de DTIC mientras no se integre la autenticacion
 * institucional. Antes el ingreso usaba dbo.user de la plantilla de Yii, que
 * solo existe en desarrollo (m130524_201442_init no la crea en otro entorno
 * porque el modulo no debe crear tablas en dbo).
 *
 * Los usuarios se crean por consola: php yii usuario/crear <usuario>.
 *
 * Si existe dbo.user (base de desarrollo), se copian sus usuarios con la
 * misma contraseña (el hash de Yii es el mismo), asi admin / admin123 sigue
 * funcionando.
 */
class m261026_003449_crear_usuario_temporal extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.Usuario', [
            'id' => $this->primaryKey(),
            'usuario' => $this->string(64)->notNull(),
            'claveHash' => $this->string(255)->notNull(),
            // Para "recordarme": la cookie guarda esta clave y se compara al volver.
            'authKey' => $this->string(32)->notNull(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
            'fechaCreacion' => $this->dateTime()->notNull()->defaultExpression('GETDATE()'),
        ]);

        $this->createIndex('ux-usuario-usuario', 'redes.Usuario', 'usuario', true);

        if ($this->db->getTableSchema('{{%user}}', true) !== null) {
            // status 10 = activo en la plantilla de Yii.
            $this->execute(
                'INSERT INTO redes.Usuario (usuario, claveHash, authKey, activo)
                 SELECT username, password_hash, auth_key, CASE WHEN status = 10 THEN 1 ELSE 0 END
                 FROM {{%user}}'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('redes.Usuario');
    }
}
