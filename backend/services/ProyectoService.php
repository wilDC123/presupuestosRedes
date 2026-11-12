<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\VersionDocumento;
use backend\models\CorrelativoDocumento;
use backend\models\EspecificacionEmision;
use backend\models\HistorialProyecto;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proceso;
use backend\models\Proyecto;
use backend\models\ProyectoAreaIntervencion;
use backend\models\ProyectoParticipante;
use backend\models\SolicitudCotizacion;
use backend\services\contracts\InfraestructuraProvider;
use Throwable;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Exception as DbException;
use yii\db\IntegrityException;

/**
 * Capa de Servicio para Proyecto ("Gestionar Proyecto": solo los datos
 * basicos del proyecto; plantilla, lista de items, especificaciones,
 * cotizacion y presupuesto son casos de uso separados que usan su id).
 *
 * Reglas de negocio que viven aqui:
 *  - el codigo RD-PI-PT-NNN se genera al crear y no se repite,
 *  - la unidad solicitante es texto libre (sin catalogo: se sugiere lo ya
 *    escrito),
 *  - cada area de intervencion debe existir y estar vigente en la
 *    infraestructura de la universidad (UsfxGEO); se guarda su referencia y
 *    una copia de los nombres (el tipo lo valida el modelo contra
 *    redes.TipoProyecto),
 *  - una fase nueva es un proyecto NUEVO enlazado a la ultima fase (varias
 *    fases pueden estar en elaboracion a la vez),
 *  - un proyecto con presupuesto aprobado o cancelado no se modifica.
 */
class ProyectoService
{
    /**
     * Titulo del paso de la lista en la barra de pasos (ver pasos()). La
     * pantalla de la lista lo usa para resaltarse.
     */
    public const PASO_LISTA = 'Lista de ítems y especificaciones';

    private ParametrosProyectoService $parametros;
    private InfraestructuraProvider $infraestructura;
    private HistorialService $historial;

    /**
     * Lo que no se pasa se le pide al contenedor de Yii, que lo arma con la
     * fuente configurada en backend/config/main.php (los tests pasan fuentes
     * de prueba).
     */
    public function __construct(?ParametrosProyectoService $parametros = null, ?InfraestructuraProvider $infraestructura = null)
    {
        $this->parametros = $parametros ?? Yii::$container->get(ParametrosProyectoService::class);
        $this->infraestructura = $infraestructura ?? Yii::$container->get(InfraestructuraProvider::class);
        $this->historial = new HistorialService();
    }

    /**
     * with() carga el presupuesto, el tipo y el proceso de todos los
     * proyectos de la pagina en una consulta extra cada uno (el listado
     * muestra los tres).
     */
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            // Por proyecto (el mas nuevo arriba) y, dentro, sus fases en orden: cada proyecto con sus fases juntas.
            // Las ordenes de cambio (parte 6) van debajo de su fase: en SQL Server NULL (la fase) va primero.
            'query' => Proyecto::find()->with(['presupuesto', 'tipoProyecto', 'proceso'])
                ->orderBy(['numeroProyecto' => SORT_DESC, 'numeroFase' => SORT_ASC, 'numeroOrdenCambio' => SORT_ASC, 'id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }

    public function obtener(int $id): ?Proyecto
    {
        return Proyecto::findOne($id);
    }

    /**
     * Proyecto vacio para el formulario "Nuevo proyecto". Si llega el id de
     * la ultima fase de un proyecto ("Agregar fase", revision del
     * 2026-11-05), se prepara como su FASE SIGUIENTE: mismo numero de
     * proyecto, los datos del proyecto (Proyecto::ATRIBUTOS_COMUNES, que no se
     * cambian en la fase) y, para empezar, el EQUIPO con su responsable y el
     * lugar de entrega por defecto (se pueden cambiar). El area de
     * intervencion y la carpeta economica empiezan vacias: son de cada fase.
     * Un proyecto nuevo sin fase anterior trae marcado el proceso PI
     * (ProcesoService::idPorDefecto()) y una fila de equipo con el rol
     * Proyectista, marcada como responsable, para elegir a la persona.
     */
    public function nuevo(?int $idProyectoAnterior = null): Proyecto
    {
        $proyecto = new Proyecto();
        $proyecto->idProceso = (new ProcesoService())->idPorDefecto();
        $anterior = $idProyectoAnterior === null ? null : $this->obtener($idProyectoAnterior);

        if ($anterior !== null && $this->puedeAgregarFase($anterior)) {
            $proyecto->idProyectoAnterior = $anterior->id;
            $proyecto->numeroFase = $anterior->numeroFase + 1;
            $proyecto->numeroProyecto = $anterior->numeroProyecto;
            $this->copiarDatosComunes($anterior, $proyecto);
            $proyecto->lugarEntrega = $anterior->lugarEntrega;
            $this->copiarEquipo($anterior, $proyecto);
        } else {
            $proyecto->numeroFase = 1;
        }

        $this->filaInicialSiNoHayEquipo($proyecto);

        return $proyecto;
    }

    /**
     * Deja en el modelo las areas y el equipo ya guardados, para que el
     * formulario de edicion los muestre como seleccionados.
     */
    public function cargarSeleccion(Proyecto $proyecto): void
    {
        $proyecto->areas = array_map(static fn (ProyectoAreaIntervencion $area) => $area->claves(), $proyecto->areasIntervencion);
        $this->copiarEquipo($proyecto, $proyecto);
        // Los registrados antes del 2026-10-29 no tienen equipo.
        $this->filaInicialSiNoHayEquipo($proyecto);
    }

    /**
     * Sin equipo, el formulario empieza con una fila de rol Proyectista,
     * marcada como responsable: solo falta elegir a la persona.
     */
    private function filaInicialSiNoHayEquipo(Proyecto $proyecto): void
    {
        if ($proyecto->equipo !== []) {
            return;
        }

        $idProyectista = (new RolProyectoService())->idProyectista();
        $proyecto->equipo = [0 => ['nombre' => '', 'idRol' => $idProyectista === null ? '' : (string) $idProyectista]];
        $proyecto->equipoResponsable = 0;
    }

    /**
     * Pone en $destino->equipo (el del formulario) el equipo guardado de
     * $origen, con su responsable.
     */
    private function copiarEquipo(Proyecto $origen, Proyecto $destino): void
    {
        $destino->equipo = [];
        $destino->equipoResponsable = null;

        foreach ($origen->participantes as $clave => $participante) {
            $destino->equipo[$clave] = $participante->fila();
            if ($participante->esResponsable) {
                $destino->equipoResponsable = $clave;
            }
        }
    }

    /**
     * Crea el proyecto y le asigna su codigo. Devuelve false si no llegaron
     * datos o si algo no es valido (los errores quedan en $proyecto->errors).
     */
    public function crear(Proyecto $proyecto, array $datosFormulario): bool
    {
        return $this->guardar($proyecto, $datosFormulario);
    }

    /**
     * Rechaza (devuelve false sin tocar nada) si el proyecto ya no es editable.
     */
    public function actualizar(Proyecto $proyecto, array $datosFormulario): bool
    {
        if (!$this->puedeEditarse($proyecto)) {
            return false;
        }

        return $this->guardar($proyecto, $datosFormulario);
    }

    /**
     * Devuelve null si se elimino, o el motivo por el que no.
     *
     * REGLA DE NEGOCIO: un proyecto con presupuesto, con una fase siguiente
     * que depende de el o con especificaciones emitidas (documentos firmados)
     * no se elimina. Se cuentan antes, para decir exactamente que lo impide.
     * Si la base lo impide por otra causa, queda en el log.
     */
    public function eliminar(Proyecto $proyecto): ?string
    {
        // Una orden de cambio no aprobada se elimina con sus documentos (parte 6).
        if ($proyecto->esOrdenCambio()) {
            return (new OrdenCambioFaseService())->eliminar($proyecto);
        }

        $usos = array_filter([
            'presupuesto' => Presupuesto::find()->andWhere(['idProyecto' => $proyecto->id])->exists() ? 1 : 0,
            'fase(s) siguiente(s)' => (int) Proyecto::find()->andWhere(['idProyectoAnterior' => $proyecto->id])->count(),
            'orden(es) de cambio' => (int) Proyecto::find()->andWhere(['idFaseOrigen' => $proyecto->id])->count(),
            'especificación(es) emitida(s)' => (int) EspecificacionEmision::find()->andWhere(['idProyecto' => $proyecto->id])->count(),
        ]);

        if ($usos !== []) {
            $partes = array_map(
                static fn (string $rotulo, int $cantidad): string => $rotulo === 'presupuesto' ? 'presupuesto' : "$cantidad $rotulo",
                array_keys($usos),
                $usos
            );

            return 'No se puede eliminar el proyecto: tiene ' . implode(', ', $partes) . '.';
        }

        // El historial queda (no tiene llave foranea): se anota la eliminacion
        // en la misma transaccion, por si el DELETE falla.
        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $this->historial->registrar($proyecto, HistorialProyecto::ACCION_PROYECTO_ELIMINADO, $proyecto->nombre);
            $proyecto->delete();
            $transaccion->commit();

            return null;
        } catch (IntegrityException $e) {
            $transaccion->rollBack();
            Yii::error($e->getMessage(), __METHOD__);

            return 'No se pudo eliminar el proyecto: otra parte del sistema lo usa.';
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }
    }

    /**
     * REGLA DE NEGOCIO: un proyecto cuyo presupuesto esta aprobado o
     * cancelado nunca se modifica. Una ampliacion es un proyecto nuevo de
     * fase siguiente.
     */
    public function puedeEditarse(Proyecto $proyecto): bool
    {
        $presupuesto = $proyecto->presupuesto;

        return $presupuesto === null || $presupuesto->puedeEditarse();
    }

    /**
     * La FASE 1 del proyecto, si hoy deja cambiar los datos comunes a todas las
     * fases; null si no (su presupuesto esta aprobado o cancelado, u otra fase
     * esta aprobada: datosComunesFijos()). La usa la vista del proyecto en las fases
     * 2+ para decir "se editan en la Fase 1" solo cuando es verdad (revision de
     * codigo del 2026-11-11).
     */
    public function faseParaDatosComunes(Proyecto $proyecto): ?Proyecto
    {
        $base = $proyecto->esOrdenCambio() ? $proyecto->faseOrigen : $proyecto;
        if ($base === null || $base->numeroProyecto === null) {
            return null;
        }

        foreach ($base->fases as $fase) {
            if ((int) $fase->numeroFase === 1) {
                return $this->puedeEditarse($fase) && !$this->datosComunesFijos($fase) ? $fase : null;
            }
        }

        return null;
    }

    public function estaAprobado(Proyecto $proyecto): bool
    {
        return ($proyecto->presupuesto->estado ?? null) === Presupuesto::ESTADO_APROBADO;
    }

    /**
     * REGLA DE NEGOCIO: una fase nueva se agrega desde la ULTIMA fase del
     * proyecto, este o no aprobada. El Ing. de DTIC dijo el 2026-11-09 que se
     * pueden empezar varias fases a la vez (del 06 al 09-11 se exigia la
     * anterior aprobada). "Reabrir" la ficha tecnica de un proyecto aprobado
     * sigue siendo agregarle una fase.
     */
    public function puedeAgregarFase(Proyecto $proyecto): bool
    {
        return $proyecto->esUltimaFase();
    }

    /**
     * Los DATOS DEL PROYECTO (Proyecto::ATRIBUTOS_COMUNES) son los mismos en
     * todas las fases y se escriben SOLO en la fase 1: en una fase 2+ van
     * fijos. En la fase 1 tambien quedan fijos cuando OTRA fase del proyecto
     * ya esta aprobada (la ficha tecnica del proyecto aprobada los muestra);
     * si la fase 1 esta aprobada, el proyecto entero ya no se edita
     * (puedeEditarse()).
     */
    public function datosComunesFijos(Proyecto $proyecto): bool
    {
        // Una fase 2+ y una orden de cambio los toman de su fase.
        if ($proyecto->idProyectoAnterior !== null || $proyecto->esOrdenCambio()) {
            return true;
        }

        if ($proyecto->isNewRecord || $proyecto->numeroProyecto === null) {
            return false;
        }

        $otrasFases = Proyecto::find()
            ->select('id')
            ->andWhere(['numeroProyecto' => $proyecto->numeroProyecto])
            ->andWhere(['<>', 'id', $proyecto->id]);

        return Presupuesto::find()
            ->andWhere(['idProyecto' => $otrasFases, 'estado' => Presupuesto::ESTADO_APROBADO])
            ->exists();
    }

    /** Los datos del proyecto (iguales en todas sus fases) de una fase a otra. */
    private function copiarDatosComunes(Proyecto $desde, Proyecto $hacia): void
    {
        foreach (Proyecto::ATRIBUTOS_COMUNES as $atributo) {
            $hacia->$atributo = $desde->$atributo;
        }
    }

    /**
     * La franja "FASES" de la vista del proyecto (2026-11-10), encima de la
     * barra de pasos: un grupo por fase, cada uno con la fase y despues sus
     * ordenes de cambio, con el presupuesto y su total. Asi se pasa de una
     * fase a otra sin bajar hasta el recuadro "Fases del proyecto". En una
     * orden de cambio, las fases de su fase de origen. VACIA (no se muestra)
     * con fase unica y sin ordenes de cambio.
     *
     * @return array<int, array<int, array{id: int, texto: string, presupuesto: ?Presupuesto, monto: ?float, actual: bool}>>
     */
    public function franjaFases(Proyecto $proyecto): array
    {
        $base = $proyecto->esOrdenCambio() ? $proyecto->faseOrigen : $proyecto;
        /** @var Proyecto[] $fases */
        $fases = $base->numeroProyecto !== null && $base->fases !== [] ? $base->fases : [$base];

        if (count($fases) === 1 && $fases[0]->ordenesCambio === []) {
            return [];
        }

        $grupos = [];
        foreach ($fases as $fase) {
            $grupo = [];
            foreach (array_merge([$fase], $fase->ordenesCambio) as $fila) {
                $grupo[] = [
                    'id' => (int) $fila->id,
                    'texto' => $fila->esOrdenCambio() ? $fila->textoOrdenCambio() : 'Fase ' . (int) $fila->numeroFase,
                    'presupuesto' => $fila->presupuesto,
                    'monto' => $this->montoPresupuesto($fila),
                    'actual' => (int) $fila->id === (int) $proyecto->id,
                ];
            }
            $grupos[] = $grupo;
        }

        return $grupos;
    }

    /**
     * Total del presupuesto del proyecto (null si aun no tiene presupuesto).
     */
    public function montoPresupuesto(Proyecto $proyecto): ?float
    {
        $presupuesto = $proyecto->presupuesto;

        if ($presupuesto === null) {
            return null;
        }

        return (float) PresupuestoDetalle::find()->andWhere(['idPresupuesto' => $presupuesto->id])->sum('precioTotal');
    }

    /**
     * Lo que imprime la FICHA TECNICA DEL PROYECTO: UNA por proyecto, con
     * TODAS sus fases (revision del 2026-11-05, punto 2; parte 5d): arriba lo
     * del proyecto (la vista lo toma del modelo) y despues un bloque por fase
     * con su area, equipo, fechas y documentos de su carpeta economica. Sale
     * igual desde cualquier fase.
     *
     * Version y aprobacion se CALCULAN (no hay tabla): cada fase que se aprueba
     * es una version, contadas en el ORDEN EN QUE SE APROBARON (la primera
     * aprobada da 1.0, la segunda 1.1..., sea la fase 1 o la 2: desde el
     * 2026-11-09 varias fases se elaboran a la vez). "Reabrir la ficha tecnica"
     * es agregar una fase; una ORDEN DE CAMBIO no cambia la version. Mientras
     * ALGUNA fase tenga su presupuesto sin aprobar, la ficha tecnica del
     * proyecto es la version siguiente en BORRADOR (marca de agua). Una fase
     * con el presupuesto CANCELADO no cuenta como pendiente (nunca se
     * aprobara). La fecha de la cabecera es la de creacion de la fase mas
     * nueva: "arriba va la fecha de creacion del documento". Desde el
     * 2026-11-09 (formatos corregidos v3, parte 6c) la ficha NO lleva firmas.
     *
     * Cada fase trae su CARPETA ECONOMICA (carpeta()) y, dentro, la de cada
     * una de sus ORDENES DE CAMBIO ('ordenes'). 'costo' es el TOTAL UNICO del
     * proyecto (costoTotal()).
     *
     * @return array{
     *   codigo: string|null,
     *   fases: array<int, array<string, mixed>>,
     *   variasFases: bool,
     *   costo: array{total: float, partes: array<int, array{rotulo: string, monto: float}>},
     *   version: string,
     *   fechaDocumento: string|null,
     *   aprobada: bool,
     *   fechaAprobacion: string
     * }
     */
    public function datosFicha(Proyecto $proyecto): array
    {
        /** @var Proyecto[] $fases */
        $fases = $proyecto->numeroProyecto !== null ? $proyecto->fases : [$proyecto];
        $fases = $fases === [] ? [$proyecto] : $fases;
        $aprobadas = array_values(array_filter($fases, fn (Proyecto $fase) => $this->estaAprobado($fase)));
        $pendientes = array_filter(
            $fases,
            fn (Proyecto $fase) => !$this->estaAprobado($fase) && ($fase->presupuesto->estado ?? null) !== Presupuesto::ESTADO_CANCELADO
        );
        $todasAprobadas = $aprobadas !== [] && $pendientes === [];
        // La aprobada mas reciente (a igual fecha, la de fase mayor) da la fecha de aprobacion.
        usort($aprobadas, static fn (Proyecto $a, Proyecto $b) => [(string) $a->presupuesto->fechaAprobacion, (int) $a->numeroFase]
            <=> [(string) $b->presupuesto->fechaAprobacion, (int) $b->numeroFase]);
        $presupuesto = $todasAprobadas ? end($aprobadas)->presupuesto : null;
        $creaciones = array_map(static fn (Proyecto $fase) => (string) $fase->fechaCreacion, $fases);

        $porFase = [];
        foreach ($fases as $fase) {
            $porFase[] = $this->carpeta($fase) + [
                'fechas' => $this->historial->fechas($fase),
                'ordenes' => array_map(fn (Proyecto $orden) => $this->carpeta($orden), $fase->ordenesCambio),
            ];
        }

        return [
            'codigo' => $fases[0]->codigo,
            'fases' => $porFase,
            'variasFases' => count($fases) > 1,
            'costo' => $this->costoTotal($proyecto),
            'version' => VersionDocumento::texto(max(1, $todasAprobadas ? count($aprobadas) : count($aprobadas) + 1)),
            'fechaDocumento' => max($creaciones) === '' ? null : max($creaciones),
            'aprobada' => $todasAprobadas,
            'fechaAprobacion' => $presupuesto !== null ? $presupuesto->fechaAprobacionFormateada() : '',
        ];
    }

    /**
     * La CARPETA ECONOMICA de una fase o de una orden de cambio, para la ficha
     * tecnica: lista de materiales, presupuesto (y su total), especificaciones
     * vigentes, solicitudes con sus proformas recibidas. 'reemisiones' =
     * [id de la emision vigente => motivo] de las ET reemitidas (el motivo
     * queda en la emision anterior, la "reemplazada por" la vigente).
     *
     * @return array{proyecto: Proyecto, monto: float|null, especificaciones: EspecificacionEmision[], reemisiones: array<int, string>, solicitudes: SolicitudCotizacion[], lista: array}
     */
    private function carpeta(Proyecto $proyecto): array
    {
        $especificaciones = (new EspecificacionEmisionService())->vigentesDelProyecto($proyecto);
        $reemitidas = array_map(
            static fn (EspecificacionEmision $e): int => (int) $e->id,
            array_filter($especificaciones, static fn (EspecificacionEmision $e): bool => (int) $e->versionDocumento > 1)
        );

        return [
            'proyecto' => $proyecto,
            'monto' => $this->montoPresupuesto($proyecto),
            'especificaciones' => $especificaciones,
            'reemisiones' => $reemitidas === [] ? [] : EspecificacionEmision::find()
                ->select(['motivo', 'idReemplazadaPor'])
                ->andWhere(['idReemplazadaPor' => $reemitidas])
                ->indexBy('idReemplazadaPor')
                ->column(),
            'solicitudes' => (new SolicitudCotizacionService())->listar($proyecto),
            'lista' => (new ListaItemsService())->documento($proyecto),
        ];
    }

    /**
     * COSTO TOTAL DEL PROYECTO (parte 6c, formatos corregidos v3): UN solo
     * total, la suma de los presupuestos APROBADOS de todas sus fases y de sus
     * ordenes de cambio, en orden (cada fase seguida de sus ordenes). Las
     * partes, para el detalle "Fase 1: 5,732.50 + orden de cambio N.° 1:
     * 1,870.00 + fase 2: 3,026.00"; con fase unica, la fase se llama
     * "Presupuesto".
     *
     * @return array{total: float, partes: array<int, array{rotulo: string, monto: float}>}
     */
    public function costoTotal(Proyecto $proyecto): array
    {
        $base = $proyecto->esOrdenCambio() ? $proyecto->faseOrigen : $proyecto;
        /** @var Proyecto[] $fases */
        $fases = $base->numeroProyecto !== null && $base->fases !== [] ? $base->fases : [$base];
        $variasFases = count($fases) > 1;
        $partes = [];

        foreach ($fases as $fase) {
            foreach (array_merge([$fase], $fase->ordenesCambio) as $fila) {
                if (!$this->estaAprobado($fila)) {
                    continue;
                }
                $rotulo = $fila->esOrdenCambio()
                    ? 'orden de cambio N.° ' . (int) $fila->numeroOrdenCambio
                    : ($variasFases ? 'fase ' . (int) $fila->numeroFase : 'presupuesto');
                $partes[] = ['rotulo' => $rotulo, 'monto' => (float) $this->montoPresupuesto($fila)];
            }
        }

        // La primera parte con mayuscula: "Fase 1: ... + orden de cambio N.° 1: ...".
        if ($partes !== []) {
            $partes[0]['rotulo'] = mb_strtoupper(mb_substr($partes[0]['rotulo'], 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($partes[0]['rotulo'], 1, null, 'UTF-8');
        }

        return ['total' => array_sum(array_column($partes, 'monto')), 'partes' => $partes];
    }

    /**
     * Sugerencias para los campos "Unidad / dependencia" y "Unidad
     * solicitante" (comparten la lista): lo ya escrito en cualquiera de los
     * dos en otros proyectos y las facultades de los parametros. Es solo
     * ayuda para no transcribir de nuevo: los campos aceptan cualquier texto.
     *
     * @return string[] ordenadas, sin repetidas (sin importar mayusculas)
     */
    public function sugerenciasUnidad(): array
    {
        $escritas = [];

        foreach (['unidadDependencia', 'unidadSolicitante'] as $columna) {
            $escritas = array_merge($escritas, Proyecto::find()
                ->select($columna)
                ->distinct()
                ->andWhere(['not', [$columna => null]])
                ->column());
        }

        $sugerencias = [];

        foreach (array_merge($escritas, array_values($this->parametros->opcionesFacultad())) as $texto) {
            $texto = trim((string) $texto);

            if ($texto !== '') {
                $sugerencias[mb_strtolower($texto, 'UTF-8')] ??= $texto;
            }
        }

        ksort($sugerencias);

        return array_values($sugerencias);
    }

    /**
     * Las areas del modelo ($proyecto->areas: las guardadas, las heredadas de
     * la fase anterior o las que llegaron en un POST con errores), cada una
     * con su "texto", su "grupo" (edificio · bloque) y su "detalle" (piso y
     * ambiente) para mostrarla en el formulario. Las guardadas usan sus
     * nombres copiados; las escritas a mano, lo que se escribio; las demas
     * se piden a la fuente. "manual" dice cual es cual.
     *
     * @return array<int, array<string, mixed>> las claves de normalizarArea() + manual, texto, grupo y detalle
     */
    public function areasDelFormulario(Proyecto $proyecto): array
    {
        $guardadas = [];

        foreach ($proyecto->isNewRecord ? [] : $proyecto->areasIntervencion as $area) {
            $guardadas[self::claveArea($area->claves())] = $area;
        }

        $areas = [];

        foreach (is_array($proyecto->areas) ? $proyecto->areas : [] as $datos) {
            $claves = self::normalizarArea(is_array($datos) ? $datos : []);

            if ($claves === null) {
                continue;
            }

            $area = $guardadas[self::claveArea($claves)] ?? null;

            if ($area === null && isset($claves['manual'])) {
                $area = new ProyectoAreaIntervencion(array_diff_key($claves, ['manual' => true]));
            } elseif ($area === null) {
                try {
                    $ruta = $this->infraestructura->ruta($claves['idEdificio'], $claves['idBloqueArea'], $claves['numeroNivel'], $claves['idEspacio']);
                } catch (DbException $e) {
                    $ruta = null;
                }

                if ($ruta === null) {
                    $areas[] = $claves + ['manual' => false, 'texto' => $texto = 'Área no vigente (edificio ' . $claves['idEdificio'] . ')', 'grupo' => $texto, 'detalle' => ''];
                    continue;
                }

                $area = new ProyectoAreaIntervencion($claves + $ruta);
            }

            $areas[] = $claves + ['manual' => $area->esManual(), 'texto' => $area->texto(), 'grupo' => $area->grupo(), 'detalle' => $area->detalle()];
        }

        return $areas;
    }

    /**
     * Edificios vigentes para el campo "Edificio" del formulario, cada uno con
     * cuantos proyectos ya lo tienen como area (el formulario pone primero
     * los usados: casi siempre se trabaja en los mismos edificios).
     *
     * @return array<int, array{id: string, nombre: string, usos: int}>|null ordenados por nombre; null si UsfxGEO no responde
     */
    public function edificiosParaFormulario(): ?array
    {
        $vigentes = $this->consultarInfraestructura(static fn (InfraestructuraProvider $geo): array => $geo->edificios());
        if ($vigentes === null) {
            return null;
        }

        $usos = ProyectoAreaIntervencion::find()
            ->select(['total' => 'COUNT(DISTINCT idProyecto)', 'idEdificio'])
            ->groupBy('idEdificio')
            ->indexBy('idEdificio')
            ->column();

        $edificios = [];

        foreach ($vigentes as $id => $nombre) {
            $edificios[] = ['id' => (string) $id, 'nombre' => $nombre, 'usos' => (int) ($usos[(string) $id] ?? 0)];
        }

        return $edificios;
    }

    /** Cuantos ambientes muestra el buscador del formulario. */
    public const MAX_AMBIENTES_BUSCADOS = 30;

    /**
     * Lo que encuentra el buscador del formulario:
     *  - 'edificios': los edificios cuyo nombre coincide (van primero: asi
     *    "ciencias y tecnologia" muestra el edificio aunque tenga cientos de
     *    ambientes),
     *  - 'ambientes': cada uno con su area completa y el "texto" para mostrar,
     *  - 'hayMas': true si hay mas ambientes de los que se muestran (el
     *    formulario pide escribir algo mas preciso).
     *
     *  - 'error': solo si UsfxGEO no responde (AVISO_INFRAESTRUCTURA).
     *
     * @return array{edificios: array<int, array{idEdificio: string, edificio: string}>, ambientes: array<int, array<string, mixed>>, hayMas: bool, error?: string}
     */
    public function buscarAreas(string $texto): array
    {
        if (mb_strlen(trim($texto), 'UTF-8') < 2) {
            return ['edificios' => [], 'ambientes' => [], 'hayMas' => false];
        }

        // Se pide uno mas de los que se muestran, solo para saber si hay mas.
        $encontrado = $this->consultarInfraestructura(static fn (InfraestructuraProvider $geo): array => [
            'ambientes' => $geo->buscarAmbientes($texto, self::MAX_AMBIENTES_BUSCADOS + 1),
            'edificios' => $geo->buscarEdificios($texto),
        ]);
        if ($encontrado === null) {
            return ['edificios' => [], 'ambientes' => [], 'hayMas' => false, 'error' => self::AVISO_INFRAESTRUCTURA];
        }
        $ambientes = $encontrado['ambientes'];

        return [
            'edificios' => $encontrado['edificios'],
            'ambientes' => array_map(static function (array $fila): array {
                $fila['texto'] = (new ProyectoAreaIntervencion($fila))->texto();

                return $fila;
            }, array_slice($ambientes, 0, self::MAX_AMBIENTES_BUSCADOS)),
            'hayMas' => count($ambientes) > self::MAX_AMBIENTES_BUSCADOS,
        ];
    }

    /** Lo que se le dice al usuario cuando UsfxGEO no responde. */
    public const AVISO_INFRAESTRUCTURA = 'La infraestructura de la universidad (UsfxGEO) no responde en este momento. Puede escribir el área a mano («¿No está en la lista?») o intentar más tarde.';

    /**
     * Hace una consulta a UsfxGEO (otra base, de Desarrollo) sin que su caida
     * tumbe la pantalla: si no responde, deja el error en el log y devuelve
     * null, y quien llama muestra AVISO_INFRAESTRUCTURA (auditoria del
     * 2026-11-11, hallazgo 4: antes, sin UsfxGEO no se podia crear ni editar
     * ningun proyecto). Lo usan el formulario, el buscador y el paso a paso.
     *
     * @template T
     * @param callable(InfraestructuraProvider): T $consulta
     * @return T|null
     */
    public function consultarInfraestructura(callable $consulta): mixed
    {
        try {
            return $consulta($this->infraestructura);
        } catch (DbException $e) {
            Yii::error('UsfxGEO no responde: ' . $e->getMessage(), __METHOD__);

            return null;
        }
    }


    /**
     * El paso "Presupuesto" del proyecto: la pantalla unica donde se elabora
     * (ElaborarPresupuestoController).
     */
    public function urlPresupuesto(Proyecto $proyecto): array
    {
        return ['/elaborar-presupuesto/index', 'idProyecto' => $proyecto->id];
    }

    /**
     * Pasos del proyecto, para la barra de navegacion de su pantalla
     * principal. Cada paso dice en que estado esta:
     *  - 'hecho'      ya se completo
     *  - 'actual'     es el siguiente por hacer
     *  - 'pendiente'  todavia no corresponde
     * y la ruta a la que lleva ('url' => null: ese paso ya no se puede
     * abrir en el estado actual del proyecto, ej. Plantilla con la lista
     * enviada a cotizar).
     *
     * @return array<int, array{titulo: string, estado: string, url: array|null}>
     */
    public function pasos(Proyecto $proyecto): array
    {
        $presupuesto = $proyecto->presupuesto;
        $urlPresupuesto = $this->urlPresupuesto($proyecto);

        $listaEnEdicion = $proyecto->estadoLista === Proyecto::LISTA_EDICION;

        if ($presupuesto === null) {
            // El presupuesto nace de la lista: mientras la lista este en
            // edicion, el paso actual es la lista (o la plantilla).
            $estadoPresupuesto = $listaEnEdicion ? 'pendiente' : 'actual';
        } else {
            $estadoPresupuesto = $presupuesto->puedeEditarse() ? 'actual' : 'hecho';
        }

        if (!$proyecto->listaIniciada) {
            $estadoLista = 'pendiente';
        } else {
            $estadoLista = $listaEnEdicion ? 'actual' : 'hecho';
        }

        return [
            ['titulo' => 'Datos', 'estado' => 'hecho', 'url' => ['/proyecto/view', 'id' => $proyecto->id]],
            [
                'titulo' => 'Plantilla',
                'estado' => $proyecto->listaIniciada ? 'hecho' : 'actual',
                // Con el proyecto cerrado o la lista ya enviada a cotizacion,
                // la pantalla de plantilla ya no aplica.
                'url' => $this->puedeEditarse($proyecto) && $listaEnEdicion ? ['/proyecto/plantilla', 'id' => $proyecto->id] : null,
            ],
            // Un solo paso: las especificaciones se enlazan item por item EN la
            // lista (son opcionales), en la misma pantalla y al mismo tiempo.
            ['titulo' => self::PASO_LISTA, 'estado' => $estadoLista, 'url' => ['/lista-items/index', 'id' => $proyecto->id]],
            [
                // Solicitudes de cotizacion: se arman con la lista ya enviada a cotizar.
                'titulo' => 'Cotización',
                'estado' => $listaEnEdicion ? 'pendiente' : ($presupuesto === null ? 'actual' : 'hecho'),
                'url' => ['/solicitud-cotizacion/index', 'id' => $proyecto->id],
            ],
            ['titulo' => 'Presupuesto', 'estado' => $estadoPresupuesto, 'url' => $urlPresupuesto],
        ];
    }

    /**
     * ORDEN (importa en Yii2): load() -> validar -> completar lo que calcula
     * el sistema -> guardar todo dentro de UNA transaccion, para que nunca
     * quede un proyecto guardado sin sus areas o su equipo.
     *
     * La ubicacion anterior (facultad, edificio, redes.ProyectoBloque) no se
     * toca: queda como estaba en los proyectos registrados antes del cambio.
     */
    private function guardar(Proyecto $proyecto, array $datosFormulario): bool
    {
        // Una orden de cambio tiene su propio formulario (OrdenCambioFaseService):
        // por aqui se copiaria como si fuera una fase 1.
        if ($proyecto->esOrdenCambio()) {
            $proyecto->addError('nombre', 'Una orden de cambio se edita desde su propio formulario.');

            return false;
        }

        // Como estaba antes, para anotar en el historial solo lo que cambio.
        $antes = $proyecto->isNewRecord ? null : $this->historial->foto(Proyecto::findOne($proyecto->id));

        if (!$proyecto->load($datosFormulario)) {
            return false;
        }

        foreach (['idTipoProyecto', 'idProceso', 'idProyectoAnterior'] as $atributo) {
            if ($proyecto->$atributo === '') {
                $proyecto->$atributo = null;
            }
        }

        // La fase de un proyecto no se cambia al editarla (se agrega con "Agregar fase").
        if (!$proyecto->isNewRecord) {
            $proyecto->idProyectoAnterior = $proyecto->getOldAttribute('idProyectoAnterior');
        }

        // Una fase siguiente lleva los datos del proyecto tal como estan en la anterior;
        // una fase 1 con otra fase aprobada, los que ya tenia. Lo que llegue del
        // formulario para ellos no cuenta (los campos van deshabilitados).
        $anteriorDatos = $proyecto->idProyectoAnterior === null ? null : Proyecto::findOne((int) $proyecto->idProyectoAnterior);
        if ($anteriorDatos !== null) {
            $this->copiarDatosComunes($anteriorDatos, $proyecto);
        } elseif ($this->datosComunesFijos($proyecto)) {
            foreach (Proyecto::ATRIBUTOS_COMUNES as $atributo) {
                $proyecto->$atributo = $proyecto->getOldAttribute($atributo);
            }
        }

        // validate() borra los errores anteriores: por eso va ANTES de las
        // verificaciones propias del servicio, que agregan los suyos.
        $proyecto->validate();

        $areas = $this->armarAreas($proyecto);
        $this->aplicarFase($proyecto);
        $equipo = $this->armarEquipo($proyecto);

        if ($proyecto->hasErrors()) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            if ($proyecto->isNewRecord) {
                $this->insertarConCodigo($proyecto);
            } else {
                $proyecto->save(false);
                $this->ajustarProcesoDeLaLista($proyecto);
            }

            $this->reemplazarAreas($proyecto, $areas);
            $this->reemplazarEquipo($proyecto, $equipo);
            $this->anotarEnHistorial($proyecto, $antes);
            $this->llevarDatosComunesALasFases($proyecto);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Arma las areas de intervencion con lo que llego del formulario: cada
     * una debe existir y estar vigente en la fuente de infraestructura, de la
     * que se copian los nombres. Un area que el proyecto ya tenia guardada se
     * conserva tal cual (con sus nombres de entonces), aunque despues la
     * hayan dado de baja en UsfxGEO. Las escritas a mano se guardan con lo
     * que se escribio (sin consultar la fuente). Las repetidas, una vez.
     *
     * @return ProyectoAreaIntervencion[] sin guardar, en el orden recibido
     */
    private function armarAreas(Proyecto $proyecto): array
    {
        $guardadas = [];

        if (!$proyecto->isNewRecord) {
            foreach ($proyecto->areasIntervencion as $area) {
                $guardadas[self::claveArea($area->claves())] = $area;
            }
        }

        $areas = [];

        foreach (is_array($proyecto->areas) ? $proyecto->areas : [] as $datos) {
            $claves = self::normalizarArea(is_array($datos) ? $datos : []);

            if ($claves === null) {
                continue;
            }

            $clave = self::claveArea($claves);

            if (isset($areas[$clave])) {
                continue;
            }

            if (isset($guardadas[$clave])) {
                $areas[$clave] = new ProyectoAreaIntervencion($guardadas[$clave]->getAttributes(null, ['id', 'idProyecto', 'orden']));
                continue;
            }

            if (isset($claves['manual'])) {
                $error = self::errorAreaManual($claves);

                if ($error !== null) {
                    $proyecto->addError('areas', $error);
                    continue;
                }

                $areas[$clave] = new ProyectoAreaIntervencion(array_diff_key($claves, ['manual' => true]));
                continue;
            }

            try {
                $ruta = $this->infraestructura->ruta($claves['idEdificio'], $claves['idBloqueArea'], $claves['numeroNivel'], $claves['idEspacio']);
            } catch (DbException $e) {
                Yii::error($e->getMessage(), __METHOD__);
                $proyecto->addError('areas', 'No se pudo consultar la infraestructura de la universidad (UsfxGEO). Intente de nuevo más tarde.');

                return [];
            }

            if ($ruta === null) {
                $proyecto->addError('areas', 'Una de las áreas de intervención no existe o ya no está vigente en la infraestructura de la universidad.');
                continue;
            }

            $areas[$clave] = new ProyectoAreaIntervencion($claves + $ruta);
        }

        return array_values($areas);
    }

    /**
     * Largo maximo de cada nombre de un area escrita a mano (las columnas de
     * redes.ProyectoAreaIntervencion) y como se llama en pantalla.
     */
    private const LARGO_AREA_MANUAL = [
        'edificio' => [200, 'edificio'],
        'bloque' => [200, 'bloque'],
        'nivel' => [100, 'piso'],
        'ambiente' => [250, 'espacio'],
    ];

    /**
     * Deja un area como la espera el resto del servicio ('' -> null):
     *  - de UsfxGEO: sus identificadores, como los espera la fuente,
     *  - escrita a mano (llega con "manual"): sus nombres, con los espacios
     *    de mas quitados.
     * null si no tiene edificio (fila vacia: se ignora).
     *
     * @return array{idEdificio: string, idBloqueArea: ?int, numeroNivel: ?string, idEspacio: ?string}|array{manual: true, edificio: string, bloque: ?string, nivel: ?string, ambiente: ?string}|null
     */
    private static function normalizarArea(array $datos): ?array
    {
        $texto = static function ($valor): ?string {
            $valor = is_scalar($valor) ? trim(preg_replace('/\s+/u', ' ', (string) $valor)) : '';

            return $valor === '' ? null : $valor;
        };

        if (!empty($datos['manual'])) {
            $edificio = $texto($datos['edificio'] ?? null);

            return $edificio === null ? null : [
                'manual' => true,
                'edificio' => $edificio,
                'bloque' => $texto($datos['bloque'] ?? null),
                'nivel' => $texto($datos['nivel'] ?? null),
                'ambiente' => $texto($datos['ambiente'] ?? null),
            ];
        }

        $idEdificio = $texto($datos['idEdificio'] ?? null);

        if ($idEdificio === null) {
            return null;
        }

        $idBloque = $texto($datos['idBloqueArea'] ?? null);

        return [
            'idEdificio' => $idEdificio,
            'idBloqueArea' => $idBloque !== null && ctype_digit($idBloque) ? (int) $idBloque : null,
            'numeroNivel' => $texto($datos['numeroNivel'] ?? null),
            'idEspacio' => $texto($datos['idEspacio'] ?? null),
        ];
    }

    /**
     * Para no guardar dos veces la misma area. Las escritas a mano se
     * comparan por sus nombres, sin importar mayusculas.
     */
    private static function claveArea(array $claves): string
    {
        if (isset($claves['manual'])) {
            return 'manual|' . mb_strtolower(implode('|', [$claves['edificio'], $claves['bloque'] ?? '', $claves['nivel'] ?? '', $claves['ambiente'] ?? '']), 'UTF-8');
        }

        return implode('|', [$claves['idEdificio'], $claves['idBloqueArea'] ?? '', $claves['numeroNivel'] ?? '', $claves['idEspacio'] ?? '']);
    }

    /**
     * El error de un area escrita a mano, o null si esta bien. Solo se
     * revisa el largo: el resto es texto libre.
     */
    private static function errorAreaManual(array $claves): ?string
    {
        foreach (self::LARGO_AREA_MANUAL as $campo => [$maximo, $nombre]) {
            if ($claves[$campo] !== null && mb_strlen($claves[$campo], 'UTF-8') > $maximo) {
                return "En un área escrita a mano, el $nombre tiene más de $maximo caracteres.";
            }
        }

        return null;
    }

    /**
     * Calcula numeroFase a partir del proyecto de la fase anterior (que no
     * tiene que estar aprobada: varias fases pueden elaborarse a la vez).
     */
    private function aplicarFase(Proyecto $proyecto): void
    {
        if ($proyecto->idProyectoAnterior === null) {
            $proyecto->numeroFase = 1;

            return;
        }

        $anterior = $this->obtener((int) $proyecto->idProyectoAnterior);

        if ($anterior === null || $anterior->esOrdenCambio() || (!$proyecto->isNewRecord && $anterior->id === $proyecto->id)) {
            $proyecto->addError('idProyectoAnterior', 'No se encontró la fase anterior del proyecto.');

            return;
        }

        // Al editar una fase ya creada su anterior no cambia: solo se revisa al crearla.
        if (!$proyecto->isNewRecord) {
            $proyecto->numeroFase = $anterior->numeroFase + 1;

            return;
        }

        $proyecto->numeroFase = $anterior->numeroFase + 1;

        // Una fase no se repite dentro del proyecto (la base tambien lo impide).
        $ocupada = $anterior->numeroProyecto !== null && Proyecto::find()
            ->andWhere(['numeroProyecto' => $anterior->numeroProyecto, 'numeroFase' => $proyecto->numeroFase])
            ->andFilterWhere(['<>', 'id', $proyecto->id])
            ->exists();

        if ($ocupada) {
            $proyecto->addError('idProyectoAnterior', $anterior->identificador() . ' ya tiene una fase ' . $proyecto->numeroFase . '.');
        }
    }

    /**
     * Con varias fases a la vez, la fase 1 se puede editar cuando ya existen
     * las siguientes: sus DATOS DEL PROYECTO se copian a las demas fases en la
     * misma transaccion (si no, cada fase diria otro nombre). Cada fase anota
     * lo que cambio en su historial; si cambio el proceso, su lista cambia de
     * codigo como en la fase 1 (solo si aun no se envio a cotizacion).
     */
    private function llevarDatosComunesALasFases(Proyecto $fase1): void
    {
        if ($fase1->idProyectoAnterior !== null || $fase1->numeroProyecto === null) {
            return;
        }

        $otras = Proyecto::find()
            ->andWhere(['numeroProyecto' => $fase1->numeroProyecto])
            ->andWhere(['<>', 'id', $fase1->id])
            ->all();

        /** @var Proyecto $fase */
        foreach ($otras as $fase) {
            $nuevos = [];
            foreach (Proyecto::ATRIBUTOS_COMUNES as $atributo) {
                if ((string) $fase->$atributo !== (string) $fase1->$atributo) {
                    $nuevos[$atributo] = $fase1->$atributo;
                }
            }

            if ($nuevos === []) {
                continue;
            }

            $antes = $this->historial->foto($fase);
            $fase->updateAttributes($nuevos);
            $this->ajustarProcesoDeLaLista($fase);
            $cambios = $this->historial->cambios($antes, $this->historial->foto(Proyecto::findOne($fase->id)));
            if ($cambios !== '') {
                $this->historial->registrar($fase, HistorialProyecto::ACCION_PROYECTO_MODIFICADO, "Desde la fase 1:\n" . $cambios);
            }
        }
    }

    /**
     * Proyecto nuevo: "Proyecto creado". Editado: un renglon por dato que
     * cambio ("Unidad solicitante: Decanato → Rectorado"); si no cambio nada,
     * no se anota. Se compara con el proyecto recien leido de la base.
     *
     * @param array<string, string>|null $antes HistorialService::foto() antes de guardar (null si es nuevo)
     */
    private function anotarEnHistorial(Proyecto $proyecto, ?array $antes): void
    {
        if ($antes === null) {
            $detalle = $proyecto->nombre
                . ($proyecto->idProyectoAnterior === null ? '' : "\nFase " . (int) $proyecto->numeroFase . ' del proyecto ' . $proyecto->numeroTexto());
            $this->historial->registrar($proyecto, HistorialProyecto::ACCION_PROYECTO_CREADO, $detalle);

            return;
        }

        $cambios = $this->historial->cambios($antes, $this->historial->foto(Proyecto::findOne($proyecto->id)));
        if ($cambios !== '') {
            $this->historial->registrar($proyecto, HistorialProyecto::ACCION_PROYECTO_MODIFICADO, $cambios);
        }
    }

    /**
     * Arma el equipo del proyecto con lo que llego del formulario (revision
     * del 2026-10-29). REGLAS DE NEGOCIO:
     *  - al menos una persona, y UNA sola marcada como responsable (no tiene
     *    por que ser proyectista: un mantenimiento del data center lo lleva
     *    quien lo administra),
     *  - cada fila con persona y rol; una fila sin ninguno de los dos se ignora,
     *  - persona y rol de las listas (activos), salvo los que el proyecto ya
     *    tenia o los que trae de la fase anterior: se conservan aunque se
     *    hayan desactivado,
     *  - una persona puede tener dos roles, pero no el mismo dos veces.
     *
     * @return ProyectoParticipante[] sin guardar, en el orden del formulario
     */
    private function armarEquipo(Proyecto $proyecto): array
    {
        $filas = is_array($proyecto->equipo) ? $proyecto->equipo : [];
        $conservados = $this->participantesConservados($proyecto);
        $nombresPermitidos = array_merge(
            (new PersonalService())->nombresActivos(),
            array_map(static fn (ProyectoParticipante $p) => $p->nombre, $conservados)
        );
        $rolesPermitidos = array_map('intval', array_keys(
            (new RolProyectoService())->opciones(array_map(static fn (ProyectoParticipante $p) => (int) $p->idRol, $conservados))
        ));

        $equipo = [];
        $vistos = [];

        foreach ($filas as $clave => $fila) {
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            $idRol = trim((string) ($fila['idRol'] ?? ''));

            if ($nombre === '' && $idRol === '') {
                continue;
            }
            if ($nombre === '' || $idRol === '') {
                $proyecto->addError('equipo', 'Cada persona del equipo necesita su nombre y su rol.');
                continue;
            }
            if (!in_array($nombre, $nombresPermitidos, true)) {
                $proyecto->addError('equipo', '"' . $nombre . '" no está en la lista de personal.');
                continue;
            }
            if (!ctype_digit($idRol) || !in_array((int) $idRol, $rolesPermitidos, true)) {
                $proyecto->addError('equipo', 'El rol elegido para "' . $nombre . '" no existe o está inactivo.');
                continue;
            }

            $persona = mb_strtolower($nombre, 'UTF-8') . '|' . $idRol;
            if (isset($vistos[$persona])) {
                $proyecto->addError('equipo', '"' . $nombre . '" está dos veces con el mismo rol.');
                continue;
            }
            $vistos[$persona] = true;

            $equipo[] = new ProyectoParticipante([
                'nombre' => $nombre,
                'idRol' => (int) $idRol,
                'esResponsable' => $proyecto->equipoResponsable !== null && (string) $clave === (string) $proyecto->equipoResponsable,
            ]);
        }

        if (!$proyecto->hasErrors('equipo')) {
            $responsables = array_filter($equipo, static fn (ProyectoParticipante $p) => $p->esResponsable);
            if ($equipo === []) {
                $proyecto->addError('equipo', 'Agregue al menos a una persona: el responsable del proyecto.');
            } elseif (count($responsables) !== 1) {
                $proyecto->addError('equipo', 'Marque quién es el responsable del proyecto.');
            }
        }

        return $equipo;
    }

    /**
     * Personas y roles que se aceptan aunque ya no esten activos: los del
     * equipo guardado de este proyecto y los de la fase anterior (de donde
     * se heredan).
     *
     * @return ProyectoParticipante[]
     */
    private function participantesConservados(Proyecto $proyecto): array
    {
        $conservados = $proyecto->isNewRecord ? [] : $proyecto->participantes;
        $anterior = $proyecto->idProyectoAnterior === null ? null : Proyecto::findOne((int) $proyecto->idProyectoAnterior);

        return $anterior === null ? $conservados : array_merge($conservados, $anterior->participantes);
    }

    /**
     * Inserta el proyecto con el siguiente codigo del correlativo PT
     * (RD-PI-PT-005, ver CorrelativoDocumento::codigo()) y el de su lista de
     * materiales (RD-<proceso>-LM-005, revision del 2026-11-05: cada proyecto
     * tiene una sola lista, que conserva su codigo en todas sus versiones). Se
     * llama dentro de la transaccion de guardar(): si el INSERT falla, los
     * numeros tomados vuelven atras con todo lo demas. Los indices unicos de
     * la base impiden igual cualquier codigo repetido.
     */
    private function insertarConCodigo(Proyecto $proyecto): void
    {
        $codificacion = new CodificacionService();
        // Una fase siguiente lleva el MISMO numero de proyecto que la anterior; un proyecto nuevo, el siguiente.
        $this->asignarNumero($proyecto, $codificacion);
        // Codigo PT = el de la FICHA TECNICA, UNA por proyecto (parte 5d): solo la fase 1 lo toma; una fase
        // siguiente usa el de su proyecto (Proyecto::codigoFicha()) y no gasta un numero.
        $proyecto->codigo = $proyecto->idProyectoAnterior === null
            ? CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_PROYECTO, $codificacion->tomar(CorrelativoDocumento::TIPO_PROYECTO))
            : null;
        $proyecto->codigoLista = CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_LISTA, $codificacion->tomar(CorrelativoDocumento::TIPO_LISTA), $this->codigoProceso($proyecto));
        $proyecto->save(false);
    }

    /**
     * Si se cambio el proceso del proyecto y su lista todavia no se envio a
     * cotizacion (ninguna version emitida), el codigo de la lista cambia a ese
     * proceso con el MISMO numero (RD-PI-LM-005 -> RD-GR-LM-005). Una lista ya
     * enviada conserva su codigo, como el presupuesto.
     */
    private function ajustarProcesoDeLaLista(Proyecto $proyecto): void
    {
        $partes = CorrelativoDocumento::partesDe(CorrelativoDocumento::TIPO_LISTA, $proyecto->codigoLista);
        $proceso = $this->codigoProceso($proyecto);

        if ($partes === null || $partes['proceso'] === $proceso || $proyecto->ultimaAprobacionLista() !== null) {
            return;
        }

        $proyecto->updateAttributes(['codigoLista' => CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_LISTA, $partes['numero'], $proceso)]);
    }

    /**
     * Numero de proyecto (revision del 2026-11-05): el de la fase anterior si
     * es una fase siguiente; si no, el siguiente del correlativo NP. Se llama
     * dentro de la transaccion de guardar().
     */
    private function asignarNumero(Proyecto $proyecto, CodificacionService $codificacion): void
    {
        $anterior = $proyecto->idProyectoAnterior === null ? null : Proyecto::findOne((int) $proyecto->idProyectoAnterior);

        $proyecto->numeroProyecto = $anterior->numeroProyecto ?? $codificacion->tomar(CorrelativoDocumento::TIPO_NUMERO_PROYECTO);
    }

    /** Codigo de 2 letras del proceso del proyecto (PI si no tiene). */
    private function codigoProceso(Proyecto $proyecto): string
    {
        $codigo = $proyecto->idProceso === null ? null : Proceso::find()->select('codigo')->where(['id' => (int) $proyecto->idProceso])->scalar();

        return is_string($codigo) && $codigo !== '' ? $codigo : CorrelativoDocumento::PROCESO;
    }

    /**
     * @param ProyectoAreaIntervencion[] $areas
     */
    private function reemplazarAreas(Proyecto $proyecto, array $areas): void
    {
        ProyectoAreaIntervencion::deleteAll(['idProyecto' => $proyecto->id]);

        foreach ($areas as $orden => $area) {
            $area->idProyecto = $proyecto->id;
            $area->orden = $orden;
            $area->save(false);
        }

        // Olvida las areas que el modelo tenia cargadas, para que la proxima
        // lectura de $proyecto->areasIntervencion traiga las nuevas.
        unset($proyecto->areasIntervencion);
    }

    /**
     * @param ProyectoParticipante[] $equipo
     */
    private function reemplazarEquipo(Proyecto $proyecto, array $equipo): void
    {
        ProyectoParticipante::deleteAll(['idProyecto' => $proyecto->id]);

        foreach ($equipo as $orden => $participante) {
            $participante->idProyecto = $proyecto->id;
            $participante->orden = $orden;
            $participante->save(false);
        }

        unset($proyecto->participantes);
    }
}
