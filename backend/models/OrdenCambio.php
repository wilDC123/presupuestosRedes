<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.OrdenCambio": registro de por que se reabrio
 * algo que ya estaba bloqueado (la lista enviada a cotizacion). En pantalla
 * se llama "REAPERTURA DE LA LISTA" desde el 2026-11-09: la ORDEN DE CAMBIO
 * del formato RD-GD-FR-006 es otra cosa (un presupuesto adicional de una
 * fase aprobada, parte 6 de la revision del 2026-11-05). La tabla y la clase
 * conservan su nombre.
 *
 * Solo "motivo" llega del formulario. Proyecto, alcance, quien la solicito y
 * el estado los pone OrdenCambioService: por eso no tienen regla (un atributo
 * sin reglas no es "seguro" y load() lo ignora).
 *
 * @property int $id
 * @property int $idProyecto
 * @property string $alcance Que reabre: 'lista' o 'presupuesto'.
 * @property string $motivo
 * @property string $solicitadoPor Nombre de usuario de quien la registro.
 * @property string $fecha
 * @property string $estado 'abierta' mientras se corrige; 'cerrada' al terminar.
 * @property string|null $fechaCierre
 *
 * @property-read Proyecto $proyecto
 */
class OrdenCambio extends ActiveRecord
{
    public const ALCANCE_LISTA = 'lista';
    public const ALCANCE_PRESUPUESTO = 'presupuesto';

    public const ESTADO_ABIERTA = 'abierta';
    public const ESTADO_CERRADA = 'cerrada';

    public static function tableName(): string
    {
        return 'redes.OrdenCambio';
    }

    public function rules(): array
    {
        return [
            [['motivo'], 'trim'],
            [['motivo'], 'required', 'message' => 'Escriba el motivo para reabrir la lista.'],
            [['motivo'], 'string', 'max' => 500],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'motivo' => 'Motivo para reabrir la lista',
            'solicitadoPor' => 'Solicitado por',
            'fecha' => 'Fecha',
            'estado' => 'Estado',
            'fechaCierre' => 'Fecha de cierre',
        ];
    }

    public function getProyecto(): ActiveQuery
    {
        return $this->hasOne(Proyecto::class, ['id' => 'idProyecto']);
    }

    public function estaAbierta(): bool
    {
        return $this->estado === self::ESTADO_ABIERTA;
    }
}
