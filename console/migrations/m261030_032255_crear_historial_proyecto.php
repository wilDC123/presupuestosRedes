<?php

use yii\db\Migration;

/**
 * Historial del proyecto (revision del Ing. de DTIC del 2026-10-29, paso 6):
 * "cualquier cambio a futuro se tiene que poder consultar"; quien y cuando
 * cambio los datos del proyecto, los precios del presupuesto y sus estados.
 * De aqui salen tambien la fecha de revision y la de ultima modificacion.
 *
 * redes.HistorialProyecto: una fila por hecho. Nunca se modifica ni se borra.
 *  - usuario: COPIA del nombre de usuario (NULL = antes del historial), asi
 *    no depende de redes.Usuario, que se reemplazara por la autenticacion
 *    institucional.
 *  - idProyecto SIN llave foranea y con copia del codigo: si se elimina el
 *    proyecto, su historial queda.
 *  - estado: el nuevo estado del presupuesto, solo en los cambios de estado
 *    (para calcular la fecha de revision sin leer el texto del detalle).
 *
 * Se carga el pasado que el sistema conoce: la creacion de cada proyecto y
 * de cada presupuesto, y la aprobacion de los aprobados, sin usuario.
 */
class m261030_032255_crear_historial_proyecto extends Migration
{
    private const TABLA = 'redes.HistorialProyecto';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable(self::TABLA, [
            'id' => $this->primaryKey(),
            'fecha' => $this->dateTime()->notNull(),
            'usuario' => $this->string(100)->null(),
            'idProyecto' => $this->integer()->notNull(),
            'codigoProyecto' => $this->string(50)->null(),
            'accion' => $this->string(40)->notNull(),
            'estado' => $this->string(20)->null(),
            'detalle' => $this->text()->null(),
        ]);
        $this->createIndex('idx-historialproyecto-proyecto-fecha', self::TABLA, ['idProyecto', 'fecha']);

        $this->execute(
            "INSERT INTO redes.HistorialProyecto (fecha, usuario, idProyecto, codigoProyecto, accion, detalle)
             SELECT fechaCreacion, NULL, id, codigo, 'proyecto_creado', nombre FROM redes.Proyecto"
        );
        $this->execute(
            "INSERT INTO redes.HistorialProyecto (fecha, usuario, idProyecto, codigoProyecto, accion, detalle)
             SELECT pr.fechaCreacion, NULL, pr.idProyecto, p.codigo, 'presupuesto_creado', pr.numeroPresupuesto
             FROM redes.Presupuesto pr JOIN redes.Proyecto p ON p.id = pr.idProyecto"
        );
        $this->execute(
            "INSERT INTO redes.HistorialProyecto (fecha, usuario, idProyecto, codigoProyecto, accion, estado, detalle)
             SELECT CAST(pr.fechaAprobacion AS datetime), NULL, pr.idProyecto, p.codigo, 'presupuesto_estado', 'aprobado', pr.numeroPresupuesto
             FROM redes.Presupuesto pr JOIN redes.Proyecto p ON p.id = pr.idProyecto
             WHERE pr.estado = 'aprobado' AND pr.fechaAprobacion IS NOT NULL"
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable(self::TABLA);
    }
}
