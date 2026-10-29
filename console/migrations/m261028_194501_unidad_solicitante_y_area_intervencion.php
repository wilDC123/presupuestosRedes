<?php

use yii\db\Migration;

/**
 * Revision del sistema con el Ing. de DTIC (2026-10-27): "Facultad / Edificio
 * / Bloques" mezclaba dos datos distintos. Se separan:
 *
 *  - Proyecto.unidadSolicitante: QUIEN pide, en texto libre tal como dice la
 *    nota (una facultad, una carrera, un laboratorio o hasta una persona).
 *    Sin catalogo propio: nadie lo mantendria. Los proyectos que ya existen
 *    reciben su facultad, asi ninguno queda vacio.
 *  - redes.ProyectoAreaIntervencion: DONDE se hace el trabajo (edificio ->
 *    bloque -> nivel -> ambiente), una o varias filas por proyecto, tomadas
 *    de la infraestructura fisica de la universidad (base UsfxGEO, solo
 *    lectura). Cada nivel guarda el identificador de UsfxGEO (referencia
 *    LOGICA, sin llave foranea porque es otra base, como idSigma) y una copia
 *    del nombre, que no cambia aunque despues renombren el ambiente.
 *
 * Las columnas facultad, edificio y redes.ProyectoBloque NO se borran: son la
 * ubicacion de los proyectos registrados antes de este cambio y no se pueden
 * convertir con seguridad a ambientes de UsfxGEO. Ya no se escriben.
 *
 * Sin facultad, el lugar de entrega por defecto del formulario de
 * especificacion pasa a ser un texto de su configuracion (vacio = no sale).
 */
class m261028_194501_unidad_solicitante_y_area_intervencion extends Migration
{
    public function safeUp()
    {
        $this->addColumn('redes.Proyecto', 'unidadSolicitante', $this->string(250)->null());
        $this->execute("UPDATE redes.Proyecto SET unidadSolicitante = facultad WHERE facultad IS NOT NULL AND LTRIM(RTRIM(facultad)) <> ''");

        $this->createTable('redes.ProyectoAreaIntervencion', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'orden' => $this->integer()->notNull()->defaultValue(0),
            // UsfxGEO: Edificios.IdEdificio es char(3); se deja margen.
            'idEdificio' => $this->string(10)->notNull(),
            'edificio' => $this->string(200)->notNull(),
            // Desde aqui es opcional: el area llega hasta el nivel que haga falta.
            'idBloqueArea' => $this->integer(),
            'bloque' => $this->string(200),
            'numeroNivel' => $this->string(10),
            'nivel' => $this->string(100),
            // UsfxGEO: Espacios.IdEspacio es char(4). CodigoEspacio ("B101") NO es unico.
            'idEspacio' => $this->string(10),
            'codigoAmbiente' => $this->string(30),
            'ambiente' => $this->string(250),
        ]);
        $this->createIndex('ix-proyectoareaintervencion-idproyecto', 'redes.ProyectoAreaIntervencion', ['idProyecto', 'orden']);
        // Composicion: las areas se borran con su proyecto.
        $this->addForeignKey('fk-proyectoareaintervencion-idproyecto', 'redes.ProyectoAreaIntervencion', 'idProyecto', 'redes.Proyecto', 'id', 'CASCADE');

        $this->addColumn('redes.ConfiguracionEspecificacion', 'lugarEntregaPorDefecto', $this->string(300)->null());
    }

    public function safeDown()
    {
        $this->dropColumn('redes.ConfiguracionEspecificacion', 'lugarEntregaPorDefecto');
        $this->dropTable('redes.ProyectoAreaIntervencion');
        $this->dropColumn('redes.Proyecto', 'unidadSolicitante');
    }
}
