<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\ConfiguracionPresupuesto;
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
 *  - una fase nueva es un proyecto NUEVO enlazado a uno ya aprobado,
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
            'query' => Proyecto::find()->with(['presupuesto', 'tipoProyecto', 'proceso'])->orderBy(['fechaCreacion' => SORT_DESC, 'id' => SORT_DESC]),
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
     * un proyecto aprobado, se prepara como su FASE SIGUIENTE: queda
     * enlazado a el y hereda tipo, proceso, unidades, areas de intervencion
     * y el EQUIPO con su responsable (todo se puede cambiar). No hereda los
     * items: la lista de la fase nueva empieza vacia. Un proyecto nuevo sin
     * fase anterior trae marcado el proceso PI (ProcesoService::idPorDefecto())
     * y una fila de equipo con el rol Proyectista, marcada como responsable,
     * para elegir a la persona.
     */
    public function nuevo(?int $idProyectoAnterior = null): Proyecto
    {
        $proyecto = new Proyecto();
        $proyecto->idProceso = (new ProcesoService())->idPorDefecto();
        $anterior = $idProyectoAnterior === null ? null : $this->obtener($idProyectoAnterior);

        if ($anterior !== null && $this->estaAprobado($anterior)) {
            $proyecto->idProyectoAnterior = $anterior->id;
            $proyecto->numeroFase = $anterior->numeroFase + 1;
            $proyecto->idTipoProyecto = $anterior->idTipoProyecto;
            $proyecto->idProceso = $anterior->idProceso ?? $proyecto->idProceso;
            $proyecto->unidadDependencia = $anterior->unidadDependencia;
            $proyecto->unidadSolicitante = $anterior->unidadSolicitante;
            $proyecto->lugarEntrega = $anterior->lugarEntrega;
            $proyecto->areas = array_map(static fn (ProyectoAreaIntervencion $area) => $area->claves(), $anterior->areasIntervencion);
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
        $usos = array_filter([
            'presupuesto' => Presupuesto::find()->andWhere(['idProyecto' => $proyecto->id])->exists() ? 1 : 0,
            'fase(s) siguiente(s)' => (int) Proyecto::find()->andWhere(['idProyectoAnterior' => $proyecto->id])->count(),
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

    public function estaAprobado(Proyecto $proyecto): bool
    {
        return ($proyecto->presupuesto->estado ?? null) === Presupuesto::ESTADO_APROBADO;
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
     * Lo que imprime la FICHA TECNICA DEL PROYECTO: los datos del proyecto
     * (van en la vista con el modelo), sus fechas y los documentos de su
     * carpeta economica.
     *
     * Desde la revision del 2026-11-05 (punto 2) es un DOCUMENTO normado que
     * se aprueba: cabecera del lineamiento y control de elaboracion. Hasta que
     * existan las fases dentro del proyecto (parte 5), queda APROBADA cuando
     * se aprueba el proyecto, es decir su presupuesto (el punto de control
     * "Revisar y aprobar diseño"): 'aprobada', su fecha y las firmas copiadas
     * del presupuesto aprobado (Presupuesto::firmaDeControl()); mientras no,
     * borrador con las firmas de la configuracion de hoy.
     *
     * 'variasFases': el proyecto es fase 2 o siguiente, o ya tiene una fase
     * siguiente. Con fase unica la ficha no muestra "Fase" (punto 3).
     *
     * @return array{
     *   fechas: array{creacion: string|null, revision: string|null, aprobacion: string|null, ultimaModificacion: string|null, ultimoUsuario: string|null},
     *   monto: float|null,
     *   especificaciones: EspecificacionEmision[],
     *   solicitudes: SolicitudCotizacion[],
     *   lista: array{version: string, borrador: bool, aprobacion: \backend\models\ListaMaterialesAprobacion|null, fechaCreacion: string},
     *   aprobada: bool,
     *   fechaAprobacion: string,
     *   elaborado: array{prefijo: string, nombre: string, cargo: string}|null,
     *   aprobado: array{prefijo: string, nombre: string, cargo: string}|null,
     *   variasFases: bool
     * }
     */
    public function datosFicha(Proyecto $proyecto): array
    {
        $presupuesto = $proyecto->presupuesto;
        $configuracion = ConfiguracionPresupuesto::obtener();
        $aprobada = $presupuesto !== null && $presupuesto->estado === Presupuesto::ESTADO_APROBADO;

        return [
            'fechas' => $this->historial->fechas($proyecto),
            'monto' => $this->montoPresupuesto($proyecto),
            'especificaciones' => (new EspecificacionEmisionService())->vigentesDelProyecto($proyecto),
            'solicitudes' => (new SolicitudCotizacionService())->listar($proyecto),
            'lista' => (new ListaItemsService())->documento($proyecto),
            'aprobada' => $aprobada,
            'fechaAprobacion' => $aprobada ? $presupuesto->fechaAprobacionFormateada() : '',
            'elaborado' => $presupuesto !== null ? $presupuesto->firmaDeControl(1, $configuracion) : $configuracion->firma(1),
            'aprobado' => $presupuesto !== null ? $presupuesto->firmaDeControl(2, $configuracion) : $configuracion->firma(2),
            'variasFases' => $proyecto->tieneVariasFases(),
        ];
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
     * @return array<int, array{id: string, nombre: string, usos: int}> ordenados por nombre
     */
    public function edificiosParaFormulario(): array
    {
        $usos = ProyectoAreaIntervencion::find()
            ->select(['total' => 'COUNT(DISTINCT idProyecto)', 'idEdificio'])
            ->groupBy('idEdificio')
            ->indexBy('idEdificio')
            ->column();

        $edificios = [];

        foreach ($this->infraestructura->edificios() as $id => $nombre) {
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
     * @return array{edificios: array<int, array{idEdificio: string, edificio: string}>, ambientes: array<int, array<string, mixed>>, hayMas: bool}
     */
    public function buscarAreas(string $texto): array
    {
        if (mb_strlen(trim($texto), 'UTF-8') < 2) {
            return ['edificios' => [], 'ambientes' => [], 'hayMas' => false];
        }

        // Se pide uno mas de los que se muestran, solo para saber si hay mas.
        $ambientes = $this->infraestructura->buscarAmbientes($texto, self::MAX_AMBIENTES_BUSCADOS + 1);

        return [
            'edificios' => $this->infraestructura->buscarEdificios($texto),
            'ambientes' => array_map(static function (array $fila): array {
                $fila['texto'] = (new ProyectoAreaIntervencion($fila))->texto();

                return $fila;
            }, array_slice($ambientes, 0, self::MAX_AMBIENTES_BUSCADOS)),
            'hayMas' => count($ambientes) > self::MAX_AMBIENTES_BUSCADOS,
        ];
    }

    /**
     * Proyectos que pueden ser "fase anterior" de otro: SOLO los que tienen
     * presupuesto aprobado. Para cada uno devuelve el texto de la opcion y
     * el resumen que se muestra en "Proyecto vinculado".
     *
     * @param int|null $excluirId el propio proyecto, que no puede ser su fase anterior
     * @return array<int, array{etiqueta: string, codigo: string, resumen: string}> indexado por id
     */
    public function proyectosAprobados(?int $excluirId = null): array
    {
        $proyectos = Proyecto::find()
            ->alias('p')
            ->innerJoin(['pr' => Presupuesto::tableName()], 'pr.idProyecto = p.id')
            ->andWhere(['pr.estado' => Presupuesto::ESTADO_APROBADO])
            ->andFilterWhere(['<>', 'p.id', $excluirId])
            ->orderBy(['p.numeroProyecto' => SORT_ASC, 'p.numeroFase' => SORT_ASC])
            ->all();

        $opciones = [];

        foreach ($proyectos as $proyecto) {
            $monto = Yii::$app->formatter->asDecimal((float) $this->montoPresupuesto($proyecto), 2);

            $opciones[$proyecto->id] = [
                'etiqueta' => $proyecto->etiqueta() . " (Fase {$proyecto->numeroFase}, aprobado)",
                'codigo' => 'Proyecto ' . $proyecto->numeroTexto(),
                'resumen' => "Fase {$proyecto->numeroFase} — Aprobado — Bs. $monto",
            ];
        }

        return $opciones;
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
                // Se cambio (o quito) la fase anterior: el numero sigue a la cadena de fases.
                if ((string) $proyecto->getOldAttribute('idProyectoAnterior') !== (string) $proyecto->idProyectoAnterior) {
                    $this->asignarNumero($proyecto, new CodificacionService());
                }
                $proyecto->save(false);
                $this->ajustarProcesoDeLaLista($proyecto);
            }

            $this->reemplazarAreas($proyecto, $areas);
            $this->reemplazarEquipo($proyecto, $equipo);
            $this->anotarEnHistorial($proyecto, $antes);

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
     * Calcula numeroFase a partir del proyecto de la fase anterior. Solo se
     * acepta como fase anterior un proyecto con presupuesto APROBADO.
     */
    private function aplicarFase(Proyecto $proyecto): void
    {
        if ($proyecto->idProyectoAnterior === null) {
            $proyecto->numeroFase = 1;

            return;
        }

        $anterior = $this->obtener((int) $proyecto->idProyectoAnterior);

        if ($anterior === null || (!$proyecto->isNewRecord && $anterior->id === $proyecto->id) || !$this->estaAprobado($anterior)) {
            $proyecto->addError('idProyectoAnterior', 'Solo un proyecto con presupuesto aprobado puede ser la fase anterior.');

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
        $proyecto->codigo = CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_PROYECTO, $codificacion->tomar(CorrelativoDocumento::TIPO_PROYECTO));
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
