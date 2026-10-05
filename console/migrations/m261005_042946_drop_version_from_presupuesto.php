<?php

use yii\db\Migration;

/**
 * Retira el versionado de `redes.Presupuesto`.
 *
 * DECISION: una fase NO es una version del mismo presupuesto; es un proyecto
 * nuevo enlazado al de la fase anterior (redes.Proyecto.idProyectoAnterior).
 * Por eso cada proyecto tiene UN solo presupuesto y las columnas de version
 * dejan de tener sentido.
 */
class m261005_042946_drop_version_from_presupuesto extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->dropForeignKey('fk-presupuesto-idversionanterior', 'redes.Presupuesto');
        $this->dropColumn('redes.Presupuesto', 'idVersionAnterior');
        $this->dropColumn('redes.Presupuesto', 'numeroVersion');

        // Un presupuesto por proyecto. Si existieran dos presupuestos del
        // mismo proyecto esta linea fallaria y la migracion se desharia
        // completa (safeUp corre en una transaccion): no se pierde nada.
        $this->createIndex('uq-presupuesto-idproyecto', 'redes.Presupuesto', 'idProyecto', true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('uq-presupuesto-idproyecto', 'redes.Presupuesto');

        $this->addColumn('redes.Presupuesto', 'numeroVersion', $this->integer()->notNull()->defaultValue(1));
        $this->addColumn('redes.Presupuesto', 'idVersionAnterior', $this->integer()->null());

        $this->addForeignKey(
            'fk-presupuesto-idversionanterior',
            'redes.Presupuesto',
            'idVersionAnterior',
            'redes.Presupuesto',
            'id'
        );
    }
}
