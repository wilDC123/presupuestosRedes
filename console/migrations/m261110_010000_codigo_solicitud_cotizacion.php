<?php

use yii\db\Migration;

/**
 * Codigo propio de la "Solicitud de proforma de cotizacion" (formatos
 * corregidos v3 del 2026-11-09: la solicitud lleva la misma cabecera que los
 * demas documentos, con codigo RD-PI-SC-001 y version). Hasta ahora no tenia
 * codigo del lineamiento: se identificaba con "001 · Fase 2 / SC-01".
 *
 * - redes.CorrelativoDocumento admite el tipo 'SC' (sin año, como LM y PR;
 *   un solo numero para todos los procesos).
 * - redes.SolicitudCotizacion.codigoDocumento: RD-<proceso del proyecto>-SC-NNN.
 *   Lo pone el sistema al crear la solicitud y ya no cambia (como el del
 *   presupuesto).
 * - Las solicitudes que ya existen reciben su numero en el orden en que se
 *   crearon (001, 002...) y el correlativo sigue desde el siguiente.
 */
class m261110_010000_codigo_solicitud_cotizacion extends Migration
{
    public function safeUp()
    {
        $this->execute('ALTER TABLE redes.CorrelativoDocumento DROP CONSTRAINT [ck-correlativodocumento-tipo]');
        $this->execute("ALTER TABLE redes.CorrelativoDocumento ADD CONSTRAINT [ck-correlativodocumento-tipo] CHECK (tipo IN ('NP', 'PT', 'LM', 'SC', 'PR', 'ET'))");

        $this->addColumn('redes.SolicitudCotizacion', 'codigoDocumento', $this->string(20)->null());

        $this->execute(<<<'SQL'
            WITH numeradas AS (
                SELECT s.id, ROW_NUMBER() OVER (ORDER BY s.id) AS n, ISNULL(pr.codigo, 'PI') AS proceso
                FROM redes.SolicitudCotizacion s
                JOIN redes.Proyecto p ON p.id = s.idProyecto
                LEFT JOIN redes.Proceso pr ON pr.id = p.idProceso
            )
            UPDATE s
            SET codigoDocumento = 'RD-' + numeradas.proceso + '-SC-' + RIGHT('00' + CAST(numeradas.n AS varchar(10)), CASE WHEN numeradas.n > 999 THEN LEN(CAST(numeradas.n AS varchar(10))) ELSE 3 END)
            FROM redes.SolicitudCotizacion s
            JOIN numeradas ON numeradas.id = s.id
            SQL);

        $siguiente = (int) $this->db->createCommand('SELECT COUNT(*) FROM redes.SolicitudCotizacion')->queryScalar() + 1;
        $this->insert('redes.CorrelativoDocumento', ['tipo' => 'SC', 'siguiente' => $siguiente]);

        // Un codigo no se repite (los NULL no cuentan).
        $this->execute('CREATE UNIQUE INDEX [ux-solicitudcotizacion-codigodocumento] ON redes.SolicitudCotizacion (codigoDocumento) WHERE codigoDocumento IS NOT NULL');
    }

    public function safeDown()
    {
        $this->execute('DROP INDEX [ux-solicitudcotizacion-codigodocumento] ON redes.SolicitudCotizacion');
        $this->dropColumn('redes.SolicitudCotizacion', 'codigoDocumento');
        $this->delete('redes.CorrelativoDocumento', ['tipo' => 'SC']);
        $this->execute('ALTER TABLE redes.CorrelativoDocumento DROP CONSTRAINT [ck-correlativodocumento-tipo]');
        $this->execute("ALTER TABLE redes.CorrelativoDocumento ADD CONSTRAINT [ck-correlativodocumento-tipo] CHECK (tipo IN ('NP', 'PT', 'LM', 'PR', 'ET'))");
    }
}
