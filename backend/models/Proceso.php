<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Proceso": los procesos de Redes y
 * Telecomunicaciones de la tabla 4 (8.5) del lineamiento RD-GD-LI-001
 * (PI, DS, GR...). Cada proyecto dice de que proceso es (Proyecto.idProceso).
 *
 * El codigo (dos letras) formara parte del codigo de los documentos
 * (RD-PI-PR-001): por eso es unico y, una vez que un proyecto usa el
 * proceso, ya no se cambia (ProcesoService::actualizar()).
 *
 * Un proceso en uso no se elimina: se desactiva. Uno inactivo ya no se
 * ofrece para elegir, pero los proyectos que lo tenian lo conservan.
 *
 * @property int $id
 * @property string $codigo ej. "PI"
 * @property string $nombre ej. "Proyectos de infraestructura de redes y servicios"
 * @property bool $activo
 */
class Proceso extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.Proceso';
    }

    public function rules(): array
    {
        return [
            [['codigo', 'nombre'], 'trim'],
            // "pi" se guarda "PI".
            [['codigo'], 'filter', 'filter' => static fn ($valor) => is_string($valor) ? mb_strtoupper($valor, 'UTF-8') : $valor],
            [['codigo', 'nombre'], 'required'],
            [['codigo'], 'match', 'pattern' => '/^[A-Z]{2}$/', 'message' => 'El código son dos letras (ej. PI), como en el lineamiento.'],
            [['nombre'], 'string', 'max' => 200],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
            // 'unique' consulta la base, asi que solo se valida en el servidor.
            [['codigo'], 'unique', 'message' => 'Ya existe un proceso con este código.'],
            [['nombre'], 'unique', 'message' => 'Ya existe un proceso con este nombre.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'codigo' => 'Código',
            'nombre' => 'Nombre',
            'activo' => 'Activo',
        ];
    }

    /**
     * "PI – Proyectos de infraestructura de redes y servicios", para los
     * desplegables y la vista del proyecto.
     */
    public function etiqueta(): string
    {
        return $this->codigo . ' – ' . $this->nombre;
    }

    /**
     * Regla para Proyecto.idProceso: el proceso debe existir y estar activo,
     * salvo que el proyecto ya lo tuviera guardado (como
     * TipoProyecto::reglaElegible()).
     */
    public static function reglaElegible(): array
    {
        return [
            ['idProceso'], 'exist',
            'targetClass' => self::class,
            'targetAttribute' => ['idProceso' => 'id'],
            'filter' => ['activo' => true],
            'when' => static fn (ActiveRecord $model) => $model->isAttributeChanged('idProceso', false),
            'message' => 'El proceso elegido no existe o está inactivo.',
        ];
    }
}
