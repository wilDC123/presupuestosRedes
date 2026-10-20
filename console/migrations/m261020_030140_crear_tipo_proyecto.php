<?php

use yii\db\Migration;

/**
 * Catalogo de tipos de proyecto (redes.TipoProyecto), para que el encargado
 * los administre desde el sistema. Antes estaban en
 * backend/config/parametros-proyecto.json y solo se cambiaban editando ese
 * archivo en el servidor.
 *
 *  - redes.Proyecto: la columna de texto tipo (el codigo del JSON, ej.
 *    "videovigilancia") se reemplaza por la FK idTipoProyecto.
 *  - redes.Plantilla: igual, tipoProyecto pasa a idTipoProyecto.
 *
 * Con la FK, la base impide borrar un tipo que algun proyecto o plantilla
 * usa: se desactiva (igual que UnidadMedida).
 *
 * Un codigo guardado que no es de los tipos iniciales no se pierde: se crea
 * como tipo nuevo con ese mismo texto.
 */
class m261020_030140_crear_tipo_proyecto extends Migration
{
    /**
     * Tipos iniciales, los que tenia el JSON: codigo antiguo => nombre.
     */
    private const TIPOS = [
        'ampliacion_red' => 'Ampliación de red',
        'cableado_estructurado' => 'Cableado estructurado',
        'videovigilancia' => 'Videovigilancia',
        'equipamiento_activo' => 'Equipamiento activo',
    ];

    /**
     * Columnas de texto que se reemplazan: tabla => columna.
     */
    private const COLUMNAS = [
        'redes.Proyecto' => 'tipo',
        'redes.Plantilla' => 'tipoProyecto',
    ];

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.TipoProyecto', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(100)->notNull(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
        ]);

        // Sin repetidos: dos "Videovigilancia" harian ambiguo el desplegable.
        $this->createIndex('ux-tipoproyecto-nombre', 'redes.TipoProyecto', 'nombre', true);

        /** @var array<string, int> $idPorCodigo */
        $idPorCodigo = [];

        foreach (self::TIPOS as $codigo => $nombre) {
            $idPorCodigo[$codigo] = $this->insertarTipo($nombre);
        }

        foreach (self::COLUMNAS as $tabla => $columna) {
            $this->addColumn($tabla, 'idTipoProyecto', $this->integer()->null());

            $codigos = $this->db->createCommand(
                "SELECT DISTINCT [$columna] FROM $tabla WHERE [$columna] IS NOT NULL AND [$columna] <> ''"
            )->queryColumn();

            foreach ($codigos as $codigo) {
                $idPorCodigo[$codigo] ??= $this->insertarTipo(mb_substr($codigo, 0, 100));

                echo "    > $tabla: \"$codigo\" => " . $idPorCodigo[$codigo] . "\n";

                $this->update($tabla, ['idTipoProyecto' => $idPorCodigo[$codigo]], [$columna => $codigo]);
            }

            $sufijo = strtolower(str_replace('redes.', '', $tabla));
            // Sin ON DELETE: borrar un tipo en uso debe fallar.
            $this->addForeignKey("fk-$sufijo-idtipoproyecto", $tabla, 'idTipoProyecto', 'redes.TipoProyecto', 'id');
            // SQL Server no crea solo el indice de una FK.
            $this->createIndex("idx-$sufijo-idtipoproyecto", $tabla, 'idTipoProyecto');

            $this->dropColumn($tabla, $columna);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Vuelve el codigo de texto. Los tipos iniciales recuperan su codigo
        // del JSON; uno creado despues queda con su nombre como codigo.
        $codigoPorNombre = array_flip(self::TIPOS);

        foreach (self::COLUMNAS as $tabla => $columna) {
            $this->addColumn($tabla, $columna, $this->string(50));

            $tipos = $this->db->createCommand(
                "SELECT DISTINCT t.id, t.nombre FROM redes.TipoProyecto t INNER JOIN $tabla x ON x.idTipoProyecto = t.id"
            )->queryAll();

            foreach ($tipos as $tipo) {
                $codigo = $codigoPorNombre[$tipo['nombre']] ?? mb_substr($tipo['nombre'], 0, 50);
                $this->update($tabla, [$columna => $codigo], ['idTipoProyecto' => $tipo['id']]);
            }

            $sufijo = strtolower(str_replace('redes.', '', $tabla));
            $this->dropIndex("idx-$sufijo-idtipoproyecto", $tabla);
            $this->dropForeignKey("fk-$sufijo-idtipoproyecto", $tabla);
            $this->dropColumn($tabla, 'idTipoProyecto');
        }

        $this->dropTable('redes.TipoProyecto');
    }

    private function insertarTipo(string $nombre): int
    {
        $this->insert('redes.TipoProyecto', ['nombre' => $nombre]);

        return (int) $this->db->getLastInsertID();
    }
}
