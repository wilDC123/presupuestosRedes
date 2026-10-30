<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ProyectoParticipante": una persona del equipo
 * del proyecto, con su rol (revision del 2026-10-29; antes
 * redes.ProyectoProyectista, solo proyectistas).
 *
 * Guarda una COPIA del nombre (la lista de personas vendra despues del
 * sistema institucional; aqui no se administran usuarios) y el rol con FK
 * a redes.RolProyecto. Una sola persona del equipo es la responsable del
 * proyecto. Las reglas las aplica ProyectoService al guardar el proyecto.
 *
 * @property int $id
 * @property int $idProyecto
 * @property string $nombre
 * @property int $idRol
 * @property bool $esResponsable
 * @property int $orden
 *
 * @property-read RolProyecto $rol
 */
class ProyectoParticipante extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.ProyectoParticipante';
    }

    public function rules(): array
    {
        return [
            [['idProyecto', 'nombre', 'idRol'], 'required'],
            [['idProyecto', 'idRol', 'orden'], 'integer'],
            [['nombre'], 'string', 'max' => 200],
            [['esResponsable'], 'boolean'],
        ];
    }

    public function getRol(): ActiveQuery
    {
        return $this->hasOne(RolProyecto::class, ['id' => 'idRol']);
    }

    /**
     * Como llega del formulario y como se le devuelve (ver Proyecto::$equipo).
     *
     * @return array{nombre: string, idRol: string}
     */
    public function fila(): array
    {
        return ['nombre' => $this->nombre, 'idRol' => (string) $this->idRol];
    }
}
