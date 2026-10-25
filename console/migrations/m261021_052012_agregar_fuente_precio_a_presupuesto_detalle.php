<?php

use yii\db\Migration;

/**
 * Agrega a `redes.PresupuestoDetalle` la fuente de un PRECIO DIRECTO (el que
 * se escribe sin cotizacion): de donde salio ("compra 2025", "pagina del
 * proveedor", "Precio referencial SIGMA"). Opcional; NULL en las lineas que
 * vienen de una cotizacion, porque su respaldo ya es la cotizacion.
 */
class m261021_052012_agregar_fuente_precio_a_presupuesto_detalle extends Migration
{
    public function safeUp()
    {
        $this->addColumn('redes.PresupuestoDetalle', 'fuentePrecio', $this->string(200));
    }

    public function safeDown()
    {
        $this->dropColumn('redes.PresupuestoDetalle', 'fuentePrecio');
    }
}
