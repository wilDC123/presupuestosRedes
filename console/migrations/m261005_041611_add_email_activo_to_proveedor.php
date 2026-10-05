<?php

use yii\db\Migration;

/**
 * Agrega a `redes.Proveedor` el correo de contacto y el estado activo/inactivo
 * que muestra la pantalla de Proveedores.
 */
class m261005_041611_add_email_activo_to_proveedor extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('redes.Proveedor', 'email', $this->string(200));

        // NOT NULL con valor por defecto 1: los proveedores que ya existen
        // quedan activos sin tener que actualizarlos a mano.
        $this->addColumn('redes.Proveedor', 'activo', $this->boolean()->notNull()->defaultValue(1));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // En SQL Server el valor por defecto es una restriccion aparte;
        // dropColumn() de Yii la elimina antes de borrar la columna.
        $this->dropColumn('redes.Proveedor', 'activo');
        $this->dropColumn('redes.Proveedor', 'email');
    }
}
