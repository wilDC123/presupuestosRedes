<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Codificacion con proceso y año (revision del Ing. de DTIC del 2026-10-29,
 * paso 4):
 *
 *  - Las especificaciones tecnicas llevan el año al final y su numero vuelve
 *    a 001 cada 1 de enero: RD-PI-ET-001/26 ... RD-PI-ET-001/27. Son las que
 *    pueden pasar de 999 en un año (el NET llego a ~900 en 2026). Proyectos y
 *    presupuestos siguen sin año y sin reiniciarse.
 *  - Presupuestos y especificaciones llevan el proceso del proyecto (PI, GR,
 *    DS...) en vez de PI fijo; el numero es uno solo para todos los procesos.
 *
 * Cambios en la base:
 *  - EspecificacionEmision.proceso: el codigo del proceso con el que se
 *    emitio, CONGELADO (cambiar el proceso del proyecto despues no cambia un
 *    documento ya emitido). Las que existen quedan en PI.
 *  - El indice unico de las emisiones pasa a incluir el año: el mismo numero
 *    se repite en años distintos (001/26 y 001/27).
 *  - CorrelativoDocumento.anio: el año al que pertenece el "siguiente" de ET.
 *    Si al tomar un numero el año ya cambio, el contador vuelve a 1.
 *
 * Las ET ya emitidas (todas de 2026) pasan a citarse con /26: su columna anio
 * ya era el año de emision.
 */
class m261030_024446_especificacion_con_proceso_y_anio extends Migration
{
    private const EMISION = 'redes.EspecificacionEmision';
    private const CORRELATIVO = 'redes.CorrelativoDocumento';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Primero NULL, se llena y recien despues NOT NULL: asi no queda una
        // restriccion DEFAULT con nombre automatico que estorbe al bajarla.
        $this->addColumn(self::EMISION, 'proceso', $this->string(2)->null());
        $this->update(self::EMISION, ['proceso' => 'PI']);
        $this->alterColumn(self::EMISION, 'proceso', $this->string(2)->notNull());

        $this->dropIndex('ux-especificacionemision-serie-numero-version', self::EMISION);
        $this->createIndex('ux-especificacionemision-serie-anio-numero-version', self::EMISION, ['serie', 'anio', 'numeroNet', 'versionDocumento'], true);

        // Año del calendario de Bolivia (timeZone de common/config/main.php).
        $this->addColumn(self::CORRELATIVO, 'anio', $this->smallInteger()->null());
        $this->update(self::CORRELATIVO, ['anio' => (int) date('y')], ['tipo' => 'ET']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Sin el año, dos ET de años distintos con el mismo numero chocarian.
        $repetidas = (new Query())
            ->from(self::EMISION)
            ->groupBy(['serie', 'numeroNet', 'versionDocumento'])
            ->having('COUNT(*) > 1')
            ->exists($this->db);

        if ($repetidas) {
            echo "Hay especificaciones con el mismo numero en años distintos: no se puede volver al indice sin año.\n";

            return false;
        }

        $this->dropColumn(self::CORRELATIVO, 'anio');

        $this->dropIndex('ux-especificacionemision-serie-anio-numero-version', self::EMISION);
        $this->createIndex('ux-especificacionemision-serie-numero-version', self::EMISION, ['serie', 'numeroNet', 'versionDocumento'], true);

        $this->dropColumn(self::EMISION, 'proceso');
    }
}
