<?php

use yii\db\Migration;

/**
 * El PDF del presupuesto ya no lleva la banda de pie con los contactos
 * (revision del Ing. de DTIC del 2026-10-29, paso 9): "si es sistema de
 * gestion documental no tiene pie de pagina; toda esta informacion esta en la
 * nota" (el membrete de la carta de entrega). Se quitan sus cuatro columnas
 * de redes.ConfiguracionPresupuesto.
 *
 * safeDown() vuelve a crear las columnas VACIAS: los textos que tenian no se
 * recuperan (estan en el membrete de la carta).
 */
class m261030_162507_quitar_pie_de_configuracion_presupuesto extends Migration
{
    private const COLUMNAS = [
        'pieIzquierdaTitulo' => 150,
        'pieIzquierdaLineas' => 500,
        'pieDerechaTitulo' => 150,
        'pieDerechaLineas' => 500,
    ];

    public function safeUp()
    {
        foreach (array_keys(self::COLUMNAS) as $columna) {
            $this->dropColumn('redes.ConfiguracionPresupuesto', $columna);
        }
    }

    public function safeDown()
    {
        foreach (self::COLUMNAS as $columna => $largo) {
            $this->addColumn('redes.ConfiguracionPresupuesto', $columna, $this->string($largo));
        }
    }
}
