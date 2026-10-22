<?php

use yii\db\Migration;

/**
 * Codificacion de los impresos segun el lineamiento de la DTIC RD-GD-LI-001
 * (unidad RD - proceso PI - tipo - correlativo de 3 cifras):
 *
 *  - proyecto: RD-PI-PT-NNN. Los codigos existentes de 2 cifras pasan a 3
 *    (RD-PI-PT-04 -> RD-PI-PT-004),
 *  - presupuesto: RD-PI-PR-NNN (los numeros ya escritos a mano se conservan),
 *  - especificacion tecnica: RD-PI-ET-NNN en lugar de NET:880/26. Las
 *    emisiones ya hechas son documentos firmados: siguen siendo de la serie
 *    "NET" con su codigo de siempre; las nuevas son de la serie "ET", que
 *    empieza en 1. Por eso el numero ya no es unico solo, sino por serie.
 *
 * Los tres correlativos viven en redes.CorrelativoDocumento (una fila por
 * tipo) y reemplazan a ConfiguracionEspecificacion.siguienteNet. Ademas se
 * agrega Presupuesto.fechaAprobacion, que pide el "control de elaboracion".
 */
class m261022_135030_codificacion_segun_lineamiento extends Migration
{
    public function safeUp()
    {
        // 1. Proyectos: 2 cifras -> 3. Las posiciones 10 en adelante son el
        // numero ('RD-PI-PT-' tiene 9 caracteres).
        $this->execute(
            "UPDATE redes.Proyecto
             SET codigo = 'RD-PI-PT-' + RIGHT('000' + SUBSTRING(codigo, 10, 10), 3)
             WHERE codigo LIKE 'RD-PI-PT-[0-9][0-9]'"
        );

        // 2. Correlativos: el siguiente numero de cada tipo de documento.
        $this->createTable('redes.CorrelativoDocumento', [
            'tipo' => $this->string(2)->notNull(),
            'siguiente' => $this->integer()->notNull(),
            'PRIMARY KEY ([[tipo]])',
        ]);
        $this->execute("ALTER TABLE redes.CorrelativoDocumento ADD CONSTRAINT [ck-correlativodocumento-tipo] CHECK (tipo IN ('PT', 'PR', 'ET'))");
        $this->execute('ALTER TABLE redes.CorrelativoDocumento ADD CONSTRAINT [ck-correlativodocumento-siguiente] CHECK (siguiente >= 1)');

        // Los proyectos siguen desde el mayor ya usado; presupuestos y
        // especificaciones empiezan en 1 (decidido el 2026-10-22).
        $codigos = (new \yii\db\Query())->select('codigo')->from('redes.Proyecto')->where(['like', 'codigo', 'RD-PI-PT-%', false])->column();
        $mayor = 0;
        foreach ($codigos as $codigo) {
            if (preg_match('/^RD-PI-PT-(\d+)$/', (string) $codigo, $partes) === 1) {
                $mayor = max($mayor, (int) $partes[1]);
            }
        }
        $this->batchInsert('redes.CorrelativoDocumento', ['tipo', 'siguiente'], [
            ['PT', $mayor + 1],
            ['PR', 1],
            ['ET', 1],
        ]);

        // 3. Especificaciones: serie "NET" (las de antes) o "ET" (las nuevas).
        $this->addColumn('redes.EspecificacionEmision', 'serie', $this->string(3)->notNull()->defaultValue('NET'));
        $this->execute("ALTER TABLE redes.EspecificacionEmision ADD CONSTRAINT [ck-especificacionemision-serie] CHECK (serie IN ('NET', 'ET'))");
        $this->dropIndex('ux-especificacionemision-numeronet', 'redes.EspecificacionEmision');
        $this->createIndex('ux-especificacionemision-serie-numero', 'redes.EspecificacionEmision', ['serie', 'numeroNet'], true);

        $this->dropColumn('redes.ConfiguracionEspecificacion', 'siguienteNet');

        // 4. Fecha en que el presupuesto paso a "aprobado". Los ya aprobados
        // quedan sin fecha: el sistema no la guardaba.
        $this->addColumn('redes.Presupuesto', 'fechaAprobacion', $this->date());
    }

    public function safeDown()
    {
        // Volver atras borraria la diferencia entre "NET" y "ET": si ya hay
        // emisiones nuevas, dos documentos podrian quedar con el mismo numero.
        $nuevas = (new \yii\db\Query())->from('redes.EspecificacionEmision')->where(['serie' => 'ET'])->count();
        if ((int) $nuevas > 0) {
            echo "Hay $nuevas especificaciones emitidas con codigo RD-PI-ET: no se puede volver al codigo NET.\n";

            return false;
        }

        $this->dropColumn('redes.Presupuesto', 'fechaAprobacion');

        $this->addColumn('redes.ConfiguracionEspecificacion', 'siguienteNet', $this->integer()->null());
        $this->execute('UPDATE redes.ConfiguracionEspecificacion SET siguienteNet = (SELECT MAX(numeroNet) + 1 FROM redes.EspecificacionEmision)');

        $this->dropIndex('ux-especificacionemision-serie-numero', 'redes.EspecificacionEmision');
        $this->createIndex('ux-especificacionemision-numeronet', 'redes.EspecificacionEmision', 'numeroNet', true);
        $this->execute('ALTER TABLE redes.EspecificacionEmision DROP CONSTRAINT [ck-especificacionemision-serie]');
        $this->dropColumn('redes.EspecificacionEmision', 'serie');

        $this->dropTable('redes.CorrelativoDocumento');

        $this->execute(
            "UPDATE redes.Proyecto
             SET codigo = 'RD-PI-PT-' + SUBSTRING(codigo, 11, 2)
             WHERE codigo LIKE 'RD-PI-PT-0[0-9][0-9]'"
        );
    }
}
