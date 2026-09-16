<?php

use yii\db\Migration;

/**
 * Handles the creation of table `redes.Presupuesto`.
 */
class m260905_022302_create_presupuesto_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.Presupuesto', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            // Auto-referencia a la misma tabla, para el versionado. NULL en la
            // primera version de un presupuesto (no tiene version anterior).
            'idVersionAnterior' => $this->integer()->null(),
            'numeroVersion' => $this->integer()->notNull()->defaultValue(1),
            'numeroPresupuesto' => $this->string(20),
            'oficioDtic' => $this->string(100),
            'areaIntervencion' => $this->string(300),
            'estado' => $this->string(20)->notNull()->defaultValue('en_proceso'),
            'fecha' => $this->date(),
            'fechaCreacion' => $this->dateTime()->notNull()->defaultExpression('GETDATE()'),
        ]);

        $this->addForeignKey(
            'fk-presupuesto-idproyecto',
            'redes.Presupuesto',
            'idProyecto',
            'redes.Proyecto',
            'id'
        );

        $this->addForeignKey(
            'fk-presupuesto-idversionanterior',
            'redes.Presupuesto',
            'idVersionAnterior',
            'redes.Presupuesto',
            'id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-presupuesto-idversionanterior', 'redes.Presupuesto');
        $this->dropForeignKey('fk-presupuesto-idproyecto', 'redes.Presupuesto');
        $this->dropTable('redes.Presupuesto');
    }
}
