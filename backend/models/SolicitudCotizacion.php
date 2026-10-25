<?php

declare(strict_types=1);

namespace backend\models;

use DateTime;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.SolicitudCotizacion": un grupo de items de la
 * lista del proyecto que se manda a cotizar a un rubro de proveedores.
 *
 * Solo "nombre", "nota" y "fechaEnvio" llegan del formulario. El proyecto y el numero los
 * pone SolicitudCotizacionService: por eso no tienen regla.
 *
 * @property int $id
 * @property int $idProyecto
 * @property int $numero Correlativo dentro del proyecto.
 * @property string $nombre Rubro al que se envia ("Ferreteria y ducteo").
 * @property string|null $nota Texto libre para el proveedor.
 * @property string $fecha Fecha y hora de CREACION (la pone la base). No sale en el documento.
 * @property string|null $fechaEnvio aaaa-mm-dd, la que sale en el PDF. NULL = la del dia en que
 *           se imprime. Se cambia mientras la solicitud no tenga cotizaciones.
 *
 * @property-read Proyecto $proyecto
 * @property-read SolicitudCotizacionItem[] $items
 * @property-read Cotizacion[] $cotizaciones respuestas de los proveedores
 */
class SolicitudCotizacion extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.SolicitudCotizacion';
    }

    public function rules(): array
    {
        return [
            [['nombre', 'nota'], 'trim'],
            [['nombre'], 'required', 'message' => 'Escriba el nombre de la solicitud (el rubro al que se envía).'],
            [['nombre'], 'string', 'max' => 200],
            [['nota'], 'string', 'max' => 500],
            [['fechaEnvio'], 'date', 'format' => 'php:Y-m-d', 'message' => 'La fecha de envío no es válida.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'numero' => 'N.°',
            'nombre' => 'Rubro',
            'nota' => 'Nota para el proveedor',
            'fecha' => 'Fecha de creación',
            'fechaEnvio' => 'Fecha de envío',
        ];
    }

    public function getProyecto(): ActiveQuery
    {
        return $this->hasOne(Proyecto::class, ['id' => 'idProyecto']);
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(SolicitudCotizacionItem::class, ['idSolicitud' => 'id'])->orderBy(['id' => SORT_ASC]);
    }

    public function getCotizaciones(): ActiveQuery
    {
        return $this->hasMany(Cotizacion::class, ['idSolicitudCotizacion' => 'id'])->orderBy(['id' => SORT_ASC]);
    }

    /**
     * "SC-02": numero de la solicitud dentro del proyecto.
     */
    public function codigoCorto(): string
    {
        return 'SC-' . str_pad((string) $this->numero, 2, '0', STR_PAD_LEFT);
    }

    /**
     * "RD-PI-PT-01 / SC-02": identifica la solicitud en el documento impreso.
     */
    public function codigo(): string
    {
        return ($this->proyecto->codigo ?: '#' . $this->idProyecto) . ' / ' . $this->codigoCorto();
    }

    /**
     * La fecha que sale en el documento (dd/mm/aaaa): la de envio o, si no
     * se puso, la de hoy (la del dia en que se imprime).
     */
    public function fechaDelDocumento(): string
    {
        return $this->fechaEnvioFormateada() ?: Yii::$app->formatter->asDate('now', 'php:d/m/Y');
    }

    /**
     * fechaEnvio para mostrar (dd/mm/aaaa); vacio si no tiene.
     */
    public function fechaEnvioFormateada(): string
    {
        if (empty($this->fechaEnvio)) {
            return '';
        }

        $fecha = DateTime::createFromFormat('Y-m-d', (string) $this->fechaEnvio);

        return $fecha !== false ? $fecha->format('d/m/Y') : (string) $this->fechaEnvio;
    }

    /**
     * El campo es type="date" (envia aaaa-mm-dd; vacio = ''). Si llega
     * dd/mm/aaaa (un navegador sin calendario) tambien se acepta. Va antes
     * del validador 'date', igual que en Presupuesto.
     */
    public function beforeValidate(): bool
    {
        // '' = sin fecha de envio; SQL Server no convierte '' a date.
        if ($this->fechaEnvio === '') {
            $this->fechaEnvio = null;
        }

        $this->fechaEnvio = Presupuesto::convertirFechaFormularioABd($this->fechaEnvio);

        return parent::beforeValidate();
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->nota === '') {
            $this->nota = null;
        }

        return true;
    }
}
