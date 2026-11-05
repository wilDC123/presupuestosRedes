<?php

use yii\db\Migration;

/**
 * Tratamientos de la carta de entrega (redes.TratamientoCarta): "Señor",
 * "Señora"… con el que se encabeza al destinatario. Hasta el 2026-11-04
 * eran 4 fijos en el codigo (CartaPresupuesto::TRATAMIENTOS); desde ahora el
 * encargado los administra en "Configuración de la carta".
 *
 *  - nombre: tal como sale en la carta ("Señora:"), unico.
 *  - femenino: el cargo sugerido va con DECANA en vez de DECANO.
 *  - orden: el de la lista; el primero es el de una carta nueva.
 *
 * Sin FK desde CartaPresupuesto: cada carta guarda una COPIA del texto, asi
 * que quitar o corregir un tratamiento no cambia las cartas ya escritas.
 */
class m261104_233000_crear_tratamiento_carta extends Migration
{
    /**
     * Los 4 que estaban en el codigo: nombre => femenino.
     */
    private const TRATAMIENTOS = [
        'Señor' => 0,
        'Señora' => 1,
        'Señores' => 0,
        'Señoras' => 1,
    ];

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.TratamientoCarta', [
            'id' => $this->primaryKey(),
            // Mismo largo que CartaPresupuesto.tratamiento.
            'nombre' => $this->string(20)->notNull(),
            'femenino' => $this->boolean()->notNull()->defaultValue(0),
            'orden' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->createIndex('ux-tratamientocarta-nombre', 'redes.TratamientoCarta', 'nombre', true);

        $orden = 0;
        foreach (self::TRATAMIENTOS as $nombre => $femenino) {
            $this->insert('redes.TratamientoCarta', ['nombre' => $nombre, 'femenino' => $femenino, 'orden' => ++$orden]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('redes.TratamientoCarta');
    }
}
