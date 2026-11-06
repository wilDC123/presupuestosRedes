<?php

use yii\db\Migration;

/**
 * Codigo propio de la "Lista de materiales y condiciones previas" (revision
 * del Ing. de DTIC del 2026-11-05, punto 7: "propuesta de codigo: LM"). Desde
 * el 2026-10-30 la lista llevaba el codigo del proyecto (RD-PI-PT-NNN).
 *
 * - redes.CorrelativoDocumento admite el tipo 'LM' (sin año, como PT y PR).
 * - redes.Proyecto.codigoLista: RD-<proceso del proyecto>-LM-NNN, uno por
 *   proyecto (cada fase es su propio proyecto y su propia lista). Lo pone el
 *   sistema al crear el proyecto.
 * - Los proyectos que ya existen reciben su numero en el orden en que se
 *   crearon (001, 002...) y el correlativo sigue desde el siguiente.
 */
class m261106_030000_codigo_lista_materiales extends Migration
{
    public function safeUp()
    {
        $this->execute('ALTER TABLE redes.CorrelativoDocumento DROP CONSTRAINT [ck-correlativodocumento-tipo]');
        $this->execute("ALTER TABLE redes.CorrelativoDocumento ADD CONSTRAINT [ck-correlativodocumento-tipo] CHECK (tipo IN ('PT', 'LM', 'PR', 'ET'))");

        $this->addColumn('redes.Proyecto', 'codigoLista', $this->string(20)->null());

        $this->execute(<<<'SQL'
            WITH numerados AS (
                SELECT p.id, ROW_NUMBER() OVER (ORDER BY p.id) AS n, ISNULL(pr.codigo, 'PI') AS proceso
                FROM redes.Proyecto p
                LEFT JOIN redes.Proceso pr ON pr.id = p.idProceso
            )
            UPDATE p
            SET codigoLista = 'RD-' + numerados.proceso + '-LM-' + RIGHT('00' + CAST(numerados.n AS varchar(10)), CASE WHEN numerados.n > 999 THEN LEN(CAST(numerados.n AS varchar(10))) ELSE 3 END)
            FROM redes.Proyecto p
            JOIN numerados ON numerados.id = p.id
            SQL);

        $siguiente = (int) $this->db->createCommand('SELECT COUNT(*) FROM redes.Proyecto')->queryScalar() + 1;
        $this->insert('redes.CorrelativoDocumento', ['tipo' => 'LM', 'siguiente' => $siguiente]);

        // Un codigo no se repite (los NULL no cuentan).
        $this->execute('CREATE UNIQUE INDEX [ux-proyecto-codigolista] ON redes.Proyecto (codigoLista) WHERE codigoLista IS NOT NULL');
    }

    public function safeDown()
    {
        $this->execute('DROP INDEX [ux-proyecto-codigolista] ON redes.Proyecto');
        $this->dropColumn('redes.Proyecto', 'codigoLista');
        $this->delete('redes.CorrelativoDocumento', ['tipo' => 'LM']);
        $this->execute('ALTER TABLE redes.CorrelativoDocumento DROP CONSTRAINT [ck-correlativodocumento-tipo]');
        $this->execute("ALTER TABLE redes.CorrelativoDocumento ADD CONSTRAINT [ck-correlativodocumento-tipo] CHECK (tipo IN ('PT', 'PR', 'ET'))");
    }
}
