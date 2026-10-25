<?php

use yii\db\Migration;

/**
 * Copia de las firmas del "control de elaboracion" en el presupuesto
 * (lineamiento de la DTIC RD-GD-LI-001: un documento aprobado es un
 * documento controlado y no cambia).
 *
 * Hasta ahora el PDF leia las firmas de redes.ConfiguracionPresupuesto cada
 * vez que se imprimia: si cambiaba el responsable, un presupuesto ya aprobado
 * salia con el nombre nuevo. Desde ahora, al APROBAR, PresupuestoService
 * copia las dos firmas (prefijo, nombre y cargo) al presupuesto, igual que
 * EspecificacionEmision al emitir. Los que no estan aprobados siguen
 * leyendo la configuracion.
 *
 * Los ya aprobados se llenan con las firmas que tiene hoy la configuracion:
 * son las mismas con que se imprimian hasta ahora.
 */
class m261025_021822_copiar_firmas_al_aprobar_presupuesto extends Migration
{
    public function safeUp()
    {
        foreach ([1, 2] as $n) {
            $this->addColumn('redes.Presupuesto', "firma{$n}Prefijo", $this->string(20)->null());
            $this->addColumn('redes.Presupuesto', "firma{$n}Nombre", $this->string(150)->null());
            $this->addColumn('redes.Presupuesto', "firma{$n}Cargo", $this->string(200)->null());
        }

        $this->execute(
            "UPDATE p SET
                 firma1Prefijo = c.firma1Prefijo, firma1Nombre = c.firma1Nombre, firma1Cargo = c.firma1Cargo,
                 firma2Prefijo = c.firma2Prefijo, firma2Nombre = c.firma2Nombre, firma2Cargo = c.firma2Cargo
             FROM redes.Presupuesto p
             CROSS JOIN redes.ConfiguracionPresupuesto c
             WHERE p.estado = 'aprobado'"
        );
    }

    public function safeDown()
    {
        foreach ([1, 2] as $n) {
            $this->dropColumn('redes.Presupuesto', "firma{$n}Prefijo");
            $this->dropColumn('redes.Presupuesto', "firma{$n}Nombre");
            $this->dropColumn('redes.Presupuesto', "firma{$n}Cargo");
        }
    }
}
