<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * El codigo de las especificaciones tecnicas YA NO LLEVA EL AÑO (pedido del
 * 2026-11-10): RD-PI-ET-033, no RD-PI-ET-033/26. Su numero tampoco vuelve a
 * 001 cada 1 de enero: sigue subiendo como el de los demas documentos
 * (999, 1000, 1001...). Deshace lo de m261030_024446 que tenia que ver con el
 * año (el proceso congelado se queda).
 *
 * Cambios en la base:
 *  - CorrelativoDocumento.anio se elimina: ningun contador se reinicia.
 *  - El indice unico de las emisiones vuelve a ser (serie, numeroNet,
 *    versionDocumento): sin el año, el mismo numero no puede repetirse.
 *  - EspecificacionEmision.anio SE QUEDA como dato (año de emision): lo usan
 *    el filtro "Año" del registro y el codigo de los NET de antes
 *    (NET:880/26). Ya no sale en el codigo de las ET.
 *  - Por las dudas, el siguiente ET queda por encima del mayor ya usado.
 *
 * Las ET ya emitidas (todas de 2026, ninguna repetida) pasan a citarse sin
 * "/26", tambien al reimprimirlas.
 */
class m261110_060000_especificacion_sin_anio extends Migration
{
    private const EMISION = 'redes.EspecificacionEmision';
    private const CORRELATIVO = 'redes.CorrelativoDocumento';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Sin el año, dos documentos con el mismo numero en años distintos chocarian.
        $repetidas = (new Query())
            ->from(self::EMISION)
            ->groupBy(['serie', 'numeroNet', 'versionDocumento'])
            ->having('COUNT(*) > 1')
            ->exists($this->db);

        if ($repetidas) {
            echo "Hay especificaciones con el mismo numero en años distintos: hay que decidir que hacer con ellas antes de quitar el año.\n";

            return false;
        }

        $this->dropIndex('ux-especificacionemision-serie-anio-numero-version', self::EMISION);
        $this->createIndex('ux-especificacionemision-serie-numero-version', self::EMISION, ['serie', 'numeroNet', 'versionDocumento'], true);

        $this->dropColumn(self::CORRELATIVO, 'anio');

        $mayor = (new Query())->from(self::EMISION)->where(['serie' => 'ET'])->max('numeroNet', $this->db);
        if ($mayor !== null) {
            $this->update(
                self::CORRELATIVO,
                ['siguiente' => (int) $mayor + 1],
                ['and', ['tipo' => 'ET'], ['<=', 'siguiente', (int) $mayor]]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->addColumn(self::CORRELATIVO, 'anio', $this->smallInteger()->null());
        $this->update(self::CORRELATIVO, ['anio' => (int) date('y')], ['tipo' => 'ET']);

        $this->dropIndex('ux-especificacionemision-serie-numero-version', self::EMISION);
        $this->createIndex('ux-especificacionemision-serie-anio-numero-version', self::EMISION, ['serie', 'anio', 'numeroNet', 'versionDocumento'], true);
    }
}
