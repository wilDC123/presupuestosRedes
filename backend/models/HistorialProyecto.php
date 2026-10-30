<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.HistorialProyecto": un hecho en la vida del
 * proyecto (quien, cuando, que). Revision del Ing. de DTIC del 2026-10-29:
 * nada se pierde, ni un precio que se cambio ni una revision que fue
 * observada.
 *
 * Solo lo escribe HistorialService, desde los servicios donde ocurre cada
 * hecho; nunca se modifica ni se borra. No tiene llave foranea al proyecto:
 * si el proyecto se elimina, su historial queda (con la copia del codigo).
 *
 * @property int $id
 * @property string $fecha Fecha y hora de Bolivia.
 * @property string|null $usuario Nombre de usuario (copia); NULL = cargado de antes del historial.
 * @property int $idProyecto
 * @property string|null $codigoProyecto
 * @property string $accion Ver las constantes ACCION_*.
 * @property string|null $estado Nuevo estado del presupuesto (solo en ACCION_PRESUPUESTO_ESTADO).
 * @property string|null $detalle Texto para leer: "Unidad solicitante: Decanato → Rectorado"; una linea por cambio.
 */
class HistorialProyecto extends ActiveRecord
{
    public const ACCION_PROYECTO_CREADO = 'proyecto_creado';
    public const ACCION_PROYECTO_MODIFICADO = 'proyecto_modificado';
    public const ACCION_PROYECTO_ELIMINADO = 'proyecto_eliminado';
    public const ACCION_PRESUPUESTO_CREADO = 'presupuesto_creado';
    public const ACCION_PRESUPUESTO_ESTADO = 'presupuesto_estado';
    public const ACCION_PRECIOS = 'precios';

    public static function tableName(): string
    {
        return 'redes.HistorialProyecto';
    }

    /**
     * "Presupuesto aprobado", "Datos del proyecto cambiados"...
     */
    public function accionLabel(): string
    {
        return match ($this->accion) {
            self::ACCION_PROYECTO_CREADO => 'Proyecto creado',
            self::ACCION_PROYECTO_MODIFICADO => 'Datos del proyecto cambiados',
            self::ACCION_PROYECTO_ELIMINADO => 'Proyecto eliminado',
            self::ACCION_PRESUPUESTO_CREADO => 'Presupuesto creado',
            self::ACCION_PRESUPUESTO_ESTADO => 'Presupuesto: ' . mb_strtolower(Presupuesto::estados()[$this->estado] ?? (string) $this->estado, 'UTF-8'),
            self::ACCION_PRECIOS => 'Precios del presupuesto',
            default => $this->accion,
        };
    }

    /**
     * Quien lo hizo, para mostrar: el usuario o "(antes del historial)".
     */
    public function usuarioTexto(): string
    {
        return $this->usuario ?? '(antes del historial)';
    }
}
