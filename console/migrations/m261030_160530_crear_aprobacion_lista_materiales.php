<?php

use yii\db\Migration;

/**
 * La "Lista de materiales y condiciones previas" pasa a ser un documento
 * normado y aprobado (revision del Ing. de DTIC del 2026-10-29, paso 8b):
 * cabecera del lineamiento RD-GD-LI-001, version y control de elaboracion.
 *
 * Decidido con el desarrollador el 2026-10-30: NO lleva codigo propio (el
 * 8.3 del lineamiento no tiene uno para listas de materiales); lleva el
 * CODIGO DEL PROYECTO (RD-PI-PT-NNN), porque cada proyecto tiene una sola
 * lista y ese codigo no cambia.
 *
 * redes.ListaMaterialesAprobacion: una fila por VERSION APROBADA. "Enviar a
 * cotizacion" aprueba la version siguiente (la primera vez la 01; despues de
 * cada orden de cambio, +1). Nunca se modifica ni se borra (salvo con el
 * proyecto). Guarda la fecha, quien la aprobo y la COPIA de las dos firmas
 * del control de elaboracion (las de la configuracion del presupuesto ese
 * dia), como el presupuesto aprobado. No guarda copia de la lista: mientras
 * esta aprobada no cambia, y la impresa de cada version queda en la carpeta.
 *
 * Las listas que ya se enviaron (en cotizacion o cerradas) reciben su
 * version 01, sin fecha ni usuario (no se conocen) y con las firmas que
 * tiene hoy la configuracion, que son las que se imprimirian.
 */
class m261030_160530_crear_aprobacion_lista_materiales extends Migration
{
    public function safeUp()
    {
        $this->createTable('redes.ListaMaterialesAprobacion', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'version' => $this->integer()->notNull(),
            // NULL = aprobada antes de que existiera este registro.
            'fechaAprobacion' => $this->date()->null(),
            // Copia del nombre de usuario; NULL = antes del registro.
            'usuario' => $this->string(255)->null(),
            'firma1Prefijo' => $this->string(20)->null(),
            'firma1Nombre' => $this->string(150)->null(),
            'firma1Cargo' => $this->string(200)->null(),
            'firma2Prefijo' => $this->string(20)->null(),
            'firma2Nombre' => $this->string(150)->null(),
            'firma2Cargo' => $this->string(200)->null(),
        ]);
        $this->addForeignKey('fk-listamaterialesaprobacion-idproyecto', 'redes.ListaMaterialesAprobacion', 'idProyecto', 'redes.Proyecto', 'id', 'CASCADE');
        $this->createIndex('ux-listamaterialesaprobacion-version', 'redes.ListaMaterialesAprobacion', ['idProyecto', 'version'], true);

        $this->execute(
            "INSERT INTO redes.ListaMaterialesAprobacion
                 (idProyecto, version, firma1Prefijo, firma1Nombre, firma1Cargo, firma2Prefijo, firma2Nombre, firma2Cargo)
             SELECT p.id, 1, c.firma1Prefijo, c.firma1Nombre, c.firma1Cargo, c.firma2Prefijo, c.firma2Nombre, c.firma2Cargo
             FROM redes.Proyecto p
             LEFT JOIN redes.ConfiguracionPresupuesto c ON c.id = 1
             WHERE p.estadoLista IN ('en_cotizacion', 'cerrada')"
        );
    }

    public function safeDown()
    {
        $this->dropTable('redes.ListaMaterialesAprobacion');
    }
}
