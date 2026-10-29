<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CorrelativoDocumento;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoAreaIntervencion;
use backend\models\ProyectoProyectista;
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

    /**
     * Lo que no se pasa se le pide al contenedor de Yii, que lo arma con la
     * fuente configurada en backend/config/main.php (los tests pasan fuentes
     * de prueba).
     */
    public function __construct(?ParametrosProyectoService $parametros = null, ?InfraestructuraProvider $infraestructura = null)
    {
        $this->parametros = $parametros ?? Yii::$container->get(ParametrosProyectoService::class);
        $this->infraestructura = $infraestructura ?? Yii::$container->get(InfraestructuraProvider::class);
    }

    /**
     * with() carga el presupuesto y el tipo de todos los proyectos de la
     * pagina en una consulta extra cada uno (el listado muestra ambos).
     */
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Proyecto::find()->with(['presupuesto', 'tipoProyecto'])->orderBy(['fechaCreacion' => SORT_DESC, 'id' => SORT_DESC]),
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
     * enlazado a el y hereda tipo, unidad solicitante y areas de
     * intervencion (se pueden cambiar). No hereda proyectistas ni items: la
     * fase nueva empieza vacia.
     */
    public function nuevo(?int $idProyectoAnterior = null): Proyecto
    {
        $proyecto = new Proyecto();
        $anterior = $idProyectoAnterior === null ? null : $this->obtener($idProyectoAnterior);

        if ($anterior !== null && $this->estaAprobado($anterior)) {
            $proyecto->idProyectoAnterior = $anterior->id;
            $proyecto->numeroFase = $anterior->numeroFase + 1;
            $proyecto->idTipoProyecto = $anterior->idTipoProyecto;
            $proyecto->unidadSolicitante = $anterior->unidadSolicitante;
            $proyecto->areas = array_map(static fn (ProyectoAreaIntervencion $area) => $area->claves(), $anterior->areasIntervencion);
        } else {
            $proyecto->numeroFase = 1;
        }

        return $proyecto;
    }

    /**
     * Deja en el modelo las areas y proyectistas ya guardados, para que el
     * formulario de edicion los muestre como seleccionados.
     */
    public function cargarSeleccion(Proyecto $proyecto): void
    {
        $proyecto->areas = array_map(static fn (ProyectoAreaIntervencion $area) => $area->claves(), $proyecto->areasIntervencion);
        $proyecto->nombresProyectista = array_map(static fn (ProyectoProyectista $persona) => $persona->nombre, $proyecto->proyectistas);
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
     * Devuelve false si el proyecto no se puede borrar porque ya tiene
     * presupuesto u otra fase que depende de el (las
     * llaves foraneas lo impiden).
     */
    public function eliminar(Proyecto $proyecto): bool
    {
        try {
            return $proyecto->delete() !== false;
        } catch (IntegrityException $e) {
            return false;
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
     * Sugerencias para el campo "Unidad solicitante": lo ya escrito en otros
     * proyectos y las facultades de los parametros. Es solo ayuda para no
     * transcribir de nuevo: el campo acepta cualquier texto.
     *
     * @return string[] ordenadas, sin repetidas (sin importar mayusculas)
     */
    public function sugerenciasUnidad(): array
    {
        $escritas = Proyecto::find()
            ->select('unidadSolicitante')
            ->distinct()
            ->andWhere(['not', ['unidadSolicitante' => null]])
            ->column();

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
     * nombres copiados; las demas se piden a la fuente.
     *
     * @return array<int, array{idEdificio: string, idBloqueArea: ?int, numeroNivel: ?string, idEspacio: ?string, texto: string, grupo: string, detalle: string}>
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

            if ($area === null) {
                try {
                    $ruta = $this->infraestructura->ruta($claves['idEdificio'], $claves['idBloqueArea'], $claves['numeroNivel'], $claves['idEspacio']);
                } catch (DbException $e) {
                    $ruta = null;
                }

                if ($ruta === null) {
                    $areas[] = $claves + ['texto' => $texto = 'Área no vigente (edificio ' . $claves['idEdificio'] . ')', 'grupo' => $texto, 'detalle' => ''];
                    continue;
                }

                $area = new ProyectoAreaIntervencion($claves + $ruta);
            }

            $areas[] = $claves + ['texto' => $area->texto(), 'grupo' => $area->grupo(), 'detalle' => $area->detalle()];
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
            ->orderBy(['p.codigo' => SORT_ASC])
            ->all();

        $opciones = [];

        foreach ($proyectos as $proyecto) {
            $monto = Yii::$app->formatter->asDecimal((float) $this->montoPresupuesto($proyecto), 2);

            $opciones[$proyecto->id] = [
                'etiqueta' => $proyecto->etiqueta() . " (Fase {$proyecto->numeroFase}, aprobado)",
                'codigo' => (string) $proyecto->codigo,
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
     * quede un proyecto guardado sin sus areas o proyectistas.
     *
     * La ubicacion anterior (facultad, edificio, redes.ProyectoBloque) no se
     * toca: queda como estaba en los proyectos registrados antes del cambio.
     */
    private function guardar(Proyecto $proyecto, array $datosFormulario): bool
    {
        if (!$proyecto->load($datosFormulario)) {
            return false;
        }

        // Un campo vacio del formulario llega como '' y no como arreglo.
        $proyecto->nombresProyectista = $this->limpiarLista($proyecto->nombresProyectista);

        foreach (['idTipoProyecto', 'idProyectoAnterior'] as $atributo) {
            if ($proyecto->$atributo === '') {
                $proyecto->$atributo = null;
            }
        }

        // validate() borra los errores anteriores: por eso va ANTES de las
        // verificaciones propias del servicio, que agregan los suyos.
        $proyecto->validate();

        $areas = $this->armarAreas($proyecto);
        $this->aplicarFase($proyecto);
        $this->validarProyectistas($proyecto);

        if ($proyecto->hasErrors()) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            if ($proyecto->isNewRecord) {
                $this->insertarConCodigo($proyecto);
            } else {
                $proyecto->save(false);
            }

            $this->reemplazarAreas($proyecto, $areas);
            $this->reemplazarProyectistas($proyecto);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Deja una lista de textos no vacios y sin repetidos, venga lo que venga
     * del formulario ('' cuando no se eligio nada, o un arreglo).
     *
     * @param mixed $valor
     * @return string[]
     */
    private function limpiarLista($valor): array
    {
        $textos = array_filter((array) $valor, static fn ($elemento) => is_string($elemento) && trim($elemento) !== '');

        return array_values(array_unique(array_map('trim', $textos)));
    }

    /**
     * Arma las areas de intervencion con lo que llego del formulario: cada
     * una debe existir y estar vigente en la fuente de infraestructura, de la
     * que se copian los nombres. Un area que el proyecto ya tenia guardada se
     * conserva tal cual (con sus nombres de entonces), aunque despues la
     * hayan dado de baja en UsfxGEO. Las repetidas se guardan una vez.
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
     * Deja los identificadores de un area como los espera la fuente ('' ->
     * null). null si no tiene edificio (fila vacia: se ignora).
     *
     * @return array{idEdificio: string, idBloqueArea: ?int, numeroNivel: ?string, idEspacio: ?string}|null
     */
    private static function normalizarArea(array $datos): ?array
    {
        $texto = static function ($valor): ?string {
            $valor = is_scalar($valor) ? trim((string) $valor) : '';

            return $valor === '' ? null : $valor;
        };

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

    private static function claveArea(array $claves): string
    {
        return implode('|', [$claves['idEdificio'], $claves['idBloqueArea'] ?? '', $claves['numeroNivel'] ?? '', $claves['idEspacio'] ?? '']);
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
    }

    /**
     * Un proyectista debe estar activo en redes.Proyectista, o ya estar
     * asignado a este proyecto (para no invalidar proyectos antiguos si se
     * desactiva o se corrige su nombre).
     */
    private function validarProyectistas(Proyecto $proyecto): void
    {
        $yaAsignados = $proyecto->isNewRecord
            ? []
            : array_map(static fn (ProyectoProyectista $persona) => $persona->nombre, $proyecto->proyectistas);

        $permitidos = array_merge((new ProyectistaService())->nombresActivos(), $yaAsignados);

        if (array_diff($proyecto->nombresProyectista, $permitidos) !== []) {
            $proyecto->addError('nombresProyectista', 'Uno de los proyectistas elegidos no está en la lista.');
        }
    }

    /**
     * Inserta el proyecto con el siguiente codigo del correlativo PT
     * (RD-PI-PT-005, ver CorrelativoDocumento::codigo()). Se llama dentro de la
     * transaccion de guardar(): si el INSERT falla, el numero tomado vuelve
     * atras con todo lo demas. El indice unico de la base impide igual
     * cualquier codigo repetido.
     */
    private function insertarConCodigo(Proyecto $proyecto): void
    {
        $numero = (new CodificacionService())->tomar(CorrelativoDocumento::TIPO_PROYECTO);
        $proyecto->codigo = CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_PROYECTO, $numero);
        $proyecto->save(false);
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

    private function reemplazarProyectistas(Proyecto $proyecto): void
    {
        ProyectoProyectista::deleteAll(['idProyecto' => $proyecto->id]);

        foreach ($proyecto->nombresProyectista as $nombre) {
            $persona = new ProyectoProyectista();
            $persona->idProyecto = $proyecto->id;
            $persona->nombre = $nombre;
            $persona->save(false);
        }

        unset($proyecto->proyectistas);
    }
}
