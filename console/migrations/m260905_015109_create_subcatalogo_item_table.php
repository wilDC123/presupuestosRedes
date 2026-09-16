<?php

use yii\db\Migration;

/**
 * Handles the creation of table `redes.SubcatalogoItem`.
 */
class m260905_015109_create_subcatalogo_item_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.SubcatalogoItem', [
            'id' => $this->primaryKey(),
            // uniqueidentifier: referencia LOGICA a dbo.CatalogoSigma.IdSigma, sin
            // foreign key fisica -- esa tabla es externa/institucional, nunca se
            // le agregan constraints desde el esquema redes.
            'idSigma' => 'uniqueidentifier NULL',
            'idCategoria' => $this->integer()->notNull(),
            'descripcion' => $this->string(300)->notNull(),
            'unidadDefecto' => $this->string(20),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
            'fechaRegistro' => $this->dateTime()->notNull()->defaultExpression('GETDATE()'),
        ]);

        // Esta si es una FK real: idCategoria apunta a redes.Categoria, ambas
        // tablas viven en el mismo esquema del proyecto.
        $this->addForeignKey(
            'fk-subcatalogoitem-idcategoria',
            'redes.SubcatalogoItem',
            'idCategoria',
            'redes.Categoria',
            'id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-subcatalogoitem-idcategoria', 'redes.SubcatalogoItem');
        $this->dropTable('redes.SubcatalogoItem');
    }
}
