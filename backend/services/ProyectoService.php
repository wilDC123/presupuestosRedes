<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CorrelativoDocumento;
use backend\models\EspecificacionEmision;
use backend\models\HistorialProyecto;
use backend\models\Presupuesto;
use backend\models\Proceso;
use backend\models\Proyecto;
use backend\models\ProyectoAreaIntervencion;
use backend\models\ProyectoParticipante;
use backend\services\contracts\InfraestructuraProvider;
use Throwable;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\IntegrityException;

/**
 * Capa de Servicio para Proyecto ("Gestionar Proyecto"): crear, editar y
 * eliminar proyectos y sus fases, con sus datos, equipo y numero. Plantilla,
 * lista de items, especificaciones, cotizacion y presupuesto son casos de uso
 * separados que usan su id. Tambien separados: el area de intervencion
 * (AreaIntervencionService), la ficha tecnica y los montos
 * (FichaTecnicaService) y la barra de pasos y la franja de fases
 * (NavegacionProyectoService).
 *
 * Reglas de negocio que viven aqui:
 *  - el codigo RD-PI-PT-NNN se genera al crear y no se repite,
 *  - la unidad solicitante es texto libre (sin catalogo: se sugiere lo ya
 *    escrito; el tipo lo valida el modelo contra redes.TipoProyecto),
 *  - una fase nueva es un proyecto NUEVO enlazado a la ultima fase (varias
 *    fases pueden estar en elaboracion a la vez); los datos del proyecto se
 *    escriben en la fase 1 y pasan a las demas,
 *  - un proyecto con presupuesto aprobado o cancelado no se modifica
 *    (Proyecto::esEditable()).
 */
class ProyectoService
{
    private ParametrosProyectoService $parametros;
    private AreaIntervencionService $areas;
    private HistorialService $historial;

    /**
     * Lo que no se pasa se le pide al contenedor de Yii, que lo arma con la
     * fuente configurada en backend/config/main.php (los tests pasan fuentes
     * de prueba). La fuente de infraestructura la usa el area de intervencion.
     */
    public function __construct(?ParametrosProyectoService $parametros = null, ?InfraestructuraProvider $infraestructura = null)
    {
        $this->parametros = $parametros ?? Yii::$container->get(ParametrosProyectoService::class);
        $this->areas = new AreaIntervencionService($infraestructura);
        $this->historial = new HistorialService();
    }

    /**
     * with() carga el presupuesto, el tipo y el proceso de todos los
     * proyectos de la pagina en una consulta extra cada uno (el listado
     * muestra los tres).
     */
    public function listar(): ActiveDataProvider
    {
        $listado = new ActiveDataProvider([
            // Por proyecto (el mas nuevo arriba) y, dentro, sus fases en orden: cada proyecto con sus fases juntas.
            // Las ordenes de cambio (parte 6) van debajo de su fase: en SQL Server NULL (la fase) va primero.
            'query' => Proyecto::find()->with(['presupuesto', 'tipoProyecto', 'proceso'])
                ->orderBy(['numeroProyecto' => SORT_DESC, 'numeroFase' => SORT_ASC, 'numeroOrdenCambio' => SORT_ASC, 'id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        // La columna "Fase" pregunta tieneVariasFases() en cada fila: se calcula para toda la pagina en una
        // consulta. getModels() carga la pagina una vez; el GridView usa esos mismos modelos.
        Proyecto::precargarVariasFases($listado->getModels());

        return $listado;
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
        if (!$proyecto->esEditable()) {
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
                return $fase->esEditable() && !$this->datosComunesFijos($fase) ? $fase : null;
            }
        }

        return null;
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
     * (Proyecto::esEditable()).
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

        $areas = $this->areas->armarAreas($proyecto);
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

            $this->areas->reemplazarAreas($proyecto, $areas);
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
