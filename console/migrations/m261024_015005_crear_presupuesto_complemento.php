<?php

use yii\db\Migration;

/**
 * redes.PresupuestoComplemento: las dos secciones SIN PRECIO que van al final
 * del PDF del presupuesto, despues del total (pedido del encargado el
 * 2026-10-23; solo "a veces" se llenan):
 *
 *  - tipo 'material': "Otros materiales y equipamiento que proveerá la
 *    D.T.I.C." (lo que la DTIC ya tiene y aporta: descripcion, cantidad,
 *    unidad, observaciones),
 *  - tipo 'trabajo': "Trabajos complementarios que deberá ser realizado por
 *    la unidad y/o unidades encargadas" (lo que hace OTRA unidad de la
 *    universidad: descripcion, unidad encargada, observaciones).
 *
 * No son items de la lista (no se cotizan, no llevan especificacion ni
 * partida y NO suman al total): por eso no van en redes.ProyectoItem. Cada
 * fila pertenece a un presupuesto (composicion: se borra con el) y se agrupa
 * en el PDF por su subtitulo ("grupo").
 */
class m261024_015005_crear_presupuesto_complemento extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.PresupuestoComplemento', [
            'id' => $this->primaryKey(),
            'idPresupuesto' => $this->integer()->notNull(),
            // 'material' o 'trabajo' (ver PresupuestoComplemento::TIPOS).
            'tipo' => $this->string(10)->notNull(),
            // Subtitulo de la seccion: "Cableado y materiales de red", "Instalaciones eléctricas"...
            'grupo' => $this->string(150)->notNull(),
            'descripcion' => $this->string(300)->notNull(),
            // Solo materiales.
            'cantidad' => $this->decimal(18, 2),
            // Abreviatura, como en ProyectoItem y PresupuestoDetalle.
            'unidadMedida' => $this->string(10),
            // Solo materiales, opcional: el item del subcatalogo del que se tomo el nombre.
            'idSubcatalogo' => $this->integer(),
            // Solo trabajos: "Mantenimiento eléctrico", "Infraestructura"...
            'unidadEncargada' => $this->string(150),
            'observacion' => $this->string(300),
            'orden' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->execute("ALTER TABLE redes.PresupuestoComplemento ADD CONSTRAINT [ck-presupuestocomplemento-tipo] CHECK (tipo IN ('material', 'trabajo'))");
        $this->createIndex('ix-presupuestocomplemento-idpresupuesto', 'redes.PresupuestoComplemento', ['idPresupuesto', 'tipo', 'orden']);
        // Si se elimina el presupuesto (solo "en proceso"), sus filas se van con el.
        $this->addForeignKey('fk-presupuestocomplemento-idpresupuesto', 'redes.PresupuestoComplemento', 'idPresupuesto', 'redes.Presupuesto', 'id', 'CASCADE');
        // El nombre ya esta copiado en "descripcion": si el item se elimina, solo se pierde el enlace.
        $this->addForeignKey('fk-presupuestocomplemento-idsubcatalogo', 'redes.PresupuestoComplemento', 'idSubcatalogo', 'redes.SubcatalogoItem', 'id', 'SET NULL');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('redes.PresupuestoComplemento');
    }
}
