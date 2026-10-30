<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Las secciones sin precio pasan del presupuesto a la lista de items
 * (revision del Ing. de DTIC del 2026-10-29, paso 8a): "el presupuesto es
 * financiero: si no tiene cantidades y montos, no puede ir". Lo que estaba
 * al final del presupuesto (redes.PresupuestoComplemento, del 2026-10-23) se
 * escribe ahora debajo de la lista y es del PROYECTO:
 *
 *  - redes.ProyectoMaterialDtic: "Otros materiales y equipamiento que
 *    proveerá la D.T.I.C.", con las mismas columnas que antes (subtitulo,
 *    item del subcatalogo opcional, descripcion, cantidad, unidad) salvo
 *    las observaciones, que se quitaron (2026-10-30).
 *  - redes.ProyectoCondicionPrevia: los "Trabajos complementarios..."
 *    (condiciones y requerimientos previos): tipo, descripcion y unidad
 *    encargada, sin observaciones. El tipo ya no es uno de dos subtitulos fijos:
 *    sale de redes.TipoCondicionPrevia, que el encargado puede ampliar ("que
 *    haya algo para crear"): instalaciones electricas, obras civiles,
 *    apertura de ductos, despeje del ambiente.
 *  - Lo que habia en redes.PresupuestoComplemento se pasa al proyecto de su
 *    presupuesto y la tabla se elimina. Una observacion que tuviera se
 *    agrega al final de la descripcion, para no perderla.
 */
class m261030_035251_secciones_sin_precio_a_la_lista extends Migration
{
    /** Tipos iniciales: los dos subtitulos fijos de antes y los que nombro el Ing. */
    private const TIPOS = [
        'Instalaciones eléctricas',
        'Obras civiles',
        'Apertura de ductos',
        'Despeje del ambiente',
    ];

    /** Subtitulo de los trabajos de antes => tipo nuevo. */
    private const GRUPO_A_TIPO = [
        'Instalaciones eléctricas' => 'Instalaciones eléctricas',
        'Otras obras civiles requeridas' => 'Obras civiles',
    ];

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.ProyectoMaterialDtic', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            // Subtitulo de la seccion: "Cableado y materiales de red"...
            'grupo' => $this->string(150)->notNull(),
            // Opcional: el item del subcatalogo del que se tomo el nombre.
            'idSubcatalogo' => $this->integer()->null(),
            'descripcion' => $this->string(300)->notNull(),
            'cantidad' => $this->decimal(18, 2)->notNull(),
            // Abreviatura, como en ProyectoItem.
            'unidadMedida' => $this->string(10)->null(),
            'orden' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->addForeignKey('fk-proyectomaterialdtic-idproyecto', 'redes.ProyectoMaterialDtic', 'idProyecto', 'redes.Proyecto', 'id', 'CASCADE');
        // El nombre ya esta copiado en "descripcion": si el item se elimina, solo se pierde el enlace.
        $this->addForeignKey('fk-proyectomaterialdtic-idsubcatalogo', 'redes.ProyectoMaterialDtic', 'idSubcatalogo', 'redes.SubcatalogoItem', 'id', 'SET NULL');
        $this->createIndex('idx-proyectomaterialdtic-idproyecto', 'redes.ProyectoMaterialDtic', ['idProyecto', 'orden']);
        $this->createIndex('idx-proyectomaterialdtic-idsubcatalogo', 'redes.ProyectoMaterialDtic', 'idSubcatalogo');

        $this->createTable('redes.TipoCondicionPrevia', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(150)->notNull(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
        ]);
        $this->createIndex('ux-tipocondicionprevia-nombre', 'redes.TipoCondicionPrevia', 'nombre', true);
        foreach (self::TIPOS as $nombre) {
            $this->insert('redes.TipoCondicionPrevia', ['nombre' => $nombre]);
        }

        $this->createTable('redes.ProyectoCondicionPrevia', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'idTipo' => $this->integer()->notNull(),
            'descripcion' => $this->string(300)->notNull(),
            // "Infraestructura", "Mantenimiento eléctrico", "Facultad"... (texto libre).
            'unidadEncargada' => $this->string(150)->null(),
            'orden' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->addForeignKey('fk-proyectocondicionprevia-idproyecto', 'redes.ProyectoCondicionPrevia', 'idProyecto', 'redes.Proyecto', 'id', 'CASCADE');
        // Sin ON DELETE: un tipo en uso no se borra.
        $this->addForeignKey('fk-proyectocondicionprevia-idtipo', 'redes.ProyectoCondicionPrevia', 'idTipo', 'redes.TipoCondicionPrevia', 'id');
        $this->createIndex('idx-proyectocondicionprevia-idproyecto', 'redes.ProyectoCondicionPrevia', ['idProyecto', 'orden']);
        $this->createIndex('idx-proyectocondicionprevia-idtipo', 'redes.ProyectoCondicionPrevia', 'idTipo');

        $this->moverComplementos();

        $this->dropTable('redes.PresupuestoComplemento');
    }

    /**
     * Cada fila de redes.PresupuestoComplemento pasa al proyecto de su
     * presupuesto, en el mismo orden.
     */
    private function moverComplementos(): void
    {
        $tipos = (new Query())->select(['id', 'nombre'])->from('redes.TipoCondicionPrevia')->indexBy('nombre')->column($this->db);
        $filas = (new Query())
            ->select(['c.*', 'p.idProyecto'])
            ->from(['c' => 'redes.PresupuestoComplemento'])
            ->innerJoin(['p' => 'redes.Presupuesto'], 'p.id = c.idPresupuesto')
            ->orderBy(['p.idProyecto' => SORT_ASC, 'c.tipo' => SORT_ASC, 'c.orden' => SORT_ASC, 'c.id' => SORT_ASC])
            ->all($this->db);

        foreach ($filas as $fila) {
            $descripcion = $fila['descripcion'];
            if (trim((string) $fila['observacion']) !== '') {
                $descripcion = mb_substr($descripcion . ' (' . trim($fila['observacion']) . ')', 0, 300, 'UTF-8');
            }

            if ($fila['tipo'] === 'trabajo') {
                $this->insert('redes.ProyectoCondicionPrevia', [
                    'idProyecto' => $fila['idProyecto'],
                    'idTipo' => $tipos[self::GRUPO_A_TIPO[$fila['grupo']] ?? 'Obras civiles'],
                    'descripcion' => $descripcion,
                    'unidadEncargada' => $fila['unidadEncargada'],
                    'orden' => $fila['orden'],
                ]);
                continue;
            }

            $this->insert('redes.ProyectoMaterialDtic', [
                'idProyecto' => $fila['idProyecto'],
                'grupo' => $fila['grupo'],
                'idSubcatalogo' => $fila['idSubcatalogo'],
                'descripcion' => $descripcion,
                'cantidad' => $fila['cantidad'],
                'unidadMedida' => $fila['unidadMedida'],
                'orden' => $fila['orden'],
            ]);
        }
    }

    /**
     * {@inheritdoc}
     *
     * Vuelve a crear redes.PresupuestoComplemento VACIA: lo escrito en la
     * lista no vuelve al presupuesto.
     */
    public function safeDown()
    {
        $this->createTable('redes.PresupuestoComplemento', [
            'id' => $this->primaryKey(),
            'idPresupuesto' => $this->integer()->notNull(),
            'tipo' => $this->string(10)->notNull(),
            'grupo' => $this->string(150)->notNull(),
            'descripcion' => $this->string(300)->notNull(),
            'cantidad' => $this->decimal(18, 2),
            'unidadMedida' => $this->string(10),
            'idSubcatalogo' => $this->integer(),
            'unidadEncargada' => $this->string(150),
            'observacion' => $this->string(300),
            'orden' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->execute("ALTER TABLE redes.PresupuestoComplemento ADD CONSTRAINT [ck-presupuestocomplemento-tipo] CHECK (tipo IN ('material', 'trabajo'))");
        $this->createIndex('ix-presupuestocomplemento-idpresupuesto', 'redes.PresupuestoComplemento', ['idPresupuesto', 'tipo', 'orden']);
        $this->addForeignKey('fk-presupuestocomplemento-idpresupuesto', 'redes.PresupuestoComplemento', 'idPresupuesto', 'redes.Presupuesto', 'id', 'CASCADE');
        $this->addForeignKey('fk-presupuestocomplemento-idsubcatalogo', 'redes.PresupuestoComplemento', 'idSubcatalogo', 'redes.SubcatalogoItem', 'id', 'SET NULL');

        $this->dropTable('redes.ProyectoCondicionPrevia');
        $this->dropTable('redes.TipoCondicionPrevia');
        $this->dropTable('redes.ProyectoMaterialDtic');
    }
}
