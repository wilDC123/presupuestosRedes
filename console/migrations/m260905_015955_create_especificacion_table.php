<?php

use yii\db\Migration;

/**
 * Handles the creation of table `redes.Especificacion`.
 */
class m260905_015955_create_especificacion_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.Especificacion', [
            'id' => $this->primaryKey(),
            'idSubcatalogo' => $this->integer()->notNull(),
            'codigoNet' => $this->string(20),
            'titulo' => $this->string(300),
            'dependencia' => $this->string(200),
            'modeloSugerido' => $this->string(200),
            'documentoRuta' => $this->string(500),
            'fechaEmision' => $this->date(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
        ]);

        $this->addForeignKey(
            'fk-especificacion-idsubcatalogo',
            'redes.Especificacion',
            'idSubcatalogo',
            'redes.SubcatalogoItem',
            'id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-especificacion-idsubcatalogo', 'redes.Especificacion');
        $this->dropTable('redes.Especificacion');
    }
}
