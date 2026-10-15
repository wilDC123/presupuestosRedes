<?php

declare(strict_types=1);

namespace backend\models;

use DateTime;
use DateTimeImmutable;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * Modelo para la tabla "redes.Cotizacion".
 *
 * idSolicitudCotizacion no tiene regla a proposito: lo pone
 * CotizacionService al registrar la cotizacion desde su solicitud, y asi no
 * se puede enviar ni cambiar desde un formulario.
 *
 * @property int $id
 * @property int $idProveedor
 * @property string|null $numeroReferencia
 * @property string|null $fecha
 * @property string|null $documentoRuta
 * @property int|null $validezDias
 * @property int|null $idSolicitudCotizacion Solicitud del proyecto a la que responde (NULL = cotizacion suelta).
 *
 * @property-read Proveedor $proveedor
 * @property-read CotizacionDetalle[] $cotizacionDetalles
 * @property-read SolicitudCotizacion|null $solicitud
 */
class Cotizacion extends ActiveRecord
{
    /**
     * Atributo virtual: no existe como columna en la tabla. Solo se usa
     * para recibir el archivo PDF del formulario antes de guardarlo en disco.
     */
    public ?UploadedFile $documentoFile = null;

    public static function tableName(): string
    {
        return 'redes.Cotizacion';
    }

    public function rules(): array
    {
        return [
            [['idProveedor'], 'required'],
            [['idProveedor', 'validezDias'], 'integer'],
            [['fecha'], 'date', 'format' => 'php:Y-m-d'],
            [['numeroReferencia'], 'string', 'max' => 100],
            [['documentoRuta'], 'string', 'max' => 500],
            [['documentoFile'], 'file', 'extensions' => 'pdf', 'maxSize' => 10 * 1024 * 1024],
            [['idProveedor'], 'exist', 'skipOnError' => true, 'targetClass' => Proveedor::class, 'targetAttribute' => ['idProveedor' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idProveedor' => 'Proveedor',
            'numeroReferencia' => 'Número de referencia',
            'fecha' => 'Fecha',
            'documentoRuta' => 'Documento',
            'validezDias' => 'Validez (días)',
            'documentoFile' => 'Archivo PDF de la cotización',
        ];
    }

    public function getProveedor(): ActiveQuery
    {
        return $this->hasOne(Proveedor::class, ['id' => 'idProveedor']);
    }

    public function getCotizacionDetalles(): ActiveQuery
    {
        return $this->hasMany(CotizacionDetalle::class, ['idCotizacion' => 'id']);
    }

    public function getSolicitud(): ActiveQuery
    {
        return $this->hasOne(SolicitudCotizacion::class, ['id' => 'idSolicitudCotizacion']);
    }

    /**
     * Suma de las lineas (precioTotal lo calcula SQL Server).
     */
    public function total(): float
    {
        return array_sum(array_map(static fn (CotizacionDetalle $detalle) => (float) $detalle->precioTotal, $this->cotizacionDetalles));
    }

    /**
     * Una cotizacion esta vencida cuando ya paso su ultimo dia de validez:
     * fecha + validezDias < hoy. Ejemplo: fecha 01/09 con 30 dias de validez
     * vale hasta el 01/10 inclusive y esta vencida desde el 02/10.
     *
     * Si falta la fecha o los dias de validez (las dos columnas permiten
     * NULL) no hay forma de saberlo, y se considera NO vencida.
     *
     * $hoy solo se pasa en las pruebas; en uso normal se toma la fecha actual.
     */
    public function estaVencida(?DateTimeImmutable $hoy = null): bool
    {
        if (empty($this->fecha) || $this->validezDias === null || $this->validezDias === '') {
            return false;
        }

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->fecha);

        if ($fecha === false) {
            return false;
        }

        $venceEl = $fecha->modify('+' . (int) $this->validezDias . ' days');
        $hoy = ($hoy ?? new DateTimeImmutable())->setTime(0, 0);

        return $venceEl < $hoy;
    }

    /**
     * documentoFile queda fuera de los atributos "safe" a proposito. La
     * regla 'file' en rules() lo vuelve validable, pero eso tambien lo
     * vuelve asignable en masa: si load() lo encuentra en $_POST (los
     * inputs type="file" no viajan ahi, pero algunos navegadores/proxies
     * igual mandan un valor vacio con ese nombre) intenta asignar un string
     * a una propiedad tipada como ?UploadedFile y explota con TypeError.
     * El controlador ya asigna este atributo de forma explicita con
     * UploadedFile::getInstance() justo despues de load(), asi que no
     * necesita pasar por la asignacion masiva.
     */
    public function safeAttributes(): array
    {
        return array_values(array_diff(parent::safeAttributes(), ['documentoFile']));
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
}
