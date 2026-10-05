<?php

use yii\db\Migration;

/**
 * Amplia `redes.Proyecto` para el formulario "Nuevo proyecto": descripcion,
 * tipo, ubicacion parametrizada (facultad/edificio/varios bloques), varios
 * proyectistas y continuidad de fases (una fase = un proyecto nuevo enlazado
 * al de la fase anterior).
 */
class m261005_042945_add_fases_y_ubicacion_to_proyecto extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('redes.Proyecto', 'descripcion', $this->string(1000));
        $this->addColumn('redes.Proyecto', 'tipo', $this->string(50));

        // Codigos de la fuente de parametros (hoy un JSON, manana quiza un
        // servicio web). No son llaves foraneas: esos datos no viven en esta
        // base. Las columnas facultad y edificio ya existentes siguen
        // guardando el NOMBRE, como copia para que un documento antiguo no
        // cambie si la universidad renombra una facultad.
        $this->addColumn('redes.Proyecto', 'codigoFacultad', $this->string(30));
        $this->addColumn('redes.Proyecto', 'codigoEdificio', $this->string(30));

        // Continuidad de fases: el proyecto de la fase anterior (NULL en la
        // fase 1) y el numero de fase.
        $this->addColumn('redes.Proyecto', 'idProyectoAnterior', $this->integer()->null());
        $this->addColumn('redes.Proyecto', 'numeroFase', $this->integer()->notNull()->defaultValue(1));

        $this->addForeignKey(
            'fk-proyecto-idproyectoanterior',
            'redes.Proyecto',
            'idProyectoAnterior',
            'redes.Proyecto',
            'id'
        );

        // Un solo texto "bloque" se reemplaza por la tabla ProyectoBloque
        // (un proyecto puede involucrar varios bloques).
        $this->dropColumn('redes.Proyecto', 'bloque');

        // El codigo (RD-PI-PT-NN) no se puede repetir. Indice FILTRADO: solo
        // se exige unicidad entre los codigos que no son NULL. Yii no tiene
        // un metodo para indices filtrados, por eso va como SQL.
        $this->execute('CREATE UNIQUE INDEX [uq-proyecto-codigo] ON redes.Proyecto (codigo) WHERE codigo IS NOT NULL');

        $this->createTable('redes.ProyectoBloque', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'codigo' => $this->string(30)->notNull(),
            'nombre' => $this->string(100)->notNull(),
        ]);

        // CASCADE: es una composicion, el bloque asignado no existe sin su
        // proyecto (igual que las lineas de un presupuesto).
        $this->addForeignKey(
            'fk-proyectobloque-idproyecto',
            'redes.ProyectoBloque',
            'idProyecto',
            'redes.Proyecto',
            'id',
            'CASCADE'
        );

        $this->createTable('redes.ProyectoProyectista', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'nombre' => $this->string(200)->notNull(),
        ]);

        $this->addForeignKey(
            'fk-proyectoproyectista-idproyecto',
            'redes.ProyectoProyectista',
            'idProyecto',
            'redes.Proyecto',
            'id',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-proyectoproyectista-idproyecto', 'redes.ProyectoProyectista');
        $this->dropTable('redes.ProyectoProyectista');

        $this->dropForeignKey('fk-proyectobloque-idproyecto', 'redes.ProyectoBloque');
        $this->dropTable('redes.ProyectoBloque');

        $this->execute('DROP INDEX [uq-proyecto-codigo] ON redes.Proyecto');

        $this->addColumn('redes.Proyecto', 'bloque', $this->string(100));

        $this->dropForeignKey('fk-proyecto-idproyectoanterior', 'redes.Proyecto');
        $this->dropColumn('redes.Proyecto', 'numeroFase');
        $this->dropColumn('redes.Proyecto', 'idProyectoAnterior');
        $this->dropColumn('redes.Proyecto', 'codigoEdificio');
        $this->dropColumn('redes.Proyecto', 'codigoFacultad');
        $this->dropColumn('redes.Proyecto', 'tipo');
        $this->dropColumn('redes.Proyecto', 'descripcion');
    }
}
