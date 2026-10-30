<?php

use yii\db\Migration;

/**
 * Procesos de Redes y Telecomunicaciones (redes.Proceso), segun la tabla 4
 * "Codificacion de procesos" (8.5) del lineamiento RD-GD-LI-001. Pedido del
 * Ing. de DTIC en la revision del 2026-10-29: todo lo que se presupuesta va
 * como proyecto, pero no siempre lo hace el proceso de proyectos (PI); puede
 * venir de despliegue (DS), de gestion de la red (GR), etc. Por eso cada
 * proyecto dice de que proceso es.
 *
 * Se administra desde el sistema ("Procesos" en Configuracion): el Ing.
 * anuncio que se crearan procesos nuevos.
 *
 *  - codigo: dos letras mayusculas (lineamiento 8.5). Formara parte del
 *    codigo de los documentos (RD-PI-PR-001), por eso es unico.
 *  - Proyecto.idProceso: FK. Los proyectos que ya existen quedan en PI, que
 *    es el proceso con el que se codificaron hasta hoy.
 */
class m261030_002958_crear_proceso extends Migration
{
    /**
     * Tabla 4 del lineamiento: codigo => nombre.
     */
    private const PROCESOS = [
        'GD' => 'Gestión de documentos',
        'DC' => 'Dirección y coordinación de proyectos, despliegue y gestión de la red universitaria',
        'PI' => 'Proyectos de infraestructura de redes y servicios',
        'DS' => 'Despliegue y soporte redes',
        'GR' => 'Gestión de la red universitaria',
    ];

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.Proceso', [
            'id' => $this->primaryKey(),
            'codigo' => $this->string(2)->notNull(),
            'nombre' => $this->string(200)->notNull(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
        ]);
        $this->createIndex('ux-proceso-codigo', 'redes.Proceso', 'codigo', true);
        $this->createIndex('ux-proceso-nombre', 'redes.Proceso', 'nombre', true);

        foreach (self::PROCESOS as $codigo => $nombre) {
            $this->insert('redes.Proceso', ['codigo' => $codigo, 'nombre' => $nombre]);
        }

        // NULL permitido en la base: lo exige el modelo al guardar desde el
        // formulario (como idTipoProyecto).
        $this->addColumn('redes.Proyecto', 'idProceso', $this->integer()->null());
        // Sin ON DELETE: borrar un proceso en uso debe fallar.
        $this->addForeignKey('fk-proyecto-idproceso', 'redes.Proyecto', 'idProceso', 'redes.Proceso', 'id');
        // SQL Server no crea solo el indice de una FK.
        $this->createIndex('idx-proyecto-idproceso', 'redes.Proyecto', 'idProceso');

        $this->execute("UPDATE redes.Proyecto SET idProceso = (SELECT id FROM redes.Proceso WHERE codigo = 'PI')");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-proyecto-idproceso', 'redes.Proyecto');
        $this->dropForeignKey('fk-proyecto-idproceso', 'redes.Proyecto');
        $this->dropColumn('redes.Proyecto', 'idProceso');
        $this->dropTable('redes.Proceso');
    }
}
