<?php

use yii\db\Migration;

/**
 * "Control de elaboracion" del lineamiento de la DTIC RD-GD-LI-001 (8.12) en
 * el formulario de especificacion tecnica, como el del presupuesto: firma 1 =
 * elaborado por, firma 2 = aprobado por (con su V°B°), cada una con prefijo,
 * nombre y cargo.
 *
 *  - redes.ConfiguracionEspecificacion: los valores por defecto. Empiezan
 *    con las firmas que ya tiene la configuracion del presupuesto, para no
 *    escribirlas dos veces.
 *  - redes.EspecificacionEmision: la COPIA de cada formulario (una emision
 *    nunca cambia). Las ya emitidas quedan sin firmas: salen en blanco, para
 *    llenarlas a mano, como hasta ahora.
 */
class m261022_233202_control_elaboracion_en_formulario_especificacion extends Migration
{
    private const TABLAS = ['redes.ConfiguracionEspecificacion', 'redes.EspecificacionEmision'];

    public function safeUp()
    {
        foreach (self::TABLAS as $tabla) {
            foreach ([1, 2] as $n) {
                $this->addColumn($tabla, "firma{$n}Prefijo", $this->string(20)->null());
                $this->addColumn($tabla, "firma{$n}Nombre", $this->string(150)->null());
                $this->addColumn($tabla, "firma{$n}Cargo", $this->string(200)->null());
            }
        }

        $this->execute(
            'UPDATE e SET
                 firma1Prefijo = p.firma1Prefijo, firma1Nombre = p.firma1Nombre, firma1Cargo = p.firma1Cargo,
                 firma2Prefijo = p.firma2Prefijo, firma2Nombre = p.firma2Nombre, firma2Cargo = p.firma2Cargo
             FROM redes.ConfiguracionEspecificacion e
             CROSS JOIN redes.ConfiguracionPresupuesto p'
        );
    }

    public function safeDown()
    {
        foreach (self::TABLAS as $tabla) {
            foreach ([1, 2] as $n) {
                $this->dropColumn($tabla, "firma{$n}Prefijo");
                $this->dropColumn($tabla, "firma{$n}Nombre");
                $this->dropColumn($tabla, "firma{$n}Cargo");
            }
        }
    }
}
