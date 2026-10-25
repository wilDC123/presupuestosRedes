<?php

use yii\db\Migration;

/**
 * Agrega a `redes.Presupuesto` la mano de obra a cargo del personal de la
 * DTIC: un texto que el PDF muestra como la ultima fila de la tabla (unidad
 * "Servicio", cantidad 1, precios en 0.00). NULL = el presupuesto no lleva
 * esa fila.
 *
 * No es un item del subcatalogo a proposito: no se compra, asi que no tiene
 * partida, no se cotiza y no suma al total.
 */
class m261020_084559_agregar_mano_de_obra_a_presupuesto extends Migration
{
    public function safeUp()
    {
        $this->addColumn('redes.Presupuesto', 'manoDeObra', $this->string(300));
    }

    public function safeDown()
    {
        $this->dropColumn('redes.Presupuesto', 'manoDeObra');
    }
}
