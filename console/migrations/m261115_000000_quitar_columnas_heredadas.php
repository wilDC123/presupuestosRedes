<?php

declare(strict_types=1);

use yii\db\Migration;
use yii\db\Query;

/**
 * Quita las columnas que quedaron de cosas que el sistema ya no hace. Se
 * habian dejado "por si habia datos de antes"; como el sistema todavia no se
 * instalo en la DTIC (alla empieza de cero) y en la base local no guardan
 * nada que no este tambien en su reemplazo, ya no hace falta conservarlas:
 *
 *  - Presupuesto.fecha: fecha escrita a mano al enviar a revision (sin uso
 *    desde el 2026-10-30; la del documento es fechaCreacion).
 *  - Presupuesto.areaIntervencion: el area pasó al proyecto
 *    (redes.ProyectoAreaIntervencion, 2026-10-28).
 *  - Presupuesto.numeroAnterior y EspecificacionEmision.codigoAnterior: el
 *    numero escrito a mano / el codigo NET que tenian antes de pasar al
 *    lineamiento RD-GD-LI-001 (m261022_144239).
 *  - EspecificacionEmision.serie: "NET" (las de antes del 2026-10-22) o
 *    "ET". Ya no hay ninguna NET: todas son ET, asi que la serie sobra. El
 *    indice unico queda en (numeroNet, versionDocumento).
 *  - ConfiguracionSolicitudCotizacion.textoCabecera: desde el 2026-11-09 la
 *    cabecera del PDF es la de todos los documentos.
 *  - Proyecto.codigoFacultad, facultad, codigoEdificio, edificio y la tabla
 *    redes.ProyectoBloque: la ubicacion anterior al 2026-10-28 (ahora
 *    unidad solicitante y area de intervencion).
 *
 * safeDown() vuelve a crear las columnas y la tabla, VACIAS: lo que tenian no
 * se recupera.
 */
class m261115_000000_quitar_columnas_heredadas extends Migration
{
    private const EMISION = 'redes.EspecificacionEmision';
    private const PRESUPUESTO = 'redes.Presupuesto';
    private const PROYECTO = 'redes.Proyecto';
    private const BLOQUE = 'redes.ProyectoBloque';
    private const CONFIGURACION_SOLICITUD = 'redes.ConfiguracionSolicitudCotizacion';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Una emision NET tendria su codigo solo en la serie: quitarla lo
        // perderia. No deberia haber ninguna (se convirtieron el 2026-10-22).
        if ((new Query())->from(self::EMISION)->where(['serie' => 'NET'])->exists($this->db)) {
            echo "Hay especificaciones emitidas con serie NET: hay que convertirlas a ET antes de quitar la serie.\n";

            return false;
        }

        $this->dropIndex('ux-especificacionemision-serie-numero-version', self::EMISION);
        $this->execute('ALTER TABLE ' . self::EMISION . ' DROP CONSTRAINT [ck-especificacionemision-serie]');
        $this->quitarValorPorDefecto(self::EMISION, 'serie');
        $this->dropColumn(self::EMISION, 'serie');
        $this->dropColumn(self::EMISION, 'codigoAnterior');
        $this->createIndex('ux-especificacionemision-numero-version', self::EMISION, ['numeroNet', 'versionDocumento'], true);

        $this->dropColumn(self::PRESUPUESTO, 'fecha');
        $this->dropColumn(self::PRESUPUESTO, 'areaIntervencion');
        $this->dropColumn(self::PRESUPUESTO, 'numeroAnterior');

        $this->dropColumn(self::CONFIGURACION_SOLICITUD, 'textoCabecera');

        $this->dropForeignKey('fk-proyectobloque-idproyecto', self::BLOQUE);
        $this->dropTable(self::BLOQUE);
        foreach (['codigoFacultad', 'facultad', 'codigoEdificio', 'edificio'] as $columna) {
            $this->dropColumn(self::PROYECTO, $columna);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Mismos tipos con que las crearon sus migraciones.
        $this->addColumn(self::PROYECTO, 'facultad', $this->string(200));
        $this->addColumn(self::PROYECTO, 'edificio', $this->string(200));
        $this->addColumn(self::PROYECTO, 'codigoFacultad', $this->string(30));
        $this->addColumn(self::PROYECTO, 'codigoEdificio', $this->string(30));
        $this->createTable(self::BLOQUE, [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'codigo' => $this->string(30)->notNull(),
            'nombre' => $this->string(100)->notNull(),
        ]);
        $this->addForeignKey('fk-proyectobloque-idproyecto', self::BLOQUE, 'idProyecto', self::PROYECTO, 'id', 'CASCADE');

        // NOT NULL sin valor por defecto, como antes: se agrega vacia y se completa.
        $this->addColumn(self::CONFIGURACION_SOLICITUD, 'textoCabecera', $this->string(500));
        $this->update(self::CONFIGURACION_SOLICITUD, ['textoCabecera' => '']);
        $this->alterColumn(self::CONFIGURACION_SOLICITUD, 'textoCabecera', $this->string(500)->notNull());

        $this->addColumn(self::PRESUPUESTO, 'numeroAnterior', $this->string(20));
        $this->addColumn(self::PRESUPUESTO, 'areaIntervencion', $this->string(300));
        $this->addColumn(self::PRESUPUESTO, 'fecha', $this->date());

        $this->dropIndex('ux-especificacionemision-numero-version', self::EMISION);
        $this->addColumn(self::EMISION, 'codigoAnterior', $this->string(20));
        // Todas las que hay son ET.
        $this->addColumn(self::EMISION, 'serie', $this->string(3)->notNull()->defaultValue('ET'));
        $this->execute("ALTER TABLE " . self::EMISION . " ADD CONSTRAINT [ck-especificacionemision-serie] CHECK (serie IN ('NET', 'ET'))");
        $this->createIndex('ux-especificacionemision-serie-numero-version', self::EMISION, ['serie', 'numeroNet', 'versionDocumento'], true);
    }

    /**
     * SQL Server no deja borrar una columna con valor por defecto. El de
     * "serie" se creo sin nombre (addColumn ... defaultValue), asi que SQL
     * Server le puso uno al azar (DF__Especific__serie__3CBF0154): se busca en
     * el catalogo. Valido en SQL Server 2012.
     */
    private function quitarValorPorDefecto(string $tabla, string $columna): void
    {
        $this->execute(
            "DECLARE @nombre sysname = (
                SELECT dc.name FROM sys.default_constraints dc
                JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
                WHERE dc.parent_object_id = OBJECT_ID(:tabla) AND c.name = :columna
            );
            IF @nombre IS NOT NULL EXEC('ALTER TABLE $tabla DROP CONSTRAINT [' + @nombre + ']');",
            [':tabla' => $tabla, ':columna' => $columna]
        );
    }
}
