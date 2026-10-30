<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Equipo del proyecto (revision del Ing. de DTIC del 2026-10-29, paso 5): en
 * un proyecto participan varias personas, cada una con su ROL (proyectista,
 * director del proyecto, coordinador de infraestructura...), y una de ellas
 * es la RESPONSABLE del proyecto. Saldra en la ficha tecnica del proyecto.
 *
 *  - redes.Personal reemplaza a redes.Proyectista: las mismas personas, pero
 *    sin rol fijo (una persona puede ser proyectista en un proyecto y
 *    coordinador en otro). Sigue siendo solo una lista de NOMBRES, no usuarios.
 *  - redes.RolProyecto: catalogo de roles, editable por el encargado. Se
 *    carga con los que nombro el Ing.
 *  - redes.ProyectoParticipante reemplaza a redes.ProyectoProyectista: COPIA
 *    del nombre (como antes: la lista de personas vendra despues del sistema
 *    institucional) + rol (FK, el catalogo es nuestro) + si es la responsable.
 *    Una misma persona puede tener dos roles, pero no el mismo dos veces; un
 *    solo responsable por proyecto (indice unico filtrado).
 *
 * Los proyectistas que ya tenian los proyectos pasan con el rol Proyectista;
 * el primero de cada proyecto queda como responsable.
 */
class m261030_030403_equipo_del_proyecto extends Migration
{
    private const ROLES = [
        'Proyectista',
        'Director del proyecto',
        'Coordinador de infraestructura',
        'Responsable de despliegue',
        'Responsable de administración',
        'Supervisor',
    ];

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.Personal', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(200)->notNull(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
        ]);
        $this->createIndex('ux-personal-nombre', 'redes.Personal', 'nombre', true);
        $this->execute('INSERT INTO redes.Personal (nombre, activo) SELECT nombre, activo FROM redes.Proyectista ORDER BY id');

        $this->createTable('redes.RolProyecto', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(100)->notNull(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
        ]);
        $this->createIndex('ux-rolproyecto-nombre', 'redes.RolProyecto', 'nombre', true);
        foreach (self::ROLES as $nombre) {
            $this->insert('redes.RolProyecto', ['nombre' => $nombre]);
        }

        $this->createTable('redes.ProyectoParticipante', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'nombre' => $this->string(200)->notNull(),
            'idRol' => $this->integer()->notNull(),
            'esResponsable' => $this->boolean()->notNull()->defaultValue(0),
            'orden' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->addForeignKey('fk-proyectoparticipante-idproyecto', 'redes.ProyectoParticipante', 'idProyecto', 'redes.Proyecto', 'id', 'CASCADE');
        // Sin ON DELETE: un rol en uso no se borra.
        $this->addForeignKey('fk-proyectoparticipante-idrol', 'redes.ProyectoParticipante', 'idRol', 'redes.RolProyecto', 'id');
        $this->createIndex('idx-proyectoparticipante-idrol', 'redes.ProyectoParticipante', 'idRol');
        $this->createIndex('ux-proyectoparticipante-persona-rol', 'redes.ProyectoParticipante', ['idProyecto', 'nombre', 'idRol'], true);
        $this->execute('CREATE UNIQUE INDEX [ux-proyectoparticipante-responsable] ON redes.ProyectoParticipante (idProyecto) WHERE esResponsable = 1');

        $idProyectista = (int) (new Query())->select('id')->from('redes.RolProyecto')->where(['nombre' => 'Proyectista'])->scalar($this->db);
        $anteriores = (new Query())->from('redes.ProyectoProyectista')->orderBy(['idProyecto' => SORT_ASC, 'id' => SORT_ASC])->all($this->db);
        $vistos = [];
        foreach ($anteriores as $fila) {
            $idProyecto = (int) $fila['idProyecto'];
            $clave = $idProyecto . '|' . mb_strtolower(trim($fila['nombre']), 'UTF-8');
            if (isset($vistos[$clave])) {
                continue;
            }
            $orden = count(array_filter(array_keys($vistos), static fn ($k) => str_starts_with($k, $idProyecto . '|')));
            $vistos[$clave] = true;
            $this->insert('redes.ProyectoParticipante', [
                'idProyecto' => $idProyecto,
                'nombre' => trim($fila['nombre']),
                'idRol' => $idProyectista,
                'esResponsable' => $orden === 0 ? 1 : 0,
                'orden' => $orden,
            ]);
        }

        $this->dropTable('redes.ProyectoProyectista');
        $this->dropTable('redes.Proyectista');
    }

    /**
     * {@inheritdoc}
     *
     * Vuelve a la lista de proyectistas: solo pasan las personas con rol
     * Proyectista (los demas roles se pierden).
     */
    public function safeDown()
    {
        $this->createTable('redes.Proyectista', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(200)->notNull(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
        ]);
        $this->createIndex('ux-proyectista-nombre', 'redes.Proyectista', 'nombre', true);
        $this->execute('INSERT INTO redes.Proyectista (nombre, activo) SELECT nombre, activo FROM redes.Personal ORDER BY id');

        $this->createTable('redes.ProyectoProyectista', [
            'id' => $this->primaryKey(),
            'idProyecto' => $this->integer()->notNull(),
            'nombre' => $this->string(200)->notNull(),
        ]);
        $this->addForeignKey('fk-proyectoproyectista-idproyecto', 'redes.ProyectoProyectista', 'idProyecto', 'redes.Proyecto', 'id', 'CASCADE');
        $this->execute(
            "INSERT INTO redes.ProyectoProyectista (idProyecto, nombre)
             SELECT p.idProyecto, p.nombre FROM redes.ProyectoParticipante p
             JOIN redes.RolProyecto r ON r.id = p.idRol AND r.nombre = 'Proyectista'
             ORDER BY p.idProyecto, p.orden"
        );

        $this->dropTable('redes.ProyectoParticipante');
        $this->dropTable('redes.RolProyecto');
        $this->dropTable('redes.Personal');
    }
}
