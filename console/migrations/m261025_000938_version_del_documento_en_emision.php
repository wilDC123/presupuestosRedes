<?php

use yii\db\Migration;

/**
 * Version del documento en el formulario de especificacion tecnica, como
 * define el lineamiento de la DTIC RD-GD-LI-001 (4.2): "numero de cambios que
 * se han desarrollado en un documento; es consecutivo e inicia con version 01".
 *
 * Desde ahora, REEMITIR conserva el codigo y sube la version
 * (RD-PI-ET-015 v01 -> RD-PI-ET-015 v02); antes cada reemision tomaba un
 * codigo nuevo del correlativo. Cada version sigue siendo su propia fila (la
 * anterior queda "reemplazada" y congelada), por eso el indice unico pasa de
 * (serie, numeroNet) a (serie, numeroNet, versionDocumento).
 *
 * La columna se llama versionDocumento y no "version" porque la emision ya
 * tiene la relacion getVersion() (la version de la FICHA de la libreria).
 *
 * Las emisiones que ya se reemitieron con codigo nuevo quedan como estan
 * (version 1 cada una): se entregaron impresas con esos codigos.
 */
class m261025_000938_version_del_documento_en_emision extends Migration
{
    private const TABLA = 'redes.EspecificacionEmision';

    public function safeUp()
    {
        $this->addColumn(self::TABLA, 'versionDocumento', $this->integer()->notNull()->defaultValue(1));
        $this->execute('ALTER TABLE redes.EspecificacionEmision ADD CONSTRAINT [ck-especificacionemision-versiondocumento] CHECK (versionDocumento >= 1)');

        $this->dropIndex('ux-especificacionemision-serie-numero', self::TABLA);
        $this->createIndex('ux-especificacionemision-serie-numero-version', self::TABLA, ['serie', 'numeroNet', 'versionDocumento'], true);
    }

    public function safeDown()
    {
        // Sin la version, dos filas quedarian con el mismo codigo.
        $reemitidas = (new \yii\db\Query())->from(self::TABLA)->where(['>', 'versionDocumento', 1])->count();
        if ((int) $reemitidas > 0) {
            echo "Hay $reemitidas especificaciones reemitidas con el mismo codigo (version 02 o mas): no se puede quitar la version.\n";

            return false;
        }

        $this->dropIndex('ux-especificacionemision-serie-numero-version', self::TABLA);
        $this->createIndex('ux-especificacionemision-serie-numero', self::TABLA, ['serie', 'numeroNet'], true);

        $this->execute('ALTER TABLE redes.EspecificacionEmision DROP CONSTRAINT [ck-especificacionemision-versiondocumento]');
        $this->dropColumn(self::TABLA, 'versionDocumento');
    }
}
