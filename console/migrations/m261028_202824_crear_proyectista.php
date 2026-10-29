<?php

use yii\db\Migration;

/**
 * Lista de proyectistas (redes.Proyectista), para que el encargado la
 * administre desde el sistema (2026-10-28). Antes estaba en
 * backend/config/parametros-proyecto.json y solo se cambiaba editando ese
 * archivo en el servidor.
 *
 * Es solo una lista de NOMBRES para el desplegable "Proyectistas asignados":
 * no son usuarios ni tienen permisos (la gestion de usuarios es del sistema
 * institucional).
 *
 * redes.ProyectoProyectista no cambia: sigue guardando una COPIA del nombre,
 * asi que corregir o desactivar un proyectista no altera los proyectos que ya
 * lo tienen. Por eso no hay llave foranea.
 *
 * Se cargan los dos nombres que tenia el JSON y los que ya usan los
 * proyectos, activos.
 */
class m261028_202824_crear_proyectista extends Migration
{
    /** Los proyectistas que tenia el JSON. */
    private const DEL_JSON = ['Ing. Wilbert Dias', 'Ing. Paola Rojas'];

    public function safeUp()
    {
        $this->createTable('redes.Proyectista', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(200)->notNull(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
        ]);
        // Sin repetidos: el proyecto guarda el nombre, no el id.
        $this->createIndex('ux-proyectista-nombre', 'redes.Proyectista', 'nombre', true);

        $enUso = $this->db->createCommand('SELECT DISTINCT LTRIM(RTRIM(nombre)) FROM redes.ProyectoProyectista')->queryColumn();
        $nombres = [];

        // La comparacion de la base no distingue mayusculas: se evita
        // insertar dos veces el mismo nombre escrito distinto.
        foreach (array_merge(self::DEL_JSON, $enUso) as $nombre) {
            if ($nombre !== '' && !isset($nombres[mb_strtolower($nombre, 'UTF-8')])) {
                $nombres[mb_strtolower($nombre, 'UTF-8')] = $nombre;
                $this->insert('redes.Proyectista', ['nombre' => $nombre]);
            }
        }
    }

    public function safeDown()
    {
        $this->dropTable('redes.Proyectista');
    }
}
