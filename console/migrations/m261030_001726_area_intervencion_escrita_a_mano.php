<?php

use yii\db\Migration;

/**
 * Areas de intervencion escritas a mano (revision con el Ing. de DTIC del
 * 2026-10-29): un edificio en construccion, o uno que todavia no se registro
 * en UsfxGEO, no aparece en la lista, pero el proyecto igual se hace ahi.
 * Tambien un bloque o un espacio nuevo de un edificio que si existe.
 *
 * Un area escrita a mano no tiene ningun id de UsfxGEO: idEdificio pasa a
 * aceptar NULL, y NULL quiere decir "a mano". Los nombres (edificio, bloque,
 * nivel, ambiente) se guardan igual que en las demas.
 */
class m261030_001726_area_intervencion_escrita_a_mano extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn('redes.ProyectoAreaIntervencion', 'idEdificio', $this->string(10)->null());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // No se borran datos para poder volver atras: se detiene.
        $aMano = (int) $this->db->createCommand('SELECT COUNT(*) FROM redes.ProyectoAreaIntervencion WHERE idEdificio IS NULL')->queryScalar();

        if ($aMano > 0) {
            echo "    > Hay $aMano areas escritas a mano: quitelas de sus proyectos antes de revertir.\n";

            return false;
        }

        $this->alterColumn('redes.ProyectoAreaIntervencion', 'idEdificio', $this->string(10)->notNull());
    }
}
