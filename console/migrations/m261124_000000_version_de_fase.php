<?php

use yii\db\Migration;

/**
 * VERSION DE UNA FASE APROBADA: un presupuesto aprobado cuyas cotizaciones
 * quedaron vencidas (o que hay que corregir: precios, cantidades,
 * especificaciones, agregar o quitar algun item) se actualiza abriendo una
 * VERSION NUEVA, en vez de editarlo o de registrar una orden de cambio (que
 * solo sirve para agregar).
 *
 * Decidido con el desarrollador el 2026-11-23/24: la version es, igual que la
 * orden de cambio, una FILA MAS de redes.Proyecto (mismo numeroProyecto y
 * numeroFase que su fase), con:
 *  - idVersionAnterior: la fila de la que se abrio (NULL = primera version, 1.0),
 *  - numeroVersionMayor, numeroVersionMenor: 1.0 -> 1.1 -> 1.2 ... -> 2.0,
 *  - esVersionVigente: la fila vieja pasa a 0 en la misma transaccion en que
 *    se crea la nueva, para que getFases() siga viendo una sola fila por fase,
 *  - motivoVersion: por que se abrio (siempre a mano).
 *
 * El indice unico (numeroProyecto, numeroFase, numeroOrdenCambio) pasa a
 * incluir numeroVersionMayor y numeroVersionMenor: varias versiones de la
 * misma fase comparten numeroFase, asi que ya no alcanzaban para distinguirlas.
 */
class m261124_000000_version_de_fase extends Migration
{
    public function safeUp()
    {
        $this->addColumn('redes.Proyecto', 'idVersionAnterior', $this->integer()->null());
        $this->addColumn('redes.Proyecto', 'numeroVersionMayor', $this->integer()->notNull()->defaultValue(1));
        $this->addColumn('redes.Proyecto', 'numeroVersionMenor', $this->integer()->notNull()->defaultValue(0));
        $this->addColumn('redes.Proyecto', 'esVersionVigente', $this->boolean()->notNull()->defaultValue(1));
        $this->addColumn('redes.Proyecto', 'motivoVersion', $this->string(1000)->null());

        $this->addForeignKey('fk-proyecto-idversionanterior', 'redes.Proyecto', 'idVersionAnterior', 'redes.Proyecto', 'id');

        $this->execute('DROP INDEX [ux-proyecto-numero-fase] ON redes.Proyecto');
        $this->execute('CREATE UNIQUE INDEX [ux-proyecto-numero-fase] ON redes.Proyecto (numeroProyecto, numeroFase, numeroOrdenCambio, numeroVersionMayor, numeroVersionMenor) WHERE numeroProyecto IS NOT NULL');
    }

    public function safeDown()
    {
        $versiones = (int) $this->db->createCommand('SELECT COUNT(*) FROM redes.Proyecto WHERE idVersionAnterior IS NOT NULL')->queryScalar();
        if ($versiones > 0) {
            throw new RuntimeException("Hay $versiones versión(es) de fase registradas: elimínelas antes de deshacer la migración.");
        }

        $this->execute('DROP INDEX [ux-proyecto-numero-fase] ON redes.Proyecto');
        $this->execute('CREATE UNIQUE INDEX [ux-proyecto-numero-fase] ON redes.Proyecto (numeroProyecto, numeroFase, numeroOrdenCambio) WHERE numeroProyecto IS NOT NULL');

        $this->dropForeignKey('fk-proyecto-idversionanterior', 'redes.Proyecto');
        $this->dropColumn('redes.Proyecto', 'motivoVersion');
        $this->dropColumn('redes.Proyecto', 'esVersionVigente');
        $this->dropColumn('redes.Proyecto', 'numeroVersionMenor');
        $this->dropColumn('redes.Proyecto', 'numeroVersionMayor');
        $this->dropColumn('redes.Proyecto', 'idVersionAnterior');
    }
}
