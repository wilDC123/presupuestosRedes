<?php

use yii\db\Migration;

/**
 * ORDEN DE CAMBIO (revision del Ing. de DTIC del 2026-11-05, punto 4; parte 6;
 * formato RD-GD-FR-006): un presupuesto ADICIONAL dentro de una fase ya
 * aprobada ("se compraron 100 m de cable y piden 50 m mas: un presupuesto
 * solo de los 50 m"). Sigue todo el proceso (lista, especificaciones,
 * cotizacion, presupuesto, carta) solo con lo que se agrega.
 *
 * Decidido con el desarrollador el 2026-11-09 (opcion B): la orden es una
 * FILA MAS de redes.Proyecto, como las fases: mismo numeroProyecto y
 * numeroFase que su fase, mas:
 *  - idFaseOrigen: la fase (fila de Proyecto) a la que pertenece la orden,
 *  - numeroOrdenCambio: 1, 2... dentro de la fase (NULL = la fila es una fase),
 *  - descripcionCambio, motivoCambio, tipoCambio: lo que el sistema guarda del
 *    FR-006 (el formulario se sigue haciendo en Word; se le adjunta el
 *    presupuesto).
 * Asi la orden tiene sola su lista, sus ET, sus solicitudes, su presupuesto y
 * su carta, sin cambiar esas tablas.
 *
 * El indice unico (numeroProyecto, numeroFase) pasa a incluir
 * numeroOrdenCambio: una fase tiene una sola fila sin orden y sus ordenes 1, 2...
 */
class m261110_030000_orden_de_cambio extends Migration
{
    public function safeUp()
    {
        $this->addColumn('redes.Proyecto', 'idFaseOrigen', $this->integer()->null());
        $this->addColumn('redes.Proyecto', 'numeroOrdenCambio', $this->integer()->null());
        $this->addColumn('redes.Proyecto', 'descripcionCambio', $this->string(1000)->null());
        $this->addColumn('redes.Proyecto', 'motivoCambio', $this->string(1000)->null());
        $this->addColumn('redes.Proyecto', 'tipoCambio', $this->string(40)->null());

        $this->addForeignKey('fk-proyecto-idfaseorigen', 'redes.Proyecto', 'idFaseOrigen', 'redes.Proyecto', 'id');

        // Una orden siempre tiene su fase y su numero (las dos juntas o ninguna).
        $this->execute('ALTER TABLE redes.Proyecto ADD CONSTRAINT [ck-proyecto-ordencambio] CHECK ((idFaseOrigen IS NULL AND numeroOrdenCambio IS NULL) OR (idFaseOrigen IS NOT NULL AND numeroOrdenCambio >= 1))');

        $this->execute('DROP INDEX [ux-proyecto-numero-fase] ON redes.Proyecto');
        $this->execute('CREATE UNIQUE INDEX [ux-proyecto-numero-fase] ON redes.Proyecto (numeroProyecto, numeroFase, numeroOrdenCambio) WHERE numeroProyecto IS NOT NULL');
    }

    public function safeDown()
    {
        $ordenes = (int) $this->db->createCommand('SELECT COUNT(*) FROM redes.Proyecto WHERE idFaseOrigen IS NOT NULL')->queryScalar();
        if ($ordenes > 0) {
            throw new RuntimeException("Hay $ordenes orden(es) de cambio registradas: elimínelas antes de deshacer la migración.");
        }

        $this->execute('DROP INDEX [ux-proyecto-numero-fase] ON redes.Proyecto');
        $this->execute('CREATE UNIQUE INDEX [ux-proyecto-numero-fase] ON redes.Proyecto (numeroProyecto, numeroFase) WHERE numeroProyecto IS NOT NULL');
        $this->execute('ALTER TABLE redes.Proyecto DROP CONSTRAINT [ck-proyecto-ordencambio]');
        $this->dropForeignKey('fk-proyecto-idfaseorigen', 'redes.Proyecto');
        $this->dropColumn('redes.Proyecto', 'tipoCambio');
        $this->dropColumn('redes.Proyecto', 'motivoCambio');
        $this->dropColumn('redes.Proyecto', 'descripcionCambio');
        $this->dropColumn('redes.Proyecto', 'numeroOrdenCambio');
        $this->dropColumn('redes.Proyecto', 'idFaseOrigen');
    }
}
