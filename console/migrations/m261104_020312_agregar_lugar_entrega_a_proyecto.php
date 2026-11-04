<?php

use yii\db\Migration;

/**
 * Lugar de entrega del proyecto (2026-11-03, pedido del Ing. de DTIC el
 * 2026-10-26: "Lugar de entrega: Administración de la ... facultad", pero no
 * todo se entrega en una facultad). Cada proyecto dice donde se entregan sus
 * bienes; los formularios de especificacion que se emitan lo copian (con
 * prioridad sobre el texto de la configuracion del formulario, que queda como
 * valor para los proyectos sin lugar). Las emisiones ya hechas conservan el
 * suyo.
 *
 * Los proyectos cuya unidad / dependencia empieza con "Facultad" reciben
 * "Administración de la <unidad>", lo mismo que sugiere el formulario
 * (Proyecto::lugarEntregaSugerido()); el resto queda vacio.
 */
class m261104_020312_agregar_lugar_entrega_a_proyecto extends Migration
{
    public function safeUp()
    {
        $this->addColumn('redes.Proyecto', 'lugarEntrega', $this->string(300)->null());

        $this->execute(<<<'SQL'
            UPDATE redes.Proyecto
            SET lugarEntrega = N'Administración de la ' + LTRIM(RTRIM(unidadDependencia))
            WHERE LTRIM(unidadDependencia) LIKE N'Facultad%'
            SQL);
    }

    public function safeDown()
    {
        $this->dropColumn('redes.Proyecto', 'lugarEntrega');
    }
}
