<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Pasa al codigo del lineamiento RD-GD-LI-001 los documentos que se hicieron
 * ANTES de m261022_135030_codificacion_segun_lineamiento (pedido del
 * desarrollador el 2026-10-22, para que todo el sistema use un solo formato):
 *
 *  - especificaciones emitidas con codigo NET (NET:900/26) -> RD-PI-ET-NNN,
 *    todas (tambien las reemplazadas y anuladas, que tambien son documentos),
 *    en el orden en que se emitieron (año y numero NET),
 *  - presupuestos con numero escrito a mano ("690") -> RD-PI-PR-NNN, en el
 *    orden en que se crearon.
 *
 * Los numeros salen del correlativo de cada tipo (redes.CorrelativoDocumento),
 * que queda en el siguiente libre: no se repite ninguno ya asignado.
 *
 * El codigo de antes se GUARDA (codigoAnterior / numeroAnterior): los
 * documentos ya entregados lo llevan impreso, y asi se puede buscar y volver
 * atras (safeDown).
 */
class m261022_144239_convertir_codigos_viejos_al_lineamiento extends Migration
{
    public function safeUp()
    {
        $this->addColumn('redes.EspecificacionEmision', 'codigoAnterior', $this->string(20));
        $this->addColumn('redes.Presupuesto', 'numeroAnterior', $this->string(20));

        // 1. Especificaciones NET -> ET.
        $siguienteEt = $this->siguiente('ET');
        $emisiones = (new Query())
            ->select(['id', 'numeroNet', 'anio'])
            ->from('redes.EspecificacionEmision')
            ->where(['serie' => 'NET'])
            ->orderBy(['anio' => SORT_ASC, 'numeroNet' => SORT_ASC])
            ->all($this->db);

        foreach ($emisiones as $emision) {
            $this->update('redes.EspecificacionEmision', [
                'codigoAnterior' => sprintf('NET:%03d/%02d', $emision['numeroNet'], $emision['anio']),
                'serie' => 'ET',
                'numeroNet' => $siguienteEt++,
            ], ['id' => $emision['id']]);
        }
        $this->update('redes.CorrelativoDocumento', ['siguiente' => $siguienteEt], ['tipo' => 'ET']);

        // 2. Presupuestos con numero escrito a mano -> PR. Los que no tienen
        // numero lo reciben solos al entrar al paso Presupuesto.
        $siguientePr = $this->siguiente('PR');
        $presupuestos = (new Query())
            ->select(['id', 'numeroPresupuesto'])
            ->from('redes.Presupuesto')
            ->where(['not', ['numeroPresupuesto' => null]])
            ->andWhere(['<>', 'numeroPresupuesto', ''])
            ->andWhere(['not like', 'numeroPresupuesto', 'RD-PI-PR-%', false])
            ->orderBy(['fechaCreacion' => SORT_ASC, 'id' => SORT_ASC])
            ->all($this->db);

        foreach ($presupuestos as $presupuesto) {
            $this->update('redes.Presupuesto', [
                'numeroAnterior' => $presupuesto['numeroPresupuesto'],
                'numeroPresupuesto' => sprintf('RD-PI-PR-%03d', $siguientePr++),
            ], ['id' => $presupuesto['id']]);
        }
        $this->update('redes.CorrelativoDocumento', ['siguiente' => $siguientePr], ['tipo' => 'PR']);
    }

    public function safeDown()
    {
        // Las convertidas vuelven a su codigo NET; las emitidas despues (sin
        // codigoAnterior) se quedan como estan.
        $emisiones = (new Query())
            ->select(['id', 'codigoAnterior'])
            ->from('redes.EspecificacionEmision')
            ->where(['not', ['codigoAnterior' => null]])
            ->all($this->db);

        foreach ($emisiones as $emision) {
            if (preg_match('/^NET:(\d+)\/(\d+)$/', $emision['codigoAnterior'], $partes) !== 1) {
                throw new RuntimeException("Código anterior inesperado: {$emision['codigoAnterior']}");
            }
            $this->update('redes.EspecificacionEmision', [
                'serie' => 'NET',
                'numeroNet' => (int) $partes[1],
                'anio' => (int) $partes[2],
            ], ['id' => $emision['id']]);
        }

        $this->execute("UPDATE redes.Presupuesto SET numeroPresupuesto = numeroAnterior WHERE numeroAnterior IS NOT NULL");

        // El correlativo sigue despues del mayor que quede en uso. Los numeros
        // que tenian los documentos convertidos quedan libres otra vez.
        $mayorEt = (new Query())->from('redes.EspecificacionEmision')->where(['serie' => 'ET'])->max('numeroNet', $this->db);
        $this->update('redes.CorrelativoDocumento', ['siguiente' => (int) $mayorEt + 1], ['tipo' => 'ET']);

        $mayorPr = 0;
        $numeros = (new Query())->select('numeroPresupuesto')->from('redes.Presupuesto')->column($this->db);
        foreach ($numeros as $numero) {
            if (preg_match('/^RD-PI-PR-(\d+)$/', (string) $numero, $partes) === 1) {
                $mayorPr = max($mayorPr, (int) $partes[1]);
            }
        }
        $this->update('redes.CorrelativoDocumento', ['siguiente' => $mayorPr + 1], ['tipo' => 'PR']);

        $this->dropColumn('redes.Presupuesto', 'numeroAnterior');
        $this->dropColumn('redes.EspecificacionEmision', 'codigoAnterior');
    }

    private function siguiente(string $tipo): int
    {
        return (int) (new Query())->select('siguiente')->from('redes.CorrelativoDocumento')->where(['tipo' => $tipo])->scalar($this->db);
    }
}
