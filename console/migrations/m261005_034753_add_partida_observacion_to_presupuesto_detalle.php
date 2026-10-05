<?php

use yii\db\Migration;

/**
 * Agrega a `redes.PresupuestoDetalle` la partida presupuestaria congelada de
 * la linea y una observacion de texto libre.
 */
class m261005_034753_add_partida_observacion_to_presupuesto_detalle extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Mismo tipo que redes.SubcatalogoItem.partida (Yii2 traduce "char" a
        // "nchar" en SQL Server: queda nchar(5)). La linea guarda SU PROPIA
        // copia de la partida para que quede congelada: si despues cambia la
        // partida del item en el subcatalogo, los presupuestos ya hechos no
        // se alteran.
        $this->addColumn('redes.PresupuestoDetalle', 'partida', 'char(5) NULL');

        // Texto libre de la linea: medidas ("12mm x 4 metros"), "segun
        // especificacion tecnica", etc. $this->string(300) genera
        // nvarchar(300), igual que el resto de textos del esquema redes.
        $this->addColumn('redes.PresupuestoDetalle', 'observacion', $this->string(300));

        // Relleno de las lineas que ya existian, con la partida actual de su
        // item. Si no hay lineas, el UPDATE afecta 0 filas y no falla.
        $this->execute(
            'UPDATE d
                SET d.partida = s.partida
               FROM redes.PresupuestoDetalle d
              INNER JOIN redes.SubcatalogoItem s ON s.id = d.idSubcatalogo
              WHERE s.partida IS NOT NULL'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('redes.PresupuestoDetalle', 'observacion');
        $this->dropColumn('redes.PresupuestoDetalle', 'partida');
    }
}
