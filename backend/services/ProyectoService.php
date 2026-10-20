<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoBloque;
use backend\models\ProyectoProyectista;
use RuntimeException;
use Throwable;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\IntegrityException;

/**
 * Capa de Servicio para Proyecto ("Gestionar Proyecto": solo los datos
 * basicos del proyecto; plantilla, lista de items, especificaciones,
 * cotizacion y presupuesto son casos de uso separados que usan su id).
 *
 * Reglas de negocio que viven aqui:
 *  - el codigo RD-PI-PT-NN se genera al crear y no se repite,
 *  - facultad, edificio y bloques deben existir en los parametros (el tipo
 *    lo valida el modelo contra redes.TipoProyecto),
 *  - una fase nueva es un proyecto NUEVO enlazado a uno ya aprobado,
 *  - un proyecto con presupuesto aprobado o cancelado no se modifica.
 */
class ProyectoService
{
    private const INTENTOS_CODIGO = 5;

    private ParametrosProyectoService $parametros;

    /**
     * Si no se pasa el servicio de parametros, se le pide al contenedor de
     * Yii, que lo arma con la fuente configurada en backend/config/main.php.
     */
    public function __construct(?ParametrosProyectoService $parametros = null)
    {
        $this->parametros = $parametros ?? Yii::$container->get(ParametrosProyectoService::class);
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
     * enlazado a el y hereda tipo, facultad y edificio (se pueden cambiar).
     * No hereda bloques, proyectistas ni items: la fase nueva empieza vacia.
     */
    public function nuevo(?int $idProyectoAnterior = null): Proyecto
    {
        $proyecto = new Proyecto();
        $anterior = $idProyectoAnterior === null ? null : $this->obtener($idProyectoAnterior);

        if ($anterior !== null && $this->estaAprobado($anterior)) {
            $proyecto->idProyectoAnterior = $anterior->id;
            $proyecto->numeroFase = $anterior->numeroFase + 1;
            $proyecto->idTipoProyecto = $anterior->idTipoProyecto;
            $proyecto->codigoFacultad = $anterior->codigoFacultad;
            $proyecto->codigoEdificio = $anterior->codigoEdificio;
        } else {
            $proyecto->numeroFase = 1;
        }

        return $proyecto;
    }

    /**
     * Deja en el modelo los bloques y proyectistas ya guardados, para que el
     * formulario de edicion los muestre como seleccionados.
     */
    public function cargarSeleccion(Proyecto $proyecto): void
    {
        $proyecto->codigosBloque = array_map(static fn (ProyectoBloque $bloque) => $bloque->codigo, $proyecto->bloques);
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
     * presupuesto, computos metricos u otra fase que depende de el (las
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
            ['titulo' => 'Lista de ítems', 'estado' => $estadoLista, 'url' => ['/lista-items/index', 'id' => $proyecto->id]],
            // Las especificaciones se enlazan item por item EN la lista (son
            // opcionales): el paso avanza junto con la lista.
            ['titulo' => 'Especificaciones', 'estado' => $estadoLista, 'url' => ['/lista-items/index', 'id' => $proyecto->id]],
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
     * quede un proyecto guardado sin sus bloques o proyectistas.
     */
    private function guardar(Proyecto $proyecto, array $datosFormulario): bool
    {
        if (!$proyecto->load($datosFormulario)) {
            return false;
        }

        // Un campo vacio del formulario llega como '' y no como arreglo.
        $proyecto->codigosBloque = $this->limpiarLista($proyecto->codigosBloque);
        $proyecto->nombresProyectista = $this->limpiarLista($proyecto->nombresProyectista);

        foreach (['idTipoProyecto', 'codigoFacultad', 'codigoEdificio', 'idProyectoAnterior'] as $atributo) {
            if ($proyecto->$atributo === '') {
                $proyecto->$atributo = null;
            }
        }

        // validate() borra los errores anteriores: por eso va ANTES de las
        // verificaciones propias del servicio, que agregan los suyos.
        $proyecto->validate();

        $bloques = $this->aplicarParametros($proyecto);
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

            $this->reemplazarBloques($proyecto, $bloques);
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
     * Verifica contra los parametros que facultad, edificio y bloques
     * existan (y que el edificio sea de esa facultad y los bloques de ese
     * edificio), y copia los NOMBRES de facultad y edificio al proyecto.
     *
     * @return array<string, string> [codigo => nombre] de los bloques elegidos
     */
    private function aplicarParametros(Proyecto $proyecto): array
    {
        $proyecto->facultad = $this->parametros->nombreFacultad($proyecto->codigoFacultad);
        $proyecto->edificio = $this->parametros->nombreEdificio($proyecto->codigoFacultad, $proyecto->codigoEdificio);

        if ($proyecto->codigoFacultad !== null && $proyecto->facultad === null) {
            $proyecto->addError('codigoFacultad', 'La facultad elegida no existe.');
        }

        if ($proyecto->codigoEdificio !== null && $proyecto->edificio === null) {
            $proyecto->addError('codigoEdificio', 'El edificio elegido no pertenece a esa facultad.');
        }

        $disponibles = $this->parametros->opcionesBloque($proyecto->codigoFacultad, $proyecto->codigoEdificio);
        $elegidos = [];

        foreach ($proyecto->codigosBloque as $codigo) {
            if (!isset($disponibles[$codigo])) {
                $proyecto->addError('codigosBloque', 'Uno de los bloques elegidos no pertenece al edificio seleccionado.');
                break;
            }

            $elegidos[$codigo] = $disponibles[$codigo];
        }

        return $elegidos;
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
     * Un proyectista debe estar en la lista de los parametros, o ya estar
     * asignado a este proyecto (para no invalidar proyectos antiguos si la
     * lista cambia).
     */
    private function validarProyectistas(Proyecto $proyecto): void
    {
        $yaAsignados = $proyecto->isNewRecord
            ? []
            : array_map(static fn (ProyectoProyectista $persona) => $persona->nombre, $proyecto->proyectistas);

        $permitidos = array_merge($this->parametros->proyectistas(), $yaAsignados);

        if (array_diff($proyecto->nombresProyectista, $permitidos) !== []) {
            $proyecto->addError('nombresProyectista', 'Uno de los proyectistas elegidos no está en la lista.');
        }
    }

    /**
     * Inserta el proyecto con el siguiente codigo libre. Si justo otra
     * persona guardo un proyecto con ese mismo codigo, el indice unico de la
     * base rechaza el INSERT (IntegrityException) y se reintenta con el
     * siguiente numero.
     */
    private function insertarConCodigo(Proyecto $proyecto): void
    {
        for ($intento = 1; $intento <= self::INTENTOS_CODIGO; $intento++) {
            $proyecto->codigo = $this->siguienteCodigo();

            try {
                $proyecto->save(false);

                return;
            } catch (IntegrityException $e) {
                if ($intento === self::INTENTOS_CODIGO) {
                    throw new RuntimeException('No se pudo generar un código de proyecto único.', 0, $e);
                }
            }
        }
    }

    /**
     * Prefijo (params.php) + correlativo global: el mayor numero ya usado
     * mas uno, con al menos dos digitos (RD-PI-PT-08; despues de 99, -100).
     */
    private function siguienteCodigo(): string
    {
        $prefijo = (string) Yii::$app->params['prefijoCodigoProyecto'];
        $patron = '/^' . preg_quote($prefijo, '/') . '(\d+)$/';
        $mayor = 0;

        $codigos = Proyecto::find()->select('codigo')->andWhere(['not', ['codigo' => null]])->column();

        foreach ($codigos as $codigo) {
            if (preg_match($patron, (string) $codigo, $partes) === 1) {
                $mayor = max($mayor, (int) $partes[1]);
            }
        }

        return $prefijo . str_pad((string) ($mayor + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, string> $bloques [codigo => nombre]
     */
    private function reemplazarBloques(Proyecto $proyecto, array $bloques): void
    {
        ProyectoBloque::deleteAll(['idProyecto' => $proyecto->id]);

        foreach ($bloques as $codigo => $nombre) {
            $bloque = new ProyectoBloque();
            $bloque->idProyecto = $proyecto->id;
            $bloque->codigo = (string) $codigo;
            $bloque->nombre = $nombre;
            $bloque->save(false);
        }

        // Olvida los bloques que el modelo tenia cargados, para que la
        // proxima lectura de $proyecto->bloques traiga los nuevos.
        unset($proyecto->bloques);
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
