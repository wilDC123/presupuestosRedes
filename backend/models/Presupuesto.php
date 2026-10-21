<?php

declare(strict_types=1);

namespace backend\models;

use DateTime;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Presupuesto".
 *
 * Cada proyecto tiene UN solo presupuesto (indice unico sobre idProyecto).
 * No hay versiones: una ampliacion es un proyecto nuevo de fase siguiente
 * (ver Proyecto::$idProyectoAnterior), con su propio presupuesto.
 *
 * @property int $id
 * @property int $idProyecto
 * @property string|null $numeroPresupuesto
 * @property string|null $oficioDtic
 * @property string|null $areaIntervencion
 * @property string $estado
 * @property string|null $fecha
 * @property string $fechaCreacion
 * @property string|null $manoDeObra Mano de obra a cargo de la DTIC (null = no lleva)
 *
 * @property-read Proyecto $proyecto
 * @property-read PresupuestoDetalle[] $presupuestoDetalles
 * @property-read CartaPresupuesto|null $carta
 */
class Presupuesto extends ActiveRecord
{
    public const ESTADO_EN_PROCESO = 'en_proceso';
    public const ESTADO_EN_REVISION = 'en_revision';
    public const ESTADO_OBSERVADO = 'observado';
    public const ESTADO_APROBADO = 'aprobado';
    public const ESTADO_CANCELADO = 'cancelado';

    /**
     * Escenario de Yii2 para "Enviar a revision": las mismas reglas de
     * siempre MAS la cabecera obligatoria (numero, oficio, area y fecha).
     * Mientras el presupuesto es un borrador esos datos pueden faltar.
     */
    public const SCENARIO_ENVIAR = 'enviar';

    /**
     * Mano de obra a cargo del personal de la DTIC: NO es un item del
     * subcatalogo (no se compra: no tiene partida, no se cotiza y no suma al
     * total). Se guarda como texto en el presupuesto y el PDF la muestra como
     * la ultima fila de la tabla, con esta unidad, cantidad 1 y precios 0.00.
     */
    public const MANO_DE_OBRA_DTIC = 'A ser implementado por personal de la D.T.I.C.';
    public const UNIDAD_MANO_DE_OBRA = 'Servicio';

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
     * edita directamente. Una ampliacion se registra como un proyecto nuevo
     * de fase siguiente. Se queda en el modelo porque
     * las vistas (view.php, detalle.php) la usan para mostrar u ocultar
     * botones; los controladores la consultan via PresupuestoService.
     */
    public function puedeEditarse(): bool
    {
        return !in_array($this->estado, [self::ESTADO_APROBADO, self::ESTADO_CANCELADO], true);
    }

    /**
     * true mientras el presupuesto es un BORRADOR ("en proceso"): solo
     * entonces se eligen precios y sus lineas siguen a la lista. En revision
     * queda de solo lectura; si lo observan, vuelve a "en proceso".
     */
    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_EN_PROCESO;
    }

    public function rules(): array
    {
        return [
            [['idProyecto'], 'required'],
            [['numeroPresupuesto', 'oficioDtic', 'areaIntervencion', 'manoDeObra'], 'trim'],
            [['numeroPresupuesto', 'oficioDtic', 'areaIntervencion', 'fecha'], 'required', 'on' => self::SCENARIO_ENVIAR, 'message' => 'Complete «{attribute}» para enviar a revisión.'],
            [['idProyecto'], 'integer'],
            // Mensaje claro en vez del error de la base por el indice unico.
            [['idProyecto'], 'unique', 'message' => 'Este proyecto ya tiene un presupuesto.'],
            [['estado'], 'default', 'value' => self::ESTADO_EN_PROCESO],
            [['estado'], 'required'],
            [['estado'], 'in', 'range' => array_keys(self::estados())],
            [['fecha'], 'date', 'format' => 'php:Y-m-d'],
            [['numeroPresupuesto'], 'string', 'max' => 20],
            [['oficioDtic'], 'string', 'max' => 100],
            [['areaIntervencion', 'manoDeObra'], 'string', 'max' => 300],
            [['idProyecto'], 'exist', 'skipOnError' => true, 'targetClass' => Proyecto::class, 'targetAttribute' => ['idProyecto' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idProyecto' => 'Proyecto',
            'numeroPresupuesto' => 'N.° de presupuesto',
            'oficioDtic' => 'Oficio DTIC',
            'areaIntervencion' => 'Área de intervención',
            'estado' => 'Estado',
            'fecha' => 'Fecha',
            'fechaCreacion' => 'Fecha de creación',
            'manoDeObra' => 'Mano de obra',
        ];
    }

    public function getProyecto(): ActiveQuery
    {
        return $this->hasOne(Proyecto::class, ['id' => 'idProyecto']);
    }

    public function getPresupuestoDetalles(): ActiveQuery
    {
        return $this->hasMany(PresupuestoDetalle::class, ['idPresupuesto' => 'id']);
    }

    /**
     * Carta de entrega (null si aun no se escribio). Como maximo una.
     */
    public function getCarta(): ActiveQuery
    {
        return $this->hasOne(CartaPresupuesto::class, ['idPresupuesto' => 'id']);
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

        // '' tambien es "no lleva mano de obra": se guarda null.
        if ($this->manoDeObra === '') {
            $this->manoDeObra = null;
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
     * Publica: la usa tambien la carta de entrega (CartaPresupuesto).
     */
    public static function convertirFechaFormularioABd(?string $valor): ?string
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
     * Clases CSS de la insignia segun el estado. Los colores "b-*" estan
     * definidos en web/css/redes.css (tomados de los mockups).
     */
    public function estadoBadgeClass(): string
    {
        return match ($this->estado) {
            self::ESTADO_EN_PROCESO => 'badge b-proceso',
            self::ESTADO_EN_REVISION => 'badge b-revision',
            self::ESTADO_OBSERVADO => 'badge b-observado',
            self::ESTADO_APROBADO => 'badge b-aprobado',
            self::ESTADO_CANCELADO => 'badge b-cancelado',
            default => 'badge b-inactivo',
        };
    }
}
