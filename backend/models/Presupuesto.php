<?php

declare(strict_types=1);

namespace backend\models;

use DateTime;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Presupuesto".
 *
 * @property int $id
 * @property int $idProyecto
 * @property int|null $idVersionAnterior
 * @property int $numeroVersion
 * @property string|null $numeroPresupuesto
 * @property string|null $oficioDtic
 * @property string|null $areaIntervencion
 * @property string $estado
 * @property string|null $fecha
 * @property string $fechaCreacion
 *
 * @property-read Proyecto $proyecto
 * @property-read Presupuesto|null $versionAnterior
 * @property-read Presupuesto|null $versionSiguiente
 * @property-read PresupuestoDetalle[] $presupuestoDetalles
 */
class Presupuesto extends ActiveRecord
{
    public const ESTADO_EN_PROCESO = 'en_proceso';
    public const ESTADO_EN_REVISION = 'en_revision';
    public const ESTADO_OBSERVADO = 'observado';
    public const ESTADO_APROBADO = 'aprobado';
    public const ESTADO_CANCELADO = 'cancelado';

    public static function tableName(): string
    {
        return 'redes.Presupuesto';
    }

    /**
     * Los unicos 5 valores permitidos para "estado" (todo en minusculas).
     * Se usa tanto para el validador 'in' de rules() como para poblar el
     * dropdown del formulario y el badge de color.
     */
    public static function estados(): array
    {
        return [
            self::ESTADO_EN_PROCESO => 'En proceso',
            self::ESTADO_EN_REVISION => 'En revisión',
            self::ESTADO_OBSERVADO => 'Observado',
            self::ESTADO_APROBADO => 'Aprobado',
            self::ESTADO_CANCELADO => 'Cancelado',
        ];
    }

    /**
     * Mapa de transiciones validas: estado actual => lista de estados a los
     * que se puede pasar desde ahi. Es la UNICA fuente de verdad sobre el
     * ciclo de vida del presupuesto. La validacion de un cambio de estado se
     * hace en PresupuestoService::puedeTransicionarA(); la vista view.php
     * tambien lo lee para dibujar los botones de estado.
     */
    public static function transicionesPermitidas(): array
    {
        return [
            self::ESTADO_EN_PROCESO => [self::ESTADO_EN_REVISION],
            self::ESTADO_EN_REVISION => [self::ESTADO_OBSERVADO, self::ESTADO_APROBADO, self::ESTADO_CANCELADO],
            self::ESTADO_OBSERVADO => [self::ESTADO_EN_PROCESO],
            self::ESTADO_APROBADO => [],
            self::ESTADO_CANCELADO => [],
        ];
    }

    /**
     * REGLA DE NEGOCIO CRITICA: un presupuesto aprobado o cancelado nunca se
     * edita directamente. Cualquier cambio requiere
     * PresupuestoService::crearNuevaVersion(). Se queda en el modelo porque
     * las vistas (view.php, detalle.php) la usan para mostrar u ocultar
     * botones; los controladores la consultan via PresupuestoService.
     */
    public function puedeEditarse(): bool
    {
        return !in_array($this->estado, [self::ESTADO_APROBADO, self::ESTADO_CANCELADO], true);
    }

    public function rules(): array
    {
        return [
            [['idProyecto'], 'required'],
            [['idProyecto', 'idVersionAnterior', 'numeroVersion'], 'integer'],
            [['numeroVersion'], 'default', 'value' => 1],
            [['estado'], 'default', 'value' => self::ESTADO_EN_PROCESO],
            [['estado'], 'required'],
            [['estado'], 'in', 'range' => array_keys(self::estados())],
            [['fecha'], 'date', 'format' => 'php:Y-m-d'],
            [['numeroPresupuesto'], 'string', 'max' => 20],
            [['oficioDtic'], 'string', 'max' => 100],
            [['areaIntervencion'], 'string', 'max' => 300],
            [['idProyecto'], 'exist', 'skipOnError' => true, 'targetClass' => Proyecto::class, 'targetAttribute' => ['idProyecto' => 'id']],
            [['idVersionAnterior'], 'exist', 'skipOnError' => true, 'targetClass' => self::class, 'targetAttribute' => ['idVersionAnterior' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idProyecto' => 'Proyecto',
            'idVersionAnterior' => 'Versión anterior',
            'numeroVersion' => 'N.° de versión',
            'numeroPresupuesto' => 'N.° de presupuesto',
            'oficioDtic' => 'Oficio DTIC',
            'areaIntervencion' => 'Área de intervención',
            'estado' => 'Estado',
            'fecha' => 'Fecha',
            'fechaCreacion' => 'Fecha de creación',
        ];
    }

    public function getProyecto(): ActiveQuery
    {
        return $this->hasOne(Proyecto::class, ['id' => 'idProyecto']);
    }

    public function getVersionAnterior(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'idVersionAnterior']);
    }

    /**
     * Version siguiente: el presupuesto (si existe) cuyo idVersionAnterior
     * apunta a este. Es la relacion inversa de getVersionAnterior().
     */
    public function getVersionSiguiente(): ActiveQuery
    {
        return $this->hasOne(self::class, ['idVersionAnterior' => 'id']);
    }

    public function getPresupuestoDetalles(): ActiveQuery
    {
        return $this->hasMany(PresupuestoDetalle::class, ['idPresupuesto' => 'id']);
    }

    /**
     * fecha permite NULL, pero el input tipo "date" envia '' cuando se deja
     * en blanco. SQL Server no puede convertir '' a date, asi que hay que
     * normalizar a null antes de guardar (mismo riesgo que idSigma en
     * SubcatalogoItem).
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->fecha === '') {
            $this->fecha = null;
        }

        return true;
    }

    /**
     * El formulario muestra y recibe fecha en dd/mm/aaaa (convencion local),
     * pero el validador 'date' de rules() y la columna date de SQL Server
     * esperan aaaa-mm-dd. Esto tiene que pasar en beforeValidate() -- no en
     * beforeSave() -- porque el validador 'date' corre ANTES que
     * beforeSave() y rechazaria "11/08/2026" como formato invalido.
     */
    public function beforeValidate(): bool
    {
        $this->fecha = self::convertirFechaFormularioABd($this->fecha);

        return parent::beforeValidate();
    }

    /**
     * Convierte "dd/mm/aaaa" (o "d/m/aaaa") a "aaaa-mm-dd". Si el valor no
     * coincide con ese patron (ya viene en aaaa-mm-dd, esta vacio, o es
     * invalido) se devuelve sin cambios para que el validador 'date' decida.
     */
    private static function convertirFechaFormularioABd(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', trim($valor), $partes) === 1) {
            [, $dia, $mes, $anio] = $partes;

            return sprintf('%04d-%02d-%02d', (int) $anio, (int) $mes, (int) $dia);
        }

        return $valor;
    }

    /**
     * fecha para mostrar en pantalla (dd/mm/aaaa), no para guardar.
     */
    public function fechaFormateada(): string
    {
        if (empty($this->fecha)) {
            return '';
        }

        $fecha = DateTime::createFromFormat('Y-m-d', $this->fecha);

        return $fecha !== false ? $fecha->format('d/m/Y') : $this->fecha;
    }

    public function estadoLabel(): string
    {
        return self::estados()[$this->estado] ?? $this->estado;
    }

    /**
     * Clase de badge de Bootstrap segun el estado (HTML simple, sin CSS
     * propio -- las clases "badge bg-*" ya vienen incluidas con el tema).
     */
    public function estadoBadgeClass(): string
    {
        return match ($this->estado) {
            self::ESTADO_EN_PROCESO => 'badge bg-secondary',
            self::ESTADO_EN_REVISION => 'badge bg-info',
            self::ESTADO_OBSERVADO => 'badge bg-warning',
            self::ESTADO_APROBADO => 'badge bg-success',
            self::ESTADO_CANCELADO => 'badge bg-danger',
            default => 'badge bg-secondary',
        };
    }
}
