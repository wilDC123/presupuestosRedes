<?php

use yii\db\Migration;

/**
 * NUMERO DE PROYECTO (revision del Ing. de DTIC del 2026-11-05, puntos 1 y 3):
 * "el proyecto es una entidad, no un documento": lleva un numero correlativo
 * (1, 2, 3...) que asigna el sistema, y TODAS SUS FASES llevan el MISMO
 * numero. El codigo RD-PI-PT-NNN queda para la ficha tecnica del proyecto.
 *
 * Cada fila de redes.Proyecto es una FASE (con su area, equipo y carpeta
 * economica); redes.Proyecto.numeroProyecto agrupa las fases de un mismo
 * proyecto y numeroFase las ordena (decidido con el desarrollador el
 * 2026-11-06: opcion 1, sin tabla nueva).
 *
 * - redes.CorrelativoDocumento admite el tipo 'NP' (numero de proyecto; no
 *   es un documento, pero se reparte igual que los codigos).
 * - Los proyectos existentes: cada cadena de fases (idProyectoAnterior)
 *   recibe un numero, en el orden en que se creo su primera fase; las fases
 *   siguientes toman el de su primera fase. Ej. en la base local el 26 y su
 *   fase 2 (el 27) son el proyecto 1 y el 28 el proyecto 2.
 * - Una misma fase no se repite dentro de un proyecto (indice unico filtrado).
 */
class m261106_120000_numero_de_proyecto extends Migration
{
    public function safeUp()
    {
        $this->execute('ALTER TABLE redes.CorrelativoDocumento DROP CONSTRAINT [ck-correlativodocumento-tipo]');
        $this->execute("ALTER TABLE redes.CorrelativoDocumento ADD CONSTRAINT [ck-correlativodocumento-tipo] CHECK (tipo IN ('NP', 'PT', 'LM', 'PR', 'ET'))");

        $this->addColumn('redes.Proyecto', 'numeroProyecto', $this->integer()->null());

        // La primera fase de cada cadena (sin fase anterior) se numera en orden de id;
        // las demas heredan el numero de su primera fase.
        $this->execute(<<<'SQL'
            WITH cadena AS (
                SELECT id, id AS raiz FROM redes.Proyecto WHERE idProyectoAnterior IS NULL
                UNION ALL
                SELECT p.id, c.raiz FROM redes.Proyecto p JOIN cadena c ON p.idProyectoAnterior = c.id
            ),
            numeros AS (
                SELECT id AS raiz, ROW_NUMBER() OVER (ORDER BY id) AS numero
                FROM redes.Proyecto WHERE idProyectoAnterior IS NULL
            )
            UPDATE p SET numeroProyecto = n.numero
            FROM redes.Proyecto p
            JOIN cadena c ON c.id = p.id
            JOIN numeros n ON n.raiz = c.raiz
            SQL);

        $siguiente = (int) $this->db->createCommand('SELECT ISNULL(MAX(numeroProyecto), 0) + 1 FROM redes.Proyecto')->queryScalar();
        $this->insert('redes.CorrelativoDocumento', ['tipo' => 'NP', 'siguiente' => $siguiente]);

        $this->execute('CREATE UNIQUE INDEX [ux-proyecto-numero-fase] ON redes.Proyecto (numeroProyecto, numeroFase) WHERE numeroProyecto IS NOT NULL');
    }

    public function safeDown()
    {
        $this->execute('DROP INDEX [ux-proyecto-numero-fase] ON redes.Proyecto');
        $this->dropColumn('redes.Proyecto', 'numeroProyecto');
        $this->delete('redes.CorrelativoDocumento', ['tipo' => 'NP']);
        $this->execute('ALTER TABLE redes.CorrelativoDocumento DROP CONSTRAINT [ck-correlativodocumento-tipo]');
        $this->execute("ALTER TABLE redes.CorrelativoDocumento ADD CONSTRAINT [ck-correlativodocumento-tipo] CHECK (tipo IN ('PT', 'LM', 'PR', 'ET'))");
    }
}
