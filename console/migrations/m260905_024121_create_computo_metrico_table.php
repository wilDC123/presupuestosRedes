<?php

use yii\db\Migration;

/**
 * Handles the creation of table `redes.ComputoMetrico`.
 */
class m260905_024121_create_computo_metrico_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.ComputoMetrico', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'descripcion' => $this->string(300),
            'valor' => $this->decimal(10, 2),
            'unidad' => $this->string(20),
            // Texto libre citando el documento fuente (plano, relevamiento).
            // El sistema NUNCA calcula "valor", solo lo almacena junto con
            // esta referencia.
            'referenciaOrigen' => $this->string(500),
        ]);

        $this->addForeignKey(
            'fk-computometrico-idproyecto',
            'redes.ComputoMetrico',
            'idProyecto',
            'redes.Proyecto',
            'id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-computometrico-idproyecto', 'redes.ComputoMetrico');
        $this->dropTable('redes.ComputoMetrico');
    }
}
