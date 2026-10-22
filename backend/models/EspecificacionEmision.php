<?php

declare(strict_types=1);

namespace backend\models;

use DateTimeImmutable;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.EspecificacionEmision": el documento con codigo
 * NET que se firma, se sella y se entrega al proveedor.
 *
 * REGLA DE NEGOCIO: una emision NUNCA cambia. Un error (facultad equivocada,
 * cantidad, especificacion vencida) se corrige emitiendo otra con NET nuevo,
 * y la anterior queda "reemplazada". Por eso guarda COPIAS de todo lo que
 * imprime (titulo, cantidad, dependencia, textos fijos...), igual que el
 * precio congelado del presupuesto. El contenido tecnico no se copia: apunta a
 * una version que ya no se puede modificar.
 *
 * Las reglas para emitir, reemitir y anular viven en
 * EspecificacionEmisionService.
 *
 * @property int $id
 * @property string $serie SERIE_ET (las de ahora: RD-PI-ET-001) o SERIE_NET (las de antes del 2026-10-22: NET:880/26).
 * @property int $numeroNet Correlativo de su serie; no se reinicia por año.
 * @property string|null $codigoAnterior El NET que tenia antes de pasar a RD-PI-ET (migracion
 *           m261022_144239): el documento ya entregado lo lleva impreso. NULL en las emitidas como ET.
 * @property int $anio Dos digitos del año de emision (26). Solo forma parte del codigo en la serie NET.
 * @property int $idVersion
 * @property int $idProyecto
 * @property int|null $idProyectoItem NULL si el item se borro despues de emitir.
 * @property string $titulo
 * @property string $cantidad
 * @property string|null $unidad
 * @property string|null $dependencia
 * @property int|null $validezOfertaDias
 * @property int|null $tiempoEntregaDias
 * @property string|null $lugarEntrega
 * @property string|null $elaboradoPor
 * @property string|null $textoCabecera
 * @property string|null $textoInstrucciones
 * @property string|null $textoNotaFinal
 * @property string|null $garantia La que se imprimio: la de la version o, si no tenia, la de la configuracion.
 * @property string $fechaEmision aaaa-mm-dd
 * @property int $vigenciaDias
 * @property string $estado Ver las constantes ESTADO_*.
 * @property int|null $idReemplazadaPor
 * @property string|null $motivo Por que se reemplazo o anulo.
 * @property string $fechaRegistro
 *
 * @property-read EspecificacionVersion $version
 * @property-read Proyecto $proyecto
 * @property-read ProyectoItem|null $proyectoItem
 * @property-read EspecificacionEmision|null $reemplazadaPor
 */
class EspecificacionEmision extends ActiveRecord
{
    public const ESTADO_VIGENTE = 'vigente';
    public const ESTADO_REEMPLAZADA = 'reemplazada';
    public const ESTADO_ANULADA = 'anulada';

    /**
     * Serie del codigo. Desde el 2026-10-22 se emite con el codigo del
     * lineamiento de la DTIC RD-GD-LI-001 (RD-PI-ET-001, correlativo nuevo
     * desde 1). Las emitidas antes son documentos ya firmados y entregados:
     * conservan su codigo NET de siempre.
     */
    public const SERIE_ET = 'ET';
    public const SERIE_NET = 'NET';

    public static function tableName(): string
    {
        return 'redes.EspecificacionEmision';
    }

    public function rules(): array
    {
        return [
            [['numeroNet', 'anio', 'idVersion', 'idProyecto', 'titulo', 'cantidad', 'fechaEmision', 'vigenciaDias'], 'required'],
            [['numeroNet', 'anio', 'idVersion', 'idProyecto', 'idProyectoItem', 'validezOfertaDias', 'tiempoEntregaDias', 'vigenciaDias', 'idReemplazadaPor'], 'integer'],
            [['cantidad'], 'number'],
            [['fechaEmision'], 'date', 'format' => 'php:Y-m-d'],
            [['titulo', 'lugarEntrega', 'motivo'], 'string', 'max' => 300],
            [['unidad'], 'string', 'max' => 20],
            [['dependencia'], 'string', 'max' => 200],
            [['elaboradoPor'], 'string', 'max' => 150],
            [['textoCabecera', 'textoNotaFinal', 'garantia'], 'string', 'max' => 500],
            [['textoInstrucciones'], 'string', 'max' => 1000],
            [['codigoAnterior'], 'string', 'max' => 20],
            [['serie'], 'default', 'value' => self::SERIE_ET],
            [['serie'], 'in', 'range' => [self::SERIE_ET, self::SERIE_NET]],
            [['estado'], 'default', 'value' => self::ESTADO_VIGENTE],
            [['estado'], 'in', 'range' => [self::ESTADO_VIGENTE, self::ESTADO_REEMPLAZADA, self::ESTADO_ANULADA]],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'serie' => 'Serie',
            'numeroNet' => 'N.º',
            'codigoAnterior' => 'Código anterior',
            'anio' => 'Año',
            'idVersion' => 'Versión',
            'idProyecto' => 'Proyecto',
            'idProyectoItem' => 'Ítem',
            'titulo' => 'Título',
            'cantidad' => 'Cantidad',
            'unidad' => 'Unidad',
            'dependencia' => 'Dependencia',
            'validezOfertaDias' => 'Validez de la oferta (días)',
            'tiempoEntregaDias' => 'Tiempo de entrega (días)',
            'lugarEntrega' => 'Lugar de entrega',
            'elaboradoPor' => 'Elaborado por',
            'fechaEmision' => 'Fecha de emisión',
            'vigenciaDias' => 'Vigencia (días)',
            'estado' => 'Estado',
            'idReemplazadaPor' => 'Reemplazada por',
            'motivo' => 'Motivo',
            'garantia' => 'Garantía',
        ];
    }

    public function getVersion(): ActiveQuery
    {
        return $this->hasOne(EspecificacionVersion::class, ['id' => 'idVersion']);
    }

    public function getProyecto(): ActiveQuery
    {
        return $this->hasOne(Proyecto::class, ['id' => 'idProyecto']);
    }

    public function getProyectoItem(): ActiveQuery
    {
        return $this->hasOne(ProyectoItem::class, ['id' => 'idProyectoItem']);
    }

    public function getReemplazadaPor(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'idReemplazadaPor']);
    }

    /**
     * Como se imprime y se cita: "RD-PI-ET-001" (ver CorrelativoDocumento::codigo()) o,
     * si es de las de antes, "NET:880/26" (tres digitos como minimo, igual
     * que el banco de DTIC: "NET:013/23").
     */
    public function codigo(): string
    {
        if ($this->serie === self::SERIE_NET) {
            return sprintf('NET:%03d/%02d', $this->numeroNet, $this->anio);
        }

        return CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_ESPECIFICACION, (int) $this->numeroNet);
    }

    public function estadoLabel(): string
    {
        return match ($this->estado) {
            self::ESTADO_VIGENTE => $this->estaVencida() ? 'Vencida' : 'Vigente',
            self::ESTADO_REEMPLAZADA => 'Reemplazada',
            self::ESTADO_ANULADA => 'Anulada',
            default => $this->estado,
        };
    }

    /**
     * Clase del badge, con los colores de los estados del sistema.
     */
    public function estadoBadgeClass(): string
    {
        return 'badge ' . match ($this->estado) {
            self::ESTADO_VIGENTE => $this->estaVencida() ? 'b-observado' : 'b-activo',
            self::ESTADO_REEMPLAZADA => 'b-inactivo',
            default => 'b-cancelado',
        };
    }

    /**
     * Ultimo dia en que el formulario es valido: fecha de emision + vigencia.
     */
    public function fechaVencimiento(): DateTimeImmutable
    {
        return (new DateTimeImmutable($this->fechaEmision))->modify("+{$this->vigenciaDias} days");
    }

    /**
     * "Vencida" no es un estado guardado: se calcula (igual que la validez de
     * una cotizacion). Solo una emision vigente puede estar vencida; una
     * reemplazada o anulada ya no se usa.
     */
    public function estaVencida(?DateTimeImmutable $hoy = null): bool
    {
        $hoy ??= new DateTimeImmutable('today');

        return $this->estado === self::ESTADO_VIGENTE && $this->fechaVencimiento() < $hoy;
    }
}
