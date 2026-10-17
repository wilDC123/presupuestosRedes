<?php

use yii\db\Migration;

/**
 * Especificaciones tecnicas con VERSIONES y EMISIONES NET (ver
 * docs/plan-especificaciones.md, paso 1).
 *
 *  - redes.EspecificacionVersion: v1, v2... de una ficha de la libreria. Una
 *    version que ya se uso en una emision no se modifica nunca (lo controla el
 *    codigo): cambiar la ficha crea la version siguiente.
 *  - redes.EspecificacionCaracteristica: las filas tecnicas de una version.
 *  - redes.EspecificacionEmision: el documento con codigo NET que se firma,
 *    se sella y se entrega al proveedor. Nunca cambia: un error se corrige
 *    emitiendo otro con NET nuevo.
 *  - redes.ConfiguracionEspecificacion: una sola fila con el siguiente NET y
 *    los textos fijos del formulario, editables por el encargado.
 *  - redes.CotizacionDetalle.idEspecificacionEmision: el NET contra el que
 *    cotizo el proveedor.
 *
 * Esta migracion SOLO AGREGA. Las columnas viejas de redes.Especificacion
 * (codigoNet, dependencia, fechaEmision, modeloSugerido, documentoRuta) y
 * CotizacionDetalle.idEspecificacion se quitan en una migracion posterior,
 * cuando el codigo ya no las use; asi la aplicacion sigue funcionando
 * mientras tanto.
 */
class m261017_231816_versiones_y_emisiones_especificacion extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // --- Versiones de la ficha ---
        $this->createTable('redes.EspecificacionVersion', [
            'id' => $this->primaryKey(),
            'idEspecificacion' => $this->integer()->notNull(),
            'numero' => $this->integer()->notNull(),
            'modeloSugerido' => $this->string(200),
            'garantia' => $this->string(500),
            'adjuntoRuta' => $this->string(500),
            'notaCambio' => $this->string(300),
            'fechaCreacion' => $this->dateTime()->notNull()->defaultExpression('GETDATE()'),
        ]);
        // CASCADE: una version no existe sin su ficha. Igual, una ficha con
        // emisiones no se puede borrar: la FK de la emision lo impide.
        $this->addForeignKey('fk-especificacionversion-idespecificacion', 'redes.EspecificacionVersion', 'idEspecificacion', 'redes.Especificacion', 'id', 'CASCADE');
        // Unico: no puede haber dos "v2" de la misma ficha. Este indice
        // tambien sirve para buscar las versiones de una ficha.
        $this->createIndex('ux-especificacionversion-ficha-numero', 'redes.EspecificacionVersion', ['idEspecificacion', 'numero'], true);

        // --- Caracteristicas (filas tecnicas) de una version ---
        $this->createTable('redes.EspecificacionCaracteristica', [
            'id' => $this->primaryKey(),
            'idVersion' => $this->integer()->notNull(),
            'orden' => $this->integer()->notNull()->defaultValue(0),
            // nombre es opcional: en el banco hay filas que son un solo texto
            // ("Cumple con las normas ANSI/TIA...").
            'nombre' => $this->string(200),
            // text() = nvarchar(max) en SQL Server: algunas filas del banco
            // tienen varios parrafos.
            'valor' => $this->text()->notNull(),
        ]);
        $this->addForeignKey('fk-especificacioncaracteristica-idversion', 'redes.EspecificacionCaracteristica', 'idVersion', 'redes.EspecificacionVersion', 'id', 'CASCADE');
        $this->createIndex('idx-especificacioncaracteristica-idversion', 'redes.EspecificacionCaracteristica', 'idVersion');

        // --- Emisiones NET ---
        $this->createTable('redes.EspecificacionEmision', [
            'id' => $this->primaryKey(),
            'numeroNet' => $this->integer()->notNull(),
            // Dos digitos del año de emision: NET:880/26.
            'anio' => $this->smallInteger()->notNull(),
            'idVersion' => $this->integer()->notNull(),
            'idProyecto' => $this->integer()->notNull(),
            // NULL si despues se borro el item de la lista: el documento
            // firmado no desaparece, queda anulado con su copia de los datos.
            'idProyectoItem' => $this->integer()->null(),
            // Copias CONGELADAS al emitir (igual que el precio en el presupuesto).
            'titulo' => $this->string(300)->notNull(),
            'cantidad' => $this->decimal(10, 2)->notNull(),
            'unidad' => $this->string(20),
            'dependencia' => $this->string(200),
            'validezOfertaDias' => $this->integer(),
            'tiempoEntregaDias' => $this->integer(),
            'lugarEntrega' => $this->string(300),
            'elaboradoPor' => $this->string(150),
            'textoCabecera' => $this->string(500),
            'textoInstrucciones' => $this->string(1000),
            'textoNotaFinal' => $this->string(500),
            'fechaEmision' => $this->date()->notNull(),
            'vigenciaDias' => $this->integer()->notNull(),
            'estado' => $this->string(20)->notNull()->defaultValue('vigente'),
            'idReemplazadaPor' => $this->integer()->null(),
            'motivo' => $this->string(300),
            'fechaRegistro' => $this->dateTime()->notNull()->defaultExpression('GETDATE()'),
        ]);
        $this->execute(
            "ALTER TABLE redes.EspecificacionEmision ADD CONSTRAINT [ck-especificacionemision-estado]
             CHECK (estado IN ('vigente', 'reemplazada', 'anulada'))"
        );
        // Sin ON DELETE en version y proyecto: borrar algo que tiene un
        // documento oficial emitido debe fallar, no borrarlo en silencio.
        $this->addForeignKey('fk-especificacionemision-idversion', 'redes.EspecificacionEmision', 'idVersion', 'redes.EspecificacionVersion', 'id');
        $this->addForeignKey('fk-especificacionemision-idproyecto', 'redes.EspecificacionEmision', 'idProyecto', 'redes.Proyecto', 'id');
        $this->addForeignKey('fk-especificacionemision-idproyectoitem', 'redes.EspecificacionEmision', 'idProyectoItem', 'redes.ProyectoItem', 'id', 'SET NULL');
        $this->addForeignKey('fk-especificacionemision-idreemplazadapor', 'redes.EspecificacionEmision', 'idReemplazadaPor', 'redes.EspecificacionEmision', 'id');

        // El NET es unico en toda la historia (el correlativo no se reinicia
        // por año): la base impide repetirlo aunque el codigo fallara.
        $this->createIndex('ux-especificacionemision-numeronet', 'redes.EspecificacionEmision', 'numeroNet', true);
        $this->createIndex('idx-especificacionemision-idversion', 'redes.EspecificacionEmision', 'idVersion');
        $this->createIndex('idx-especificacionemision-idproyecto', 'redes.EspecificacionEmision', 'idProyecto');
        // Como maximo UNA emision vigente por item de la lista. Indice
        // FILTRADO (Yii no tiene metodo para esto): las reemplazadas y
        // anuladas del mismo item no cuentan.
        $this->execute(
            "CREATE UNIQUE INDEX [ux-especificacionemision-item-vigente]
             ON redes.EspecificacionEmision (idProyectoItem)
             WHERE estado = 'vigente' AND idProyectoItem IS NOT NULL"
        );

        // --- Configuracion (una sola fila) ---
        $this->createTable('redes.ConfiguracionEspecificacion', [
            'id' => $this->integer()->notNull(),
            // NULL = el encargado todavia no fijo el NET inicial; el sistema
            // no emite hasta que lo haga.
            'siguienteNet' => $this->integer()->null(),
            'textoCabecera' => $this->string(500),
            'textoInstrucciones' => $this->string(1000),
            'garantiaPorDefecto' => $this->string(500),
            'textoNotaFinal' => $this->string(500),
            'vigenciaDias' => $this->integer()->notNull()->defaultValue(30),
            'validezOfertaDias' => $this->integer()->notNull()->defaultValue(10),
            'tiempoEntregaDias' => $this->integer()->notNull()->defaultValue(5),
            'PRIMARY KEY ([[id]])',
        ]);
        // CHECK id = 1: garantiza que la tabla tenga una sola fila.
        $this->execute('ALTER TABLE redes.ConfiguracionEspecificacion ADD CONSTRAINT [ck-configuracionespecificacion-unica] CHECK (id = 1)');

        // Textos iniciales tomados de los formularios 2026 del banco de DTIC
        // (NET:843/26 y NET:880/26), tal cual estan; se editan desde el sistema.
        $this->insert('redes.ConfiguracionEspecificacion', [
            'id' => 1,
            'siguienteNet' => null,
            'textoCabecera' => "Universidad Mayor, Real y Pontificia de \u{201C}San Francisco Xavier\u{201D} de Chuquisaca\nD.T.I.C.",
            'textoInstrucciones' => "Los proponentes deberán llenar solamente la columna \u{201C}PROPUESTA\u{201D} de éste formulario, consignando su propuesta técnica de acuerdo a las especificaciones técnicas solicitados por la entidad Convocante en la columna de \"ESPECIFICACIONES TÉCNICAS\". No se tomarán en cuenta bienes reacondicionados.",
            'garantiaPorDefecto' => 'Garantías de buen funcionamiento: Como mínimo 1 año, adjuntar certificado de buen funcionamiento, soporte técnico mientras dure la garantía: adjuntar dirección y teléfono del soporte técnico en la Ciudad de Sucre.',
            'textoNotaFinal' => 'IMPORTANTE: Señor proveedor ser evaluado es necesario que llene este formulario, además de colocar su sello y firma.',
            'vigenciaDias' => 30,
            'validezOfertaDias' => 10,
            'tiempoEntregaDias' => 5,
        ]);

        // --- Cotizacion contra un NET concreto ---
        $this->addColumn('redes.CotizacionDetalle', 'idEspecificacionEmision', $this->integer()->null());
        $this->addForeignKey('fk-cotizaciondetalle-idespecificacionemision', 'redes.CotizacionDetalle', 'idEspecificacionEmision', 'redes.EspecificacionEmision', 'id');
        $this->createIndex('idx-cotizaciondetalle-idespecificacionemision', 'redes.CotizacionDetalle', 'idEspecificacionEmision');

        // --- Datos existentes: cada ficha pasa a tener su v1 ---
        // Se copian modeloSugerido y documentoRuta (que ahora son de la
        // version). No se crean emisiones: los codigos NET viejos se cargaron
        // a mano y no salieron del correlativo del sistema.
        $this->execute(
            'INSERT INTO redes.EspecificacionVersion (idEspecificacion, numero, modeloSugerido, adjuntoRuta, notaCambio)
             SELECT id, 1, modeloSugerido, documentoRuta, N\'Versión inicial (migrada)\'
             FROM redes.Especificacion'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Revertir borraria documentos NET emitidos: si hay alguno, se
        // detiene aqui sin tocar nada.
        $emisiones = (int) $this->db->createCommand('SELECT COUNT(*) FROM redes.EspecificacionEmision')->queryScalar();

        if ($emisiones > 0) {
            echo "No se puede revertir: hay $emisiones emisión(es) NET registradas.\n";
            echo "Son documentos oficiales; no se borran desde una migración.\n";

            return false;
        }

        $this->dropIndex('idx-cotizaciondetalle-idespecificacionemision', 'redes.CotizacionDetalle');
        $this->dropForeignKey('fk-cotizaciondetalle-idespecificacionemision', 'redes.CotizacionDetalle');
        $this->dropColumn('redes.CotizacionDetalle', 'idEspecificacionEmision');

        $this->dropTable('redes.ConfiguracionEspecificacion');
        $this->dropTable('redes.EspecificacionEmision');
        $this->dropTable('redes.EspecificacionCaracteristica');
        $this->dropTable('redes.EspecificacionVersion');
    }
}
