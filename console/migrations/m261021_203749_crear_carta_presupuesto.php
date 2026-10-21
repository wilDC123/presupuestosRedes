<?php

use yii\db\Migration;

/**
 * Carta de entrega del presupuesto (la nota con membrete que acompaña al
 * presupuesto, las especificaciones y las cotizaciones):
 *
 *  - redes.ConfiguracionCarta: UNA sola fila (CHECK id = 1) con los textos
 *    por defecto, editables por el encargado. Igual que las otras
 *    configuraciones (ConfiguracionPresupuesto, ConfiguracionSolicitudCotizacion).
 *  - redes.CartaPresupuesto: la carta de cada presupuesto (como maximo una,
 *    indice unico). A diferencia del PDF del presupuesto, la carta GUARDA su
 *    texto: se escribe en cualquier momento, se imprime despues, y una carta
 *    ya enviada no cambia si mas tarde se edita la configuracion. Lo unico
 *    que NO copia son el numero de oficio, el numero de presupuesto y los
 *    codigos NET: los marcadores {oficio}, {numeroPresupuesto} y
 *    {especificaciones} se reemplazan al imprimir, porque al escribir la
 *    carta pueden no existir todavia.
 */
class m261021_203749_crear_carta_presupuesto extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.ConfiguracionCarta', [
            'id' => $this->integer()->notNull(),
            'ciudad' => $this->string(60),
            // Como se escribe el numero de oficio; {oficio} y {anio} se reemplazan.
            'formatoOficio' => $this->string(100),
            'saludo' => $this->string(150),
            'plantillaReferencia' => $this->string(500),
            // Parrafos separados por una linea en blanco.
            'plantillaCuerpo' => $this->text(),
            'despedida' => $this->string(150),
            'copia' => $this->string(200),
            'plantillaAdjunto' => $this->string(300),
            // Firmas 1 y 2 lado a lado; la 3 (el V°B°) centrada debajo.
            'firma1Prefijo' => $this->string(20),
            'firma1Nombre' => $this->string(150),
            'firma1Cargo' => $this->string(200),
            'firma2Prefijo' => $this->string(20),
            'firma2Nombre' => $this->string(150),
            'firma2Cargo' => $this->string(200),
            'firma3Prefijo' => $this->string(20),
            'firma3Nombre' => $this->string(150),
            'firma3Cargo' => $this->string(200),
            'PRIMARY KEY ([[id]])',
        ]);
        $this->execute('ALTER TABLE redes.ConfiguracionCarta ADD CONSTRAINT [ck-configuracioncarta-unica] CHECK (id = 1)');

        // Textos de la carta modelo de la DTIC (DTIC Of. Nº 598/2026).
        $this->insert('redes.ConfiguracionCarta', [
            'id' => 1,
            'ciudad' => 'Sucre',
            'formatoOficio' => 'DTIC Of. Nº {oficio}/{anio}',
            'saludo' => 'De mi mayor consideración:',
            'plantillaReferencia' => 'ENTREGA DE PRESUPUESTO, ESPECIFICACIONES TÉCNICAS Y COTIZACIONES REFERENCIALES PARA {proyecto}',
            'plantillaCuerpo' => "En atención al requerimiento de {proyecto} e inspección técnica realizada en la {facultad}, mediante la presente se hace entrega del presupuesto, las especificaciones técnicas y las cotizaciones de referencia destinadas a la adquisición de los materiales necesarios para la ejecución de la instalación.\n\n"
                . "Una vez que se haya procedido con la adquisición de los materiales solicitados, pedimos a su autoridad tener a bien hacer conocer a la DTIC para proceder inmediatamente con la programación y ejecución de los trabajos de instalación correspondientes.\n\n"
                . 'Sin otro particular, quedamos a su entera disposición para cualquier aclaración técnica adicional.',
            'despedida' => 'Atentamente,',
            'copia' => 'Archivo.',
            // {especificaciones}: los codigos NET vigentes del proyecto, al imprimir.
            'plantillaAdjunto' => 'PRESUPUESTO: INSTALACIÓN DE REDES Nro: {numeroPresupuesto}, especificaciones técnicas {especificaciones} y cotización.',
            'firma1Prefijo' => null,
            'firma1Nombre' => 'Ing. Juan Carlos Valda Estrada',
            'firma1Cargo' => 'RESP. PROYECTOS Y SUPERVISIÓN DE INFRAESTRUCTURA DE REDES',
            'firma2Prefijo' => null,
            'firma2Nombre' => 'Ing. G. Jesús Serrudo Aparicio',
            'firma2Cargo' => 'RESP. REDES Y TELEC. D.T.I.C.',
            'firma3Prefijo' => 'V°B°',
            'firma3Nombre' => 'Ing. Anacleto Ortega Loayza',
            'firma3Cargo' => 'JEFE D.T.I.C.',
        ]);

        $this->createTable('redes.CartaPresupuesto', [
            'id' => $this->primaryKey(),
            'idPresupuesto' => $this->integer()->notNull(),
            'fecha' => $this->date()->notNull(),
            // Señor, Señora, Señores, Señoras (ver CartaPresupuesto::TRATAMIENTOS).
            'tratamiento' => $this->string(20)->notNull(),
            'destinatarioNombre' => $this->string(150)->notNull(),
            'destinatarioCargo' => $this->string(200)->notNull(),
            'referencia' => $this->string(500)->notNull(),
            'saludo' => $this->string(150),
            'cuerpo' => $this->text()->notNull(),
            'despedida' => $this->string(150),
            'firma1Prefijo' => $this->string(20),
            'firma1Nombre' => $this->string(150),
            'firma1Cargo' => $this->string(200),
            'firma2Prefijo' => $this->string(20),
            'firma2Nombre' => $this->string(150),
            'firma2Cargo' => $this->string(200),
            'firma3Prefijo' => $this->string(20),
            'firma3Nombre' => $this->string(150),
            'firma3Cargo' => $this->string(200),
            'copia' => $this->string(200),
            'adjunto' => $this->string(300),
            'fechaCreacion' => $this->dateTime()->notNull()->defaultExpression('GETDATE()'),
            'fechaModificacion' => $this->dateTime(),
        ]);
        // Una sola carta por presupuesto.
        $this->createIndex('ux-cartapresupuesto-idpresupuesto', 'redes.CartaPresupuesto', 'idPresupuesto', true);
        // Si se elimina el presupuesto (solo "en proceso"), su carta se va con el.
        $this->addForeignKey('fk-cartapresupuesto-idpresupuesto', 'redes.CartaPresupuesto', 'idPresupuesto', 'redes.Presupuesto', 'id', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('redes.CartaPresupuesto');
        $this->dropTable('redes.ConfiguracionCarta');
    }
}
