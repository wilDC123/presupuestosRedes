<?php

use yii\db\Migration;

/**
 * Las especificaciones tecnicas pasan a ser una LIBRERIA independiente:
 *  - ya no dependen de un item del subcatalogo (idSubcatalogo queda como
 *    "item de referencia" opcional),
 *  - se clasifican con las mismas categorias del subcatalogo,
 *  - se ENLAZAN a los items de la lista de un proyecto (como maximo una por
 *    item, y siempre opcional).
 */
class m261015_035448_libreria_especificaciones extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // SQL Server no permite ALTER COLUMN sobre una columna que participa
        // en una llave foranea: se quita la FK, se cambia la columna a NULL y
        // se vuelve a crear la misma FK (una FK sobre una columna NULL es
        // valida: solo se verifica cuando la columna tiene valor).
        $this->dropForeignKey('fk-especificacion-idsubcatalogo', 'redes.Especificacion');
        $this->alterColumn('redes.Especificacion', 'idSubcatalogo', $this->integer()->null());
        $this->addForeignKey('fk-especificacion-idsubcatalogo', 'redes.Especificacion', 'idSubcatalogo', 'redes.SubcatalogoItem', 'id');

        // Clasificacion de la especificacion en la libreria. NULL en la base
        // (por si se importan fichas antiguas sin clasificar); el modelo la
        // exige al crear o editar desde el formulario.
        $this->addColumn('redes.Especificacion', 'idCategoria', $this->integer()->null());
        $this->addForeignKey('fk-especificacion-idcategoria', 'redes.Especificacion', 'idCategoria', 'redes.Categoria', 'id');
        // SQL Server no crea solo el indice de una FK: sin el, filtrar la
        // libreria por categoria recorreria toda la tabla.
        $this->createIndex('idx-especificacion-idcategoria', 'redes.Especificacion', 'idCategoria');

        // Especificacion enlazada a un item de la lista del proyecto (como
        // maximo una; NULL = el item no necesita especificacion). Sin
        // ON DELETE CASCADE: borrar una especificacion en uso debe fallar,
        // no dejar items sin su ficha sin que nadie lo note.
        $this->addColumn('redes.ProyectoItem', 'idEspecificacion', $this->integer()->null());
        $this->addForeignKey('fk-proyectoitem-idespecificacion', 'redes.ProyectoItem', 'idEspecificacion', 'redes.Especificacion', 'id');
        // Para "donde se usa esta especificacion" sin recorrer toda la tabla.
        $this->createIndex('idx-proyectoitem-idespecificacion', 'redes.ProyectoItem', 'idEspecificacion');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Volver idSubcatalogo a NOT NULL solo es posible si todas las
        // especificaciones tienen item de referencia. Si alguna no lo tiene,
        // se detiene aqui sin tocar nada: no se borran ni se inventan datos.
        $sinItem = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM redes.Especificacion WHERE idSubcatalogo IS NULL'
        )->queryScalar();

        if ($sinItem > 0) {
            echo "No se puede revertir: $sinItem especificación(es) no tienen ítem de referencia (idSubcatalogo NULL).\n";
            echo "Asígneles un ítem del subcatálogo (o elimínelas) y vuelva a intentar.\n";

            return false;
        }

        $this->dropIndex('idx-proyectoitem-idespecificacion', 'redes.ProyectoItem');
        $this->dropForeignKey('fk-proyectoitem-idespecificacion', 'redes.ProyectoItem');
        $this->dropColumn('redes.ProyectoItem', 'idEspecificacion');

        $this->dropIndex('idx-especificacion-idcategoria', 'redes.Especificacion');
        $this->dropForeignKey('fk-especificacion-idcategoria', 'redes.Especificacion');
        $this->dropColumn('redes.Especificacion', 'idCategoria');

        $this->dropForeignKey('fk-especificacion-idsubcatalogo', 'redes.Especificacion');
        $this->alterColumn('redes.Especificacion', 'idSubcatalogo', $this->integer()->notNull());
        $this->addForeignKey('fk-especificacion-idsubcatalogo', 'redes.Especificacion', 'idSubcatalogo', 'redes.SubcatalogoItem', 'id');
    }
}
