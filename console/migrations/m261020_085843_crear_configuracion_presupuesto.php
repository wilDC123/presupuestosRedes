<?php

use yii\db\Migration;

/**
 * redes.ConfiguracionPresupuesto: una sola fila con lo que lleva el PDF del
 * presupuesto DESPUES del total: una nota opcional, las dos firmas (prefijo,
 * nombre y cargo) y la banda de pie con los contactos (dos columnas, cada
 * una con titulo y renglones). Editable por el encargado porque los cargos
 * y los contactos cambian con el tiempo.
 *
 * Mismo patron que redes.ConfiguracionSolicitudCotizacion.
 */
class m261020_085843_crear_configuracion_presupuesto extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.ConfiguracionPresupuesto', [
            'id' => $this->integer()->notNull(),
            'textoNota' => $this->string(500),
            // Firmas, de izquierda a derecha. Prefijo opcional (ej. "V°B°").
            'firma1Prefijo' => $this->string(20),
            'firma1Nombre' => $this->string(150),
            'firma1Cargo' => $this->string(200),
            'firma2Prefijo' => $this->string(20),
            'firma2Nombre' => $this->string(150),
            'firma2Cargo' => $this->string(200),
            // Banda de pie: titulo y renglones (uno por linea) de cada columna.
            'pieIzquierdaTitulo' => $this->string(150),
            'pieIzquierdaLineas' => $this->string(500),
            'pieDerechaTitulo' => $this->string(150),
            'pieDerechaLineas' => $this->string(500),
            'PRIMARY KEY ([[id]])',
        ]);
        // CHECK id = 1: garantiza que la tabla tenga una sola fila.
        $this->execute('ALTER TABLE redes.ConfiguracionPresupuesto ADD CONSTRAINT [ck-configuracionpresupuesto-unica] CHECK (id = 1)');

        // Datos del presupuesto en Excel que usa hoy la DTIC (con la
        // ortografia corregida). El encargado los revisa en la pantalla.
        $this->insert('redes.ConfiguracionPresupuesto', [
            'id' => 1,
            'textoNota' => '*Para cada ítem se deben adjuntar las respectivas especificaciones técnicas y requisitos para su adquisición.',
            'firma1Prefijo' => null,
            'firma1Nombre' => 'Ing. Juan Carlos Valda Estrada',
            'firma1Cargo' => 'RESP. PROYECTOS Y SUPERVISIÓN DE INFRAESTRUCTURA DE REDES',
            'firma2Prefijo' => 'V°B°',
            'firma2Nombre' => 'Ing. G. Jesús Serrudo Aparicio',
            'firma2Cargo' => 'RESP. REDES Y TELEC. D.T.I.C.',
            'pieIzquierdaTitulo' => 'División de Tecnologías de Información y Comunicación',
            'pieIzquierdaLineas' => 'Teléfono: 6460220; interno: 5001',
            'pieDerechaTitulo' => 'Redes y Telecomunicaciones',
            'pieDerechaLineas' => "Internos: 5002, soporte técnico: 4007, 4009, 5004\nCorreo: redusfx.dtic@usfx.bo",
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('redes.ConfiguracionPresupuesto');
    }
}
