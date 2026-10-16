<?php

use yii\db\Migration;

/**
 * El presupuesto pasa a ser un "documento vivo": cada item de la lista tiene
 * su linea y elegir otro precio reemplaza esa linea.
 *
 *  - justificacion: por que se eligio un precio que NO es el menor cotizado
 *    (PresupuestoService la exige en ese caso; en la base es opcional).
 *  - indice unico sobre idProyectoItem: como maximo UNA linea por item de
 *    la lista, garantizado por la base y no solo por el codigo.
 */
class m261016_012942_justificacion_y_linea_unica_presupuesto extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('redes.PresupuestoDetalle', 'justificacion', $this->string(300)->null());

        // Si ya hubiera un item con dos lineas, el indice no se podria crear:
        // se detiene aqui con un mensaje en vez del error de SQL Server.
        $repetidos = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM (SELECT idProyectoItem FROM redes.PresupuestoDetalle
             WHERE idProyectoItem IS NOT NULL GROUP BY idProyectoItem HAVING COUNT(*) > 1) t'
        )->queryScalar();

        if ($repetidos > 0) {
            echo "No se puede aplicar: $repetidos ítem(s) de la lista tienen más de una línea en el presupuesto.\n";
            echo "Quite las líneas repetidas y vuelva a intentar.\n";

            return false;
        }

        // Indice FILTRADO (WHERE ... IS NOT NULL): Yii no tiene un metodo
        // para esto, por eso va en SQL. Sin el filtro, SQL Server trataria
        // a todos los NULL como un mismo valor repetido.
        $this->execute(
            'CREATE UNIQUE INDEX [ux-presupuestodetalle-idproyectoitem]
             ON redes.PresupuestoDetalle (idProyectoItem)
             WHERE idProyectoItem IS NOT NULL'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('ux-presupuestodetalle-idproyectoitem', 'redes.PresupuestoDetalle');
        $this->dropColumn('redes.PresupuestoDetalle', 'justificacion');
    }
}
