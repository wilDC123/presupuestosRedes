<?php

declare(strict_types=1);

namespace backend\models;

use DateTimeImmutable;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.EspecificacionEmision": el documento con codigo
 * RD-PI-ET que se firma, se sella y se entrega al proveedor.
 *
 * REGLA DE NEGOCIO: una emision NUNCA cambia. Un error (facultad equivocada,
 * cantidad, especificacion vencida) se corrige REEMITIENDO: otra fila con el
 * MISMO codigo y la version siguiente (RD-PI-ET-015 v01 -> v02, lineamiento
 * RD-GD-LI-001, 4.2), y la anterior queda "reemplazada". Por eso guarda COPIAS de todo lo que
 * imprime (titulo, cantidad, dependencia, textos fijos...), igual que el
 * precio congelado del presupuesto. El contenido tecnico no se copia: apunta a
 * una version que ya no se puede modificar.
 *
 * Las reglas para emitir, reemitir y anular viven en
 * EspecificacionEmisionService.
 *
 * @property int $id
 * @property string $serie SERIE_ET (las de ahora: RD-PI-ET-001/26) o SERIE_NET (las de antes del 2026-10-22: NET:880/26).
 * @property int $numeroNet Correlativo de su serie; en ET vuelve a 1 cada año (2026-10-29), en
 *           NET no se reiniciaba. Las versiones de un mismo documento repiten el numero.
 * @property string $proceso Codigo del proceso del proyecto al emitirla (PI, GR...), CONGELADO:
 *           cambiar despues el proceso del proyecto no cambia el codigo. Las de antes, PI.
 * @property int $versionDocumento Version del DOCUMENTO (1, 2...): sube al reemitir. No confundir
 *           con getVersion(), la version de la FICHA de la libreria que imprime.
 * @property string|null $codigoAnterior El NET que tenia antes de pasar a RD-PI-ET (migracion
 *           m261022_144239): el documento ya entregado lo lleva impreso. NULL en las emitidas como ET.
 * @property int $anio Dos digitos del año de emision (26), parte del codigo (/26). Una reemision
 *           conserva el del documento aunque se haga al año siguiente.
 * @property int $idVersion Version de la ficha (EspecificacionVersion).
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
 * @property string|null $firma1Prefijo Control de elaboracion (copia de la configuracion al emitir):
 * @property string|null $firma1Nombre  firma 1 = elaborado por, firma 2 = aprobado por. Sin nombre
 * @property string|null $firma1Cargo   (las emitidas antes del 2026-10-22) sale en blanco.
 * @property string|null $firma2Prefijo
 * @property string|null $firma2Nombre
 * @property string|null $firma2Cargo
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
    use FirmasDeControl;

    public const ESTADO_VIGENTE = 'vigente';
    public const ESTADO_REEMPLAZADA = 'reemplazada';
    public const ESTADO_ANULADA = 'anulada';

    /**
     * Filtro "Vencidas" del registro. No es un estado guardado: es una
     * vigente cuya fecha + vigencia ya paso (ver estaVencida()). Lo usan el
     * desplegable del controlador y EspecificacionEmisionService::registro().
     */
    public const FILTRO_VENCIDA = 'vencida';

    /**
     * Serie del codigo. Desde el 2026-10-22 se emite con el codigo del
     * lineamiento de la DTIC RD-GD-LI-001 (RD-PI-ET-001/26, correlativo nuevo
     * desde 1, por año). Las emitidas antes son documentos ya firmados y entregados:
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
        return array_merge([
            [['numeroNet', 'anio', 'idVersion', 'idProyecto', 'titulo', 'cantidad', 'fechaEmision', 'vigenciaDias'], 'required'],
            [['numeroNet', 'anio', 'idVersion', 'idProyecto', 'idProyectoItem', 'validezOfertaDias', 'tiempoEntregaDias', 'vigenciaDias', 'idReemplazadaPor'], 'integer'],
            [['versionDocumento'], 'default', 'value' => 1],
            [['versionDocumento'], 'integer', 'min' => 1],
            [['cantidad'], 'number'],
            [['fechaEmision'], 'date', 'format' => 'php:Y-m-d'],
            [['titulo', 'lugarEntrega', 'motivo'], 'string', 'max' => 300],
            [['unidad'], 'string', 'max' => 20],
            [['dependencia'], 'string', 'max' => 200],
            [['elaboradoPor'], 'string', 'max' => 150],
            [['textoCabecera', 'textoNotaFinal', 'garantia'], 'string', 'max' => 500],
            [['textoInstrucciones'], 'string', 'max' => 1000],
            [['codigoAnterior'], 'string', 'max' => 20],
            [['proceso'], 'default', 'value' => CorrelativoDocumento::PROCESO],
            [['proceso'], 'match', 'pattern' => '/^[A-Z]{2}$/'],
            [['serie'], 'default', 'value' => self::SERIE_ET],
            [['serie'], 'in', 'range' => [self::SERIE_ET, self::SERIE_NET]],
            [['estado'], 'default', 'value' => self::ESTADO_VIGENTE],
            [['estado'], 'in', 'range' => [self::ESTADO_VIGENTE, self::ESTADO_REEMPLAZADA, self::ESTADO_ANULADA]],
        ], $this->reglasFirmas());
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'serie' => 'Serie',
            'numeroNet' => 'N.º',
            'versionDocumento' => 'Versión del documento',
            'codigoAnterior' => 'Código anterior',
            'anio' => 'Año',
            'proceso' => 'Proceso',
            'idVersion' => 'Versión de la ficha',
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
        ] + self::etiquetasFirmas();
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
     * Como se imprime y se cita: "RD-PI-ET-001/26" (ver CorrelativoDocumento::codigo()) o,
     * si es de las de antes, "NET:880/26" (tres digitos como minimo, igual
     * que el banco de DTIC: "NET:013/23"). Sin la version: es el mismo en
     * todas las versiones del documento (la carta, la solicitud y el
     * presupuesto citan la vigente). Para distinguir una version, codigoConVersion().
     */
    public function codigo(): string
    {
        if ($this->serie === self::SERIE_NET) {
            return sprintf('NET:%03d/%02d', $this->numeroNet, $this->anio);
        }

        return CorrelativoDocumento::codigo(
            CorrelativoDocumento::TIPO_ESPECIFICACION,
            (int) $this->numeroNet,
            $this->proceso ?: CorrelativoDocumento::PROCESO,
            (int) $this->anio
        );
    }

    /**
     * "02": como sale en la casilla "Versión" de la cabecera y en el control
     * de elaboracion del formulario.
     */
    public function versionTexto(): string
    {
        return sprintf('%02d', (int) ($this->versionDocumento ?? 1));
    }

    /**
     * "RD-PI-ET-015/26 v02": para las pantallas y el registro, donde se ven
     * juntas las versiones de un mismo documento.
     */
    public function codigoConVersion(): string
    {
        return $this->codigo() . ' v' . $this->versionTexto();
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
