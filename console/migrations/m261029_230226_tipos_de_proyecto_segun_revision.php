<?php

use yii\db\Migration;

/**
 * Tipos de proyecto segun la revision con el Ing. de DTIC del 2026-10-29.
 *
 * Un tipo dice QUE se hace (ampliar, mejorar, refuncionalizar, equipar), no
 * el sistema que se instala: "Cableado estructurado" y "Videovigilancia" son
 * sistemas y salen de la lista. "Ampliación de red" y "Equipamiento activo"
 * se acortan a "Ampliación" y "Equipamiento".
 *
 *  - Renombrar cambia tambien el tipo que muestran los proyectos que ya lo
 *    tenian (es el mismo registro), que es lo que se busca.
 *  - Un tipo que sale se BORRA si nadie lo usa (el servidor de DTIC empieza
 *    de cero) y se DESACTIVA si algun proyecto o plantilla lo tiene: ese lo
 *    conserva, pero ya no se ofrece (TipoProyecto::reglaElegible()).
 *  - Mejoramiento y Refuncionalizacion se crean si no existen (en la base
 *    local se habian agregado a mano) y se activan si estaban desactivados.
 */
class m261029_230226_tipos_de_proyecto_segun_revision extends Migration
{
    /**
     * nombre anterior => nombre nuevo.
     */
    private const RENOMBRAR = [
        'Ampliación de red' => 'Ampliación',
        'Equipamiento activo' => 'Equipamiento',
    ];

    private const QUITAR = ['Cableado estructurado', 'Videovigilancia'];

    private const ASEGURAR = ['Mejoramiento', 'Refuncionalización'];

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        foreach (self::RENOMBRAR as $anterior => $nuevo) {
            // Si alguien ya creo el nombre nuevo a mano, el indice unico no
            // deja renombrar: se avisa y se deja como esta.
            if ($this->idDe($nuevo) !== null) {
                echo "    > \"$nuevo\" ya existe: \"$anterior\" queda como esta\n";
                continue;
            }

            $this->update('redes.TipoProyecto', ['nombre' => $nuevo], ['nombre' => $anterior]);
        }

        foreach (self::QUITAR as $nombre) {
            $id = $this->idDe($nombre);

            if ($id === null) {
                continue;
            }

            if ($this->enUso($id)) {
                echo "    > \"$nombre\" esta en uso: se desactiva\n";
                $this->update('redes.TipoProyecto', ['activo' => false], ['id' => $id]);
            } else {
                $this->delete('redes.TipoProyecto', ['id' => $id]);
            }
        }

        foreach (self::ASEGURAR as $nombre) {
            if ($this->idDe($nombre) === null) {
                $this->insert('redes.TipoProyecto', ['nombre' => $nombre, 'activo' => true]);
            } else {
                $this->update('redes.TipoProyecto', ['activo' => true], ['nombre' => $nombre]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Vuelven los nombres y los tipos quitados (activos). Mejoramiento y
        // Refuncionalizacion se dejan: no se sabe si existian antes.
        foreach (self::RENOMBRAR as $anterior => $nuevo) {
            if ($this->idDe($anterior) === null) {
                $this->update('redes.TipoProyecto', ['nombre' => $anterior], ['nombre' => $nuevo]);
            }
        }

        foreach (self::QUITAR as $nombre) {
            if ($this->idDe($nombre) === null) {
                $this->insert('redes.TipoProyecto', ['nombre' => $nombre, 'activo' => true]);
            } else {
                $this->update('redes.TipoProyecto', ['activo' => true], ['nombre' => $nombre]);
            }
        }
    }

    private function idDe(string $nombre): ?int
    {
        $id = $this->db->createCommand('SELECT id FROM redes.TipoProyecto WHERE nombre = :nombre', [':nombre' => $nombre])
            ->queryScalar();

        return $id === false ? null : (int) $id;
    }

    private function enUso(int $id): bool
    {
        return (bool) $this->db->createCommand(
            // pdo_sqlsrv no deja repetir un mismo parametro con nombre.
            'SELECT CASE WHEN EXISTS (SELECT 1 FROM redes.Proyecto WHERE idTipoProyecto = :idProyecto)'
            . ' OR EXISTS (SELECT 1 FROM redes.Plantilla WHERE idTipoProyecto = :idPlantilla) THEN 1 ELSE 0 END',
            [':idProyecto' => $id, ':idPlantilla' => $id]
        )->queryScalar();
    }
}
