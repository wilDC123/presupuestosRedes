<?php

use yii\db\Migration;

/**
 * Plantillas de items (con clasificacion configurable por plantilla) y la
 * lista de items del proyecto, a la que se COPIAN los items de una plantilla.
 */
class m261005_044333_create_plantilla_y_proyecto_item extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.Plantilla', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(200)->notNull(),
            'descripcion' => $this->string(500),
            // Tipo de proyecto sugerido (codigo de los parametros). Sin FK:
            // esos datos no viven en esta base.
            'tipoProyecto' => $this->string(50),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
            'fechaCreacion' => $this->dateTime()->notNull()->defaultExpression('GETDATE()'),
        ]);

        // Clasificaciones (categorias) que incluye cada plantilla.
        $this->createTable('redes.PlantillaCategoria', [
            'id' => $this->primaryKey(),
            'idPlantilla' => $this->integer()->notNull(),
            'idCategoria' => $this->integer()->notNull(),
            'orden' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->addForeignKey('fk-plantillacategoria-idplantilla', 'redes.PlantillaCategoria', 'idPlantilla', 'redes.Plantilla', 'id', 'CASCADE');
        $this->addForeignKey('fk-plantillacategoria-idcategoria', 'redes.PlantillaCategoria', 'idCategoria', 'redes.Categoria', 'id');
        $this->createIndex('uq-plantillacategoria', 'redes.PlantillaCategoria', ['idPlantilla', 'idCategoria'], true);

        // Items de la plantilla. idCategoria es la clasificacion del item
        // DENTRO de esta plantilla (puede diferir de la categoria que el
        // item tiene en el subcatalogo). Sin indice unico sobre el item: un
        // mismo item puede repetirse con distinta observacion (patch cord de
        // 1 metro y de 3 metros).
        $this->createTable('redes.PlantillaItem', [
            'id' => $this->primaryKey(),
            'idPlantilla' => $this->integer()->notNull(),
            'idSubcatalogo' => $this->integer()->notNull(),
            'idCategoria' => $this->integer()->notNull(),
            'cantidadSugerida' => $this->decimal(10, 2),
            'observacion' => $this->string(300),
            'orden' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->addForeignKey('fk-plantillaitem-idplantilla', 'redes.PlantillaItem', 'idPlantilla', 'redes.Plantilla', 'id', 'CASCADE');
        $this->addForeignKey('fk-plantillaitem-idsubcatalogo', 'redes.PlantillaItem', 'idSubcatalogo', 'redes.SubcatalogoItem', 'id');
        $this->addForeignKey('fk-plantillaitem-idcategoria', 'redes.PlantillaItem', 'idCategoria', 'redes.Categoria', 'id');

        // Lista de items del proyecto: solo items y cantidades, SIN precios.
        // No tiene ninguna referencia a la plantilla: los items se copian,
        // asi cambiar una plantilla no altera los proyectos que la usaron.
        $this->createTable('redes.ProyectoItem', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'idSubcatalogo' => $this->integer()->notNull(),
            'idCategoria' => $this->integer()->notNull(),
            'cantidad' => $this->decimal(10, 2)->notNull()->defaultValue(0),
            'unidadMedida' => $this->string(20),
            'observacion' => $this->string(300),
            'orden' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->addForeignKey('fk-proyectoitem-idproyecto', 'redes.ProyectoItem', 'idProyecto', 'redes.Proyecto', 'id', 'CASCADE');
        $this->addForeignKey('fk-proyectoitem-idsubcatalogo', 'redes.ProyectoItem', 'idSubcatalogo', 'redes.SubcatalogoItem', 'id');
        $this->addForeignKey('fk-proyectoitem-idcategoria', 'redes.ProyectoItem', 'idCategoria', 'redes.Categoria', 'id');

        // De que plantilla partio la lista (solo informativo) y si el paso
        // "Plantilla" ya se resolvio (se uso una, o se eligio empezar en blanco).
        $this->addColumn('redes.Proyecto', 'idPlantillaOrigen', $this->integer()->null());
        $this->addColumn('redes.Proyecto', 'listaIniciada', $this->boolean()->notNull()->defaultValue(0));
        $this->addForeignKey('fk-proyecto-idplantillaorigen', 'redes.Proyecto', 'idPlantillaOrigen', 'redes.Plantilla', 'id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-proyecto-idplantillaorigen', 'redes.Proyecto');
        $this->dropColumn('redes.Proyecto', 'listaIniciada');
        $this->dropColumn('redes.Proyecto', 'idPlantillaOrigen');

        // dropTable elimina tambien las llaves foraneas que salen de la tabla.
        $this->dropTable('redes.ProyectoItem');
        $this->dropTable('redes.PlantillaItem');
        $this->dropTable('redes.PlantillaCategoria');
        $this->dropTable('redes.Plantilla');
    }
}
