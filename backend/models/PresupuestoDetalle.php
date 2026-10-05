<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.PresupuestoDetalle".
 *
 * REGLA DE NEGOCIO CRITICA: precioUnitario se copia UNA SOLA VEZ desde
 * CotizacionDetalle en el momento de crear la fila (ver
 * PresupuestoService::agregarLinea() / congelarPrecio()). Ni este modelo ni
 * el servicio tienen, ni deben tener nunca, ningun codigo que
 * vuelva a sincronizar precioUnitario con CotizacionDetalle despues de la
 * creacion inicial -- ni un afterFind, ni un job, ni un boton "actualizar
 * precio". Si el precio de la cotizacion de origen cambia, esta fila ya
 * guardada se queda con el precio viejo a proposito.
 *
 * MISMA REGLA para partida: se copia UNA SOLA VEZ desde SubcatalogoItem al
 * crear la fila (PresupuestoService::congelarPartida()) y nunca se vuelve a
 * sincronizar. La parte financiera consolida por la partida que la linea
 * tenia cuando se elaboro el presupuesto.
 *
 * @property int $id
 * @property int $idPresupuesto
 * @property int $idSubcatalogo
 * @property int|null $idCotizacionDetalle
 * @property string $cantidad
 * @property string|null $unidadMedida
 * @property string $precioUnitario
 * @property string $precioTotal Columna calculada por SQL Server, solo lectura.
 * @property string|null $partida Partida presupuestaria congelada (5 digitos).
 * @property string|null $observacion Texto libre de la linea (medidas, "segun especificacion", etc.).
 * @property int|null $idProyectoItem Item de la lista del proyecto del que nacio la linea.
 *
 * @property-read ProyectoItem|null $proyectoItem
 * @property-read Presupuesto $presupuesto
 * @property-read SubcatalogoItem $subcatalogoItem
 * @property-read CotizacionDetalle|null $cotizacionDetalle
 */
class PresupuestoDetalle extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.PresupuestoDetalle';
    }

    public function rules(): array
    {
        return [
            [['idPresupuesto', 'idSubcatalogo', 'cantidad'], 'required'],
            // idProyectoItem: que sea de la lista de ESTE proyecto y que no
            // este ya presupuestado lo verifica PresupuestoService::agregarLinea().
            [['idPresupuesto', 'idSubcatalogo', 'idCotizacionDetalle', 'idProyectoItem'], 'integer'],
            [['cantidad'], 'number', 'min' => 0],
            [['unidadMedida'], 'string', 'max' => 20],
            // precioUnitario solo es obligatorio cuando NO se eligio una
            // cotizacion (precio manual); si se eligio una,
            // PresupuestoService::congelarPrecio() lo llena solo a partir de
            // CotizacionDetalle. 'whenClient' es
            // necesario ademas de 'when': sin el, Yii igual genera la
            // validacion JS de "required" en el navegador (que no conoce el
            // callback PHP) y bloquea el envio del formulario aunque el
            // servidor nunca llegaria a exigirlo. El selector jQuery apunta
            // al id que genera Html::getInputId() para
            // PresupuestoDetalle[idCotizacionDetalle], el dropDownList con
            // prompt en presupuesto/detalle.php.
            [
                ['precioUnitario'],
                'required',
                'when' => static fn (self $model) => empty($model->idCotizacionDetalle),
                'whenClient' => "function (attribute, value) {
                    return $('#presupuestodetalle-idcotizaciondetalle').val() === '';
                }",
            ],
            [['precioUnitario'], 'number', 'min' => 0],
            [['observacion'], 'trim'],
            [['observacion'], 'string', 'max' => 300],
            // partida NO tiene regla a proposito: en Yii2 un atributo sin
            // reglas no es "seguro" y load() lo ignora, asi que nadie puede
            // enviarlo desde el formulario. Solo lo escribe
            // PresupuestoService::congelarPartida().
            [['idPresupuesto'], 'exist', 'skipOnError' => true, 'targetClass' => Presupuesto::class, 'targetAttribute' => ['idPresupuesto' => 'id']],
            [['idSubcatalogo'], 'exist', 'skipOnError' => true, 'targetClass' => SubcatalogoItem::class, 'targetAttribute' => ['idSubcatalogo' => 'id']],
            [['idCotizacionDetalle'], 'exist', 'skipOnError' => true, 'targetClass' => CotizacionDetalle::class, 'targetAttribute' => ['idCotizacionDetalle' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idPresupuesto' => 'Presupuesto',
            'idSubcatalogo' => 'Ítem de subcatálogo',
            'idCotizacionDetalle' => 'Cotización de origen',
            'cantidad' => 'Cantidad',
            'unidadMedida' => 'Unidad de medida',
            'precioUnitario' => 'Precio unitario',
            'precioTotal' => 'Precio total',
            'partida' => 'Partida',
            'observacion' => 'Observación',
            'idProyectoItem' => 'Ítem de la lista',
        ];
    }

    public function getProyectoItem(): ActiveQuery
    {
        return $this->hasOne(ProyectoItem::class, ['id' => 'idProyectoItem']);
    }

    public function getPresupuesto(): ActiveQuery
    {
        return $this->hasOne(Presupuesto::class, ['id' => 'idPresupuesto']);
    }

    public function getSubcatalogoItem(): ActiveQuery
    {
        return $this->hasOne(SubcatalogoItem::class, ['id' => 'idSubcatalogo']);
    }

    public function getCotizacionDetalle(): ActiveQuery
    {
        return $this->hasOne(CotizacionDetalle::class, ['id' => 'idCotizacionDetalle']);
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // idCotizacionDetalle permite NULL, pero el dropDownList con prompt
        // "(ninguna...)" envia '' cuando se elige precio manual. SQL Server
        // no puede convertir '' a int, asi que se normaliza a null antes de
        // seguir (mismo riesgo que idSigma en SubcatalogoItem).
        if ($this->idCotizacionDetalle === '') {
            $this->idCotizacionDetalle = null;
        }

        // Observacion vacia se guarda como NULL ("sin observacion"), no como ''.
        if ($this->observacion === '') {
            $this->observacion = null;
        }

        return true;
    }
}
