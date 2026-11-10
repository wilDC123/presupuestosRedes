<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Proyecto".
 *
 * REGLA DE NEGOCIO: una ampliacion (Fase 2, 3...) NO es una version del mismo
 * proyecto: es un proyecto NUEVO, con su propio codigo, enlazado al de la
 * fase anterior por idProyectoAnterior. Un proyecto cuyo presupuesto fue
 * aprobado nunca se modifica.
 *
 * @property int $id
 * @property string|null $codigo Codigo de la FICHA TECNICA (RD-PI-PT-NNN): solo la fase 1 lo tiene (una ficha por
 *           proyecto, desde el 2026-11-06); NULL en las fases siguientes. Ver codigoFicha().
 * @property string|null $codigoLista Codigo de su lista de materiales (RD-PI-LM-NNN, revision del 2026-11-05),
 *           tambien lo pone ProyectoService al crear; el proceso es el del proyecto.
 * @property string $nombre
 * @property string|null $descripcion
 * @property int|null $idTipoProyecto FK a redes.TipoProyecto.
 * @property int|null $idProceso FK a redes.Proceso: que proceso lleva adelante el proyecto (PI, DS...).
 *                               Obligatorio al guardar desde el formulario.
 * @property string|null $unidadDependencia Unidad BENEFICIARIA, a donde va el proyecto (texto libre).
 *                                         Es la que sale en los documentos ("Dependencia").
 * @property string|null $unidadSolicitante Quien pide, en texto libre tal como dice la nota. Puede
 *                                         ser otra (el Rector pide para el Canal Universitario).
 * @property string|null $lugarEntrega Donde se entregan los bienes (texto libre, 2026-11-03). Lo copian los
 *                                    formularios de especificacion al emitirse; vacio = el de la
 *                                    configuracion del formulario. Ver lugarEntregaSugerido().
 * @property string|null $codigoFacultad Ubicacion ANTERIOR al 2026-10-28 (ya no se escribe).
 * @property string|null $facultad       Idem: los proyectos nuevos usan las unidades
 * @property string|null $codigoEdificio y las areas de intervencion.
 * @property string|null $edificio
 * @property int|null $idProyectoAnterior
 * @property int $numeroFase
 * @property int|null $numeroProyecto Numero del proyecto (revision del 2026-11-05): lo comparten TODAS sus
 *           fases (cada fila de esta tabla es una fase). Lo pone ProyectoService al crear; ver numeroTexto().
 * @property int|null $idFaseOrigen ORDEN DE CAMBIO (parte 6, 2026-11-09): la fase a la que pertenece la orden.
 *           NULL = la fila es una fase. Ver esOrdenCambio().
 * @property int|null $numeroOrdenCambio 1, 2... dentro de su fase (NULL en una fase).
 * @property string|null $descripcionCambio Orden de cambio: descripcion del cambio solicitado (FR-006).
 * @property string|null $motivoCambio Orden de cambio: motivo.
 * @property string|null $tipoCambio Orden de cambio: una de las claves de tiposCambio() (FR-006, 7.1.1).
 * @property int|null $idPlantillaOrigen Plantilla de la que partio la lista (solo informativo).
 * @property bool $listaIniciada true si el paso "Plantilla" ya se resolvio (se uso una o se omitio).
 * @property string $estadoLista Estado de la lista de items: ver las constantes LISTA_*.
 * @property string $fechaCreacion
 *
 * @property-read TipoProyecto|null $tipoProyecto
 * @property-read Proceso|null $proceso
 * @property-read ProyectoAreaIntervencion[] $areasIntervencion
 * @property-read ProyectoBloque[] $bloques Ubicacion anterior al 2026-10-28.
 * @property-read ProyectoParticipante[] $participantes Equipo del proyecto, en orden.
 * @property-read Proyecto|null $proyectoAnterior
 * @property-read Proyecto[] $fasesSiguientes
 * @property-read Proyecto[] $fases Todas las fases del proyecto (mismo numeroProyecto), en orden; sin las ordenes de cambio.
 * @property-read Proyecto|null $faseOrigen La fase de una orden de cambio.
 * @property-read Proyecto[] $ordenesCambio Las ordenes de cambio de esta fase, en orden.
 * @property-read Presupuesto|null $presupuesto
 * @property-read Plantilla|null $plantillaOrigen
 * @property-read ProyectoItem[] $items
 * @property-read ListaMaterialesAprobacion[] $aprobacionesLista Versiones aprobadas de la lista, en orden.
 * @property-read ProyectoCondicionPrevia[] $condicionesPrevias Trabajos complementarios.
 * @property-read ProyectoMaterialDtic[] $materialesDtic Otros materiales que proveera la DTIC.
 */
class Proyecto extends ActiveRecord
{
    /**
     * Estados de la lista de items. Solo los cambia ListaItemsService (y
     * OrdenCambioService al reabrirla): estadoLista no tiene regla, asi que
     * no se puede enviar desde un formulario.
     */
    public const LISTA_EDICION = 'edicion';
    public const LISTA_EN_COTIZACION = 'en_cotizacion';
    public const LISTA_CERRADA = 'cerrada';

    /**
     * Atributos virtuales: no son columnas de redes.Proyecto. Reciben del
     * formulario las areas de intervencion y el equipo del proyecto;
     * ProyectoService los guarda en redes.ProyectoAreaIntervencion y
     * redes.ProyectoParticipante.
     *
     * Cada area llega como [idEdificio, idBloqueArea, numeroNivel, idEspacio]
     * (los tres ultimos pueden venir vacios). Los nombres NO vienen del
     * formulario: ProyectoService los pide a la fuente de infraestructura.
     * Salvo el area escrita a mano, que llega como [manual = 1, edificio,
     * bloque, nivel, ambiente] (no esta en UsfxGEO, no tiene id).
     *
     * @var array<int, array<string, string>>|string
     */
    public $areas = [];

    /**
     * Equipo del proyecto: una fila por persona, [clave => ['nombre' => ...,
     * 'idRol' => ...]]. La clave es la de la fila en el formulario (no tiene
     * por que ser 0, 1, 2...): la usa $equipoResponsable.
     *
     * @var array<int|string, array{nombre?: string, idRol?: string}>|string
     */
    public $equipo = [];

    /**
     * Clave (de $equipo) de la fila marcada como responsable del proyecto.
     *
     * @var int|string|null
     */
    public $equipoResponsable;

    public static function tableName(): string
    {
        return 'redes.Proyecto';
    }

    /**
     * codigo, facultad, edificio y numeroFase NO tienen regla a proposito:
     * un atributo sin reglas no es "seguro" y load() lo ignora, asi que no
     * se pueden enviar desde el formulario. Los calcula ProyectoService.
     */
    /**
     * Escenario del formulario de una ORDEN DE CAMBIO: solo descripcion,
     * motivo y tipo llegan del formulario (lo demas lo copia ProyectoService
     * de la fase). Ver scenarios().
     */
    public const SCENARIO_ORDEN_CAMBIO = 'ordenCambio';

    /**
     * Tipos de orden de cambio del formato RD-GD-FR-006 (7.1.1).
     *
     * @return array<string, string>
     */
    public static function tiposCambio(): array
    {
        return [
            'solicitud' => 'Nuevas solicitudes de los usuarios',
            'omision' => 'Errores u omisiones en la planificación y propuestas técnicas',
            'presupuesto' => 'Presupuestos imprecisos',
            'alcance' => 'Cambios en el alcance del proyecto',
            'costos' => 'Costos de recursos',
            'imprevisto' => 'Eventos no planificados',
        ];
    }

    /**
     * Las reglas sin 'on' valen en todos los escenarios: por eso el de la
     * orden de cambio se declara a mano, con SOLO sus tres campos (asi el
     * formulario de la orden no puede cambiar el nombre ni nada del proyecto).
     */
    public function scenarios(): array
    {
        $escenarios = parent::scenarios();
        $escenarios[self::SCENARIO_ORDEN_CAMBIO] = ['descripcionCambio', 'motivoCambio', 'tipoCambio'];

        return $escenarios;
    }

    public function rules(): array
    {
        return [
            [['descripcionCambio', 'motivoCambio'], 'trim', 'on' => self::SCENARIO_ORDEN_CAMBIO],
            [['descripcionCambio', 'motivoCambio', 'tipoCambio'], 'required', 'on' => self::SCENARIO_ORDEN_CAMBIO],
            [['descripcionCambio', 'motivoCambio'], 'string', 'max' => 1000, 'on' => self::SCENARIO_ORDEN_CAMBIO],
            [['tipoCambio'], 'in', 'range' => array_keys(self::tiposCambio()), 'on' => self::SCENARIO_ORDEN_CAMBIO],
            [['nombre'], 'required'],
            [['nombre'], 'string', 'max' => 300],
            [['descripcion'], 'string', 'max' => 1000],
            [['idTipoProyecto'], 'integer'],
            TipoProyecto::reglaElegible(),
            [['idProceso'], 'required'],
            [['idProceso'], 'integer'],
            Proceso::reglaElegible(),
            [['unidadDependencia', 'unidadSolicitante'], 'trim'],
            [['unidadDependencia', 'unidadSolicitante'], 'required'],
            [['unidadDependencia', 'unidadSolicitante'], 'string', 'max' => 250],
            [['lugarEntrega'], 'trim'],
            [['lugarEntrega'], 'default', 'value' => null],
            [['lugarEntrega'], 'string', 'max' => 300],
            [['idProyectoAnterior'], 'integer'],
            [['idProyectoAnterior'], 'exist', 'skipOnError' => true, 'targetClass' => self::class, 'targetAttribute' => ['idProyectoAnterior' => 'id']],
            // 'safe': las areas las revisa ProyectoService una por una contra la
            // fuente de infraestructura (que existan y esten vigentes).
            [['areas'], 'safe'],
            // 'safe': el equipo lo revisa ProyectoService fila por fila
            // (persona y rol de las listas, un responsable, sin repetidos).
            [['equipo', 'equipoResponsable'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'codigo' => 'Código de proyecto',
            'codigoLista' => 'Código de la lista de materiales',
            'nombre' => 'Nombre del proyecto',
            'descripcion' => 'Descripción',
            'idTipoProyecto' => 'Tipo de proyecto',
            'idProceso' => 'Proceso',
            'unidadDependencia' => 'Unidad / dependencia',
            'unidadSolicitante' => 'Unidad solicitante',
            'lugarEntrega' => 'Lugar de entrega por defecto',
            'descripcionCambio' => 'Descripción del cambio',
            'motivoCambio' => 'Motivo',
            'tipoCambio' => 'Tipo de cambio',
            'areas' => 'Área de intervención',
            'codigoFacultad' => 'Facultad',
            'facultad' => 'Facultad',
            'codigoEdificio' => 'Edificio',
            'edificio' => 'Edificio',
            'codigosBloque' => 'Bloques',
            'equipo' => 'Equipo del proyecto',
            'idProyectoAnterior' => 'Proyecto de fase anterior (opcional)',
            'numeroFase' => 'Fase',
            'numeroProyecto' => 'N.° de proyecto',
            'fechaCreacion' => 'Fecha de creación',
        ];
    }

    public function getTipoProyecto(): ActiveQuery
    {
        return $this->hasOne(TipoProyecto::class, ['id' => 'idTipoProyecto']);
    }

    public function getProceso(): ActiveQuery
    {
        return $this->hasOne(Proceso::class, ['id' => 'idProceso']);
    }

    public function getAreasIntervencion(): ActiveQuery
    {
        return $this->hasMany(ProyectoAreaIntervencion::class, ['idProyecto' => 'id'])->orderBy(['orden' => SORT_ASC, 'id' => SORT_ASC]);
    }

    /**
     * Ubicacion anterior al 2026-10-28 (facultad / edificio / bloques del
     * JSON). Solo se lee, para los proyectos registrados antes.
     */
    public function getBloques(): ActiveQuery
    {
        return $this->hasMany(ProyectoBloque::class, ['idProyecto' => 'id'])->orderBy(['nombre' => SORT_ASC]);
    }

    public function getParticipantes(): ActiveQuery
    {
        return $this->hasMany(ProyectoParticipante::class, ['idProyecto' => 'id'])->orderBy(['orden' => SORT_ASC, 'id' => SORT_ASC]);
    }

    /**
     * La persona responsable del proyecto (la marcada en el equipo), o null
     * si el proyecto todavia no tiene equipo (los registrados antes del
     * 2026-10-29).
     */
    public function responsable(): ?ProyectoParticipante
    {
        foreach ($this->participantes as $participante) {
            if ($participante->esResponsable) {
                return $participante;
            }
        }

        return null;
    }

    public function getProyectoAnterior(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'idProyectoAnterior']);
    }

    /** La fase de una orden de cambio (null en una fase). */
    public function getFaseOrigen(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'idFaseOrigen']);
    }

    /** Las ordenes de cambio de esta fase, la 1 primero. */
    public function getOrdenesCambio(): ActiveQuery
    {
        return $this->hasMany(self::class, ['idFaseOrigen' => 'id'])->orderBy(['numeroOrdenCambio' => SORT_ASC]);
    }

    /**
     * true si la fila es una ORDEN DE CAMBIO (parte 6): un presupuesto
     * adicional de una fase aprobada, con su propia lista, ET, solicitudes,
     * presupuesto y carta. No es una fase: no cuenta en las fases, en la
     * version de la ficha tecnica ni en "Agregar fase".
     */
    public function esOrdenCambio(): bool
    {
        return $this->idFaseOrigen !== null;
    }

    /** "Orden de cambio N.° 1" (vacio en una fase). */
    public function textoOrdenCambio(): string
    {
        return $this->esOrdenCambio() ? 'Orden de cambio N.° ' . (int) $this->numeroOrdenCambio : '';
    }

    /** El texto del tipo de cambio ("Nuevas solicitudes de los usuarios"). */
    public function tipoCambioLabel(): string
    {
        return self::tiposCambio()[(string) $this->tipoCambio] ?? (string) $this->tipoCambio;
    }

    /**
     * Proyectos que declararon a este como su fase anterior.
     */
    public function getFasesSiguientes(): ActiveQuery
    {
        return $this->hasMany(self::class, ['idProyectoAnterior' => 'id']);
    }

    /**
     * hasOne porque cada proyecto tiene un solo presupuesto (indice unico
     * sobre redes.Presupuesto.idProyecto).
     */
    public function getPresupuesto(): ActiveQuery
    {
        return $this->hasOne(Presupuesto::class, ['idProyecto' => 'id']);
    }

    public function getPlantillaOrigen(): ActiveQuery
    {
        return $this->hasOne(Plantilla::class, ['id' => 'idPlantillaOrigen']);
    }

    /**
     * Lista de items del proyecto (sin precios).
     */
    public function getItems(): ActiveQuery
    {
        return $this->hasMany(ProyectoItem::class, ['idProyecto' => 'id'])->orderBy(['orden' => SORT_ASC, 'id' => SORT_ASC]);
    }

    /**
     * Trabajos complementarios (condiciones y requerimientos previos): lo que
     * deben hacer otras unidades (2026-10-29), en orden.
     */
    public function getCondicionesPrevias(): ActiveQuery
    {
        return $this->hasMany(ProyectoCondicionPrevia::class, ['idProyecto' => 'id'])->orderBy(['orden' => SORT_ASC, 'id' => SORT_ASC]);
    }

    /**
     * Otros materiales y equipamiento que proveera la DTIC (2026-10-29; antes
     * en el presupuesto), en orden.
     */
    public function getMaterialesDtic(): ActiveQuery
    {
        return $this->hasMany(ProyectoMaterialDtic::class, ['idProyecto' => 'id'])->orderBy(['orden' => SORT_ASC, 'id' => SORT_ASC]);
    }

    /**
     * Versiones aprobadas de la "Lista de materiales y condiciones previas"
     * (paso 8b, 2026-10-30), de la primera a la ultima.
     */
    public function getAprobacionesLista(): ActiveQuery
    {
        return $this->hasMany(ListaMaterialesAprobacion::class, ['idProyecto' => 'id'])->orderBy(['version' => SORT_ASC]);
    }

    /** La ultima version aprobada de la lista, o null si nunca se envio a cotizacion. */
    public function ultimaAprobacionLista(): ?ListaMaterialesAprobacion
    {
        $aprobaciones = $this->aprobacionesLista;

        return $aprobaciones === [] ? null : end($aprobaciones);
    }

    public function estadoListaLabel(): string
    {
        return [
            self::LISTA_EDICION => 'En edición',
            self::LISTA_EN_COTIZACION => 'En cotización',
            self::LISTA_CERRADA => 'Cerrada',
        ][$this->estadoLista] ?? (string) $this->estadoLista;
    }

    /**
     * Clases CSS de la etiqueta de estado (las mismas del presupuesto).
     */
    public function estadoListaBadgeClass(): string
    {
        return [
            self::LISTA_EDICION => 'badge b-proceso',
            self::LISTA_EN_COTIZACION => 'badge b-revision',
            self::LISTA_CERRADA => 'badge b-aprobado',
        ][$this->estadoLista] ?? 'badge b-inactivo';
    }

    /**
     * Area de intervencion en un solo texto, para el historial: una linea por
     * edificio (ProyectoAreaIntervencion::lineasPorEdificio()) separadas por
     * " | ". Un proyecto registrado antes del 2026-10-28 no tiene
     * areas: muestra su ubicacion anterior (edificio y bloques). '' si no hay
     * ninguna de las dos.
     */
    public function areaIntervencionTexto(): string
    {
        if ($this->areasIntervencion !== []) {
            return ProyectoAreaIntervencion::resumen($this->areasIntervencion);
        }

        return $this->ubicacionAnterior();
    }

    /**
     * Ubicacion de los proyectos anteriores al 2026-10-28: "Edificio / Bloque
     * A, Bloque B" (la facultad paso a ser la unidad solicitante).
     */
    public function ubicacionAnterior(): string
    {
        $bloques = implode(', ', array_map(static fn (ProyectoBloque $bloque) => $bloque->nombre, $this->bloques));

        return implode(' / ', array_filter([$this->edificio, $bloques]));
    }

    /**
     * "001 — Nombre" para listas desplegables, enlaces y la linea del
     * proyecto de los documentos (sin la fase: la agrega quien la necesita).
     * Antes del 2026-11-06, "RD-PI-PT-028 — Nombre": ese codigo es de la ficha.
     */
    public function etiqueta(): string
    {
        return ($this->numeroProyecto !== null ? $this->numeroTexto() . ' — ' : '') . $this->nombre;
    }

    /**
     * La UNICA linea del proyecto en los documentos (revision del Ing. de DTIC
     * del 2026-11-05, punto 6): "PROYECTO: 001 – INSTALACIÓN DE CÁMARAS DE
     * SEGURIDAD (FASE 2)". Numero, guion largo y el nombre en mayusculas
     * ("como en las notas"); "(FASE n)" solo si el proyecto tiene mas de una
     * fase (fase unica: no se pone). Sin negrilla (a confirmar con el Ing.:
     * la vista decide como la muestra). Antes cada documento armaba la suya.
     */
    public function lineaDocumento(): string
    {
        $linea = 'PROYECTO: ' . ($this->numeroProyecto !== null ? $this->numeroTexto() . ' – ' : '')
            . mb_strtoupper(trim((string) $this->nombre), 'UTF-8');

        $linea = $this->tieneVariasFases() ? $linea . ' (FASE ' . (int) $this->numeroFase . ')' : $linea;

        // Una orden de cambio: "PROYECTO: 001 – NOMBRE (FASE 1) – ORDEN DE CAMBIO N.° 1" (propuesta del 2026-11-09).
        return $this->esOrdenCambio() ? $linea . ' – ' . mb_strtoupper($this->textoOrdenCambio(), 'UTF-8') : $linea;
    }

    /**
     * El numero del proyecto como se escribe: "001" (3 cifras, decidido con
     * el desarrollador el 2026-11-06 mientras el Ing. de DTIC confirma el
     * formato; es el UNICO lugar que lo decide). '—' si no tiene.
     */
    public function numeroTexto(): string
    {
        return $this->numeroProyecto === null ? '—' : CorrelativoDocumento::numeroTexto((int) $this->numeroProyecto);
    }

    /**
     * Datos del PROYECTO, iguales en todas sus fases (revision del 2026-11-05,
     * punto 2: "primero lo que es del proyecto y no cambia por fase"). Se
     * escriben en la fase 1; una fase siguiente los copia y no los cambia
     * (ProyectoService). Lo demas (area, equipo, lugar de entrega por
     * defecto, carpeta economica) es de cada fase.
     */
    public const ATRIBUTOS_COMUNES = ['nombre', 'descripcion', 'idTipoProyecto', 'idProceso', 'unidadDependencia', 'unidadSolicitante'];

    /**
     * Todas las fases del proyecto (las filas con su mismo numero), de la 1 en
     * adelante; incluye esta si es una fase. Sin las ordenes de cambio (parte
     * 6): son filas con el mismo numero, pero no fases. Vacio si no tiene
     * numero (filas de prueba).
     */
    public function getFases(): ActiveQuery
    {
        return $this->hasMany(self::class, ['numeroProyecto' => 'numeroProyecto'])
            ->andOnCondition(['numeroOrdenCambio' => null])
            ->orderBy(['numeroFase' => SORT_ASC, 'id' => SORT_ASC]);
    }

    /**
     * Codigo de la FICHA TECNICA del proyecto (RD-PI-PT-NNN): UNA por proyecto,
     * la que se codifico con su fase 1 (parte 5d). Las fases siguientes no
     * tienen codigo propio (Proyecto.codigo NULL). null si no tiene.
     */
    public function codigoFicha(): ?string
    {
        if (((int) $this->numeroFase <= 1 && !$this->esOrdenCambio()) || $this->numeroProyecto === null) {
            return $this->codigo;
        }

        $codigo = self::find()->select('codigo')
            ->andWhere(['numeroProyecto' => $this->numeroProyecto])
            ->andWhere(['not', ['codigo' => null]])
            ->orderBy(['numeroFase' => SORT_ASC])
            ->scalar();

        return is_string($codigo) ? $codigo : $this->codigo;
    }

    /** true si ninguna fase del proyecto viene despues de esta (una orden de cambio no es una fase: false). */
    public function esUltimaFase(): bool
    {
        if ($this->esOrdenCambio()) {
            return false;
        }

        if ($this->numeroProyecto === null) {
            return !self::find()->andWhere(['idProyectoAnterior' => $this->id])->exists();
        }

        return !self::find()
            ->andWhere(['numeroProyecto' => $this->numeroProyecto])
            ->andWhere(['>', 'numeroFase', (int) $this->numeroFase])
            ->exists();
    }

    /** @var bool|null cache de tieneVariasFases() */
    private ?bool $variasFases = null;

    /**
     * true si el proyecto tiene mas de una fase (esta es la 2 o siguiente, u
     * otra fila tiene su mismo numero). Con fase unica no se muestra "Fase"
     * (revision del 2026-11-05, punto 3: "no poner Fase 1, porque da a
     * entender que hay fase 2").
     */
    public function tieneVariasFases(): bool
    {
        if ($this->variasFases === null) {
            $this->variasFases = (int) $this->numeroFase > 1
                || ($this->numeroProyecto !== null && self::find()
                    ->andWhere(['numeroProyecto' => $this->numeroProyecto, 'numeroOrdenCambio' => null])
                    ->andWhere(['<>', 'numeroFase', (int) $this->numeroFase])
                    ->exists());
        }

        return $this->variasFases;
    }

    /**
     * "Proyecto 001", o "Proyecto 001 · Fase 2" si tiene varias fases: el
     * rotulo corto de migas de pan, titulos y avisos.
     */
    public function identificador(): string
    {
        $texto = $this->numeroProyecto !== null ? 'Proyecto ' . $this->numeroTexto() : 'Proyecto #' . $this->id;

        $texto = $this->tieneVariasFases() ? $texto . ' · Fase ' . (int) $this->numeroFase : $texto;

        return $this->esOrdenCambio() ? $texto . ' · ' . $this->textoOrdenCambio() : $texto;
    }

    /**
     * Lugar de entrega que se sugiere para una unidad / dependencia: si es una
     * facultad, "Administración de la Facultad de …" (pedido del Ing. de DTIC,
     * 2026-10-26); si no (Rectorado, Canal Universitario, la DTIC), null: el
     * encargado lo escribe o queda el de la configuracion del formulario. Es
     * solo una sugerencia: el campo acepta cualquier texto. La misma regla
     * esta en web/js/proyecto-form.js y en la migracion m261104_020312.
     */
    public static function lugarEntregaSugerido(?string $unidadDependencia): ?string
    {
        $unidad = trim((string) $unidadDependencia);

        return preg_match('/^facultad(\s|$)/iu', $unidad) === 1 ? 'Administración de la ' . $unidad : null;
    }
}
