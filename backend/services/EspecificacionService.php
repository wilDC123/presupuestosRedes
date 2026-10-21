<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\ArchivoSubido;
use backend\components\EspecificacionMetadataExtractor;
use backend\models\CotizacionDetalle;
use backend\models\Especificacion;
use backend\models\EspecificacionCaracteristica;
use backend\models\EspecificacionEmision;
use backend\models\EspecificacionVersion;
use backend\models\ProyectoItem;
use Throwable;
use Yii;
use yii\db\Expression;
use yii\web\UploadedFile;

/**
 * Capa de Servicio para la LIBRERIA de especificaciones tecnicas.
 *
 * Reglas de negocio que viven aqui:
 *  - la especificacion no pertenece a un item ni a un proyecto: se clasifica
 *    por categoria y se enlaza a items de la lista de un proyecto,
 *  - los selectores solo ofrecen especificaciones ACTIVAS (una desactivada
 *    sigue mostrandose donde ya estaba enlazada),
 *  - una especificacion en uso (enlazada o emitida) no se elimina: se desactiva,
 *  - el contenido tecnico vive en VERSIONES; una version ya emitida no se
 *    modifica, cambiarla crea la siguiente (ver guardarVersion()).
 *
 * Ademas: donde y con que nombre se guarda el documento adjunto, y la
 * extraccion automatica de metadatos desde PDF/DOCX.
 */
class EspecificacionService
{
    /**
     * Subcarpeta de backend/uploads para los adjuntos (ver ArchivoSubido).
     */
    private const CARPETA_ADJUNTOS = 'especificaciones';

    /**
     * Maximo de fichas que devuelve buscar(): si hay mas, conviene afinar.
     */
    public const LIMITE_BUSQUEDA = 30;

    /**
     * Nombre del grupo de fichas sugeridas en el selector de la lista.
     */
    public const GRUPO_SUGERIDAS = 'Sugeridas para este ítem';

    /**
     * selector() de una pantalla que no ofrece elegir especificaciones (ej.
     * la lista ya enviada a cotizacion): las mismas claves, sin datos.
     */
    public const SELECTOR_VACIO = ['grupos' => [], 'urls' => [], 'subcatalogos' => [], 'sugeridasPorItem' => []];

    /**
     * Valor del filtro de categoria para "Sin categoria" (no es el id de
     * ninguna: los ids empiezan en 1).
     */
    public const SIN_CATEGORIA = '0';
    private const EXTENSIONES_EXTRACCION = ['pdf', 'docx'];

    /**
     * Libreria filtrada, cada especificacion con su cantidad de usos (items
     * de proyecto + lineas de cotizacion). Los conteos se traen en UNA
     * consulta agrupada por tabla, no una consulta por especificacion.
     *
     * Filtros (todos opcionales): 'categoria' (id, o SIN_CATEGORIA), 'buscar' (texto en el
     * titulo, el modelo sugerido o las caracteristicas de la version
     * actual, ej. "LSZH"), 'activo' ('1' / '0').
     *
     * @param array{categoria?: string, buscar?: string, activo?: string} $filtros
     * @return array<int, array{especificacion: Especificacion, usos: int}>
     */
    public function listar(array $filtros = []): array
    {
        $query = Especificacion::find()
            ->alias('e')
            ->with(['categoria', 'subcatalogoItem', 'versionActual'])
            ->orderBy(['e.titulo' => SORT_ASC, 'e.id' => SORT_ASC]);

        $categoria = trim((string) ($filtros['categoria'] ?? ''));
        if ($categoria === self::SIN_CATEGORIA) {
            // Las importadas del banco quedan sin categoria hasta asignarla.
            $query->andWhere(['e.idCategoria' => null]);
        } elseif (ctype_digit($categoria)) {
            $query->andWhere(['e.idCategoria' => (int) $categoria]);
        }

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            // Solo en la version ACTUAL: buscar "LSZH" no debe traer una ficha
            // que lo tenia en la v1 y ya no lo tiene.
            $versionActual = EspecificacionVersion::find()
                ->alias('v')
                ->select('v.id')
                ->where('v.idEspecificacion = e.id')
                ->andWhere('v.numero = (SELECT MAX(v2.numero) FROM ' . EspecificacionVersion::tableName() . ' v2 WHERE v2.idEspecificacion = e.id)');

            $query->andWhere(['or',
                ['like', 'e.titulo', $buscar],
                ['exists', (clone $versionActual)->andWhere(['like', 'v.modeloSugerido', $buscar])],
                ['exists', EspecificacionCaracteristica::find()
                    ->alias('c')
                    ->select('c.id')
                    ->andWhere(['c.idVersion' => $versionActual])
                    ->andWhere(['or', ['like', 'c.nombre', $buscar], ['like', 'c.valor', $buscar]])],
            ]);
        }

        $activo = (string) ($filtros['activo'] ?? '');
        if ($activo === '1' || $activo === '0') {
            $query->andWhere(['e.activo' => $activo === '1']);
        }

        $usos = $this->conteoDeUsos();

        return array_map(static fn (Especificacion $especificacion) => [
            'especificacion' => $especificacion,
            'usos' => $usos[$especificacion->id] ?? 0,
        ], $query->all());
    }

    /**
     * Opciones para los selectores de especificacion (lista del proyecto,
     * lineas de cotizacion), en UNA consulta para toda la pantalla:
     *
     *  - grupos: [idCategoria => ['nombre' => ..., 'opciones' => [id => titulo]]]
     *    en el orden de las categorias ("Sin categoría" al final). La vista
     *    lo convierte en <optgroup> y pone primero el de la categoria del item.
     *  - urls: [id => enlace al documento] para el enlace "ver documento".
     *  - subcatalogos: [id => idSubcatalogo] de las fichas con item de
     *    referencia (para saber cuales ya lo tienen).
     *  - sugeridasPorItem: [idSubcatalogo => [id, ...]]: las fichas que se
     *    sugieren para cada item del subcatalogo (REGLA: las que lo tienen
     *    como item de referencia). La usan opcionesPorItem() y el JS de la
     *    lista para las filas nuevas.
     *
     * Cada opcion dice el titulo, el modelo sugerido de la version actual (si
     * el titulo no lo trae ya) y la version: "Switch de 16 puertos — Mod. SG2218 (v2)".
     *
     * Solo especificaciones ACTIVAS, mas las de $incluirIds aunque esten
     * desactivadas: son las que ya estan enlazadas en la pantalla y no deben
     * perderse al volver a guardar.
     *
     * @param int[] $incluirIds
     * @return array{grupos: array<int, array{nombre: string, opciones: array<int, string>}>, urls: array<int, string>, subcatalogos: array<int, int>, sugeridasPorItem: array<int, int[]>}
     */
    public function selector(array $incluirIds = []): array
    {
        $query = Especificacion::find()->with(['categoria', 'versionActual'])->andWhere(['activo' => true]);
        $incluirIds = array_values(array_filter(array_map('intval', $incluirIds)));

        if ($incluirIds !== []) {
            $query->orWhere(['id' => $incluirIds]);
        }

        $especificaciones = $query->all();

        usort($especificaciones, static fn (Especificacion $a, Especificacion $b) =>
            [$a->categoria->orden ?? PHP_INT_MAX, $a->nombre()] <=> [$b->categoria->orden ?? PHP_INT_MAX, $b->nombre()]);

        $grupos = [];
        $urls = [];
        $subcatalogos = [];
        $sugeridasPorItem = [];

        foreach ($especificaciones as $especificacion) {
            $idCategoria = (int) $especificacion->idCategoria;
            $grupos[$idCategoria]['nombre'] ??= $especificacion->categoria->nombre ?? 'Sin categoría';
            $grupos[$idCategoria]['opciones'][$especificacion->id] = $this->textoOpcion($especificacion);

            $url = $especificacion->urlDocumento();
            if ($url !== null) {
                $urls[$especificacion->id] = $url;
            }

            if ($especificacion->idSubcatalogo !== null) {
                $subcatalogos[$especificacion->id] = (int) $especificacion->idSubcatalogo;
                $sugeridasPorItem[(int) $especificacion->idSubcatalogo][] = (int) $especificacion->id;
            }
        }

        return ['grupos' => $grupos, 'urls' => $urls, 'subcatalogos' => $subcatalogos, 'sugeridasPorItem' => $sugeridasPorItem];
    }

    /**
     * Opciones del selector de CADA fila de la lista de items, ya ordenadas:
     *  1. "Sugeridas para este ítem" (ver sugeridasPorItem en selector()),
     *     que se MUEVEN a ese grupo: cada ficha aparece una sola vez,
     *  2. el grupo de la categoria del item,
     *  3. las demas categorias.
     *
     * Devuelve un mapa con clave "idCategoria|idSubcatalogo" (una entrada
     * por combinacion, no por fila) mas la clave '' para la fila modelo de
     * las filas nuevas (sin sugeridas; ahi las ordena el JS). Cada entrada:
     *  - opciones: [nombre del grupo => [id => texto]] para Html::dropDownList(),
     *  - grupos: [nombre del grupo => atributos del <optgroup>].
     *
     * @param array $selector resultado de selector()
     * @param ProyectoItem[] $items
     * @return array<string, array{opciones: array<string, array<int, string>>, grupos: array<string, array<string, int|string>>}>
     */
    public function opcionesPorItem(array $selector, array $items): array
    {
        $mapa = ['' => $this->opcionesDeFila($selector, null, null)];

        foreach ($items as $item) {
            $clave = $item->idCategoria . '|' . $item->idSubcatalogo;
            $mapa[$clave] ??= $this->opcionesDeFila(
                $selector,
                $item->idCategoria !== null ? (int) $item->idCategoria : null,
                $item->idSubcatalogo !== null ? (int) $item->idSubcatalogo : null
            );
        }

        return $mapa;
    }

    /**
     * Ver opcionesPorItem().
     *
     * @return array{opciones: array<string, array<int, string>>, grupos: array<string, array<string, int|string>>}
     */
    private function opcionesDeFila(array $selector, ?int $idCategoria, ?int $idSubcatalogo): array
    {
        $idsSugeridas = array_flip($idSubcatalogo !== null ? ($selector['sugeridasPorItem'][$idSubcatalogo] ?? []) : []);
        $sugeridas = [];
        $primero = [];
        $resto = [];
        $grupos = [];

        foreach ($selector['grupos'] as $idGrupo => $grupo) {
            $opciones = array_diff_key($grupo['opciones'], $idsSugeridas);
            $sugeridas += array_intersect_key($grupo['opciones'], $idsSugeridas);

            if ($opciones === []) {
                continue;
            }

            $grupos[$grupo['nombre']] = ['data-categoria' => $idGrupo];

            if ($idCategoria !== null && (int) $idGrupo === $idCategoria) {
                $primero[$grupo['nombre']] = $opciones;
            } else {
                $resto[$grupo['nombre']] = $opciones;
            }
        }

        if ($sugeridas !== []) {
            $primero = [self::GRUPO_SUGERIDAS => $sugeridas] + $primero;
            $grupos[self::GRUPO_SUGERIDAS] = ['data-sugeridas' => 1];
        }

        return ['opciones' => $primero + $resto, 'grupos' => $grupos];
    }

    /**
     * Texto de una opcion del selector. Muchas fichas tienen titulos casi
     * iguales ("SWITCH 24 PUERTOS"): el modelo sugerido es lo que las
     * distingue. Se omite si el titulo ya lo contiene (pasa con varias
     * fichas importadas del banco de .docx).
     */
    private function textoOpcion(Especificacion $especificacion): string
    {
        $texto = $especificacion->nombre();
        $version = $especificacion->versionActual;
        $modelo = trim((string) ($version->modeloSugerido ?? ''));

        if ($modelo !== '' && mb_stripos($texto, $modelo, 0, 'UTF-8') === false) {
            $texto .= ' — Mod. ' . $modelo;
        }

        return $texto
            . ($version !== null ? ' (' . $version->etiqueta() . ')' : '')
            . ($especificacion->activo ? '' : ' — inactiva');
    }

    /**
     * Busqueda de la ventana "Ver detalle" de la lista de items: fichas
     * ACTIVAS que contienen TODAS las palabras, en cualquier orden (misma
     * regla que el buscador de SIGMA del subcatalogo), en el titulo, el
     * modelo sugerido o las caracteristicas (nombre o valor) de su version
     * actual. Asi "24 poe" encuentra un switch PoE aunque el titulo no lo diga.
     *
     * COLLATE Modern_Spanish_CI_AI: la base es Modern_Spanish_CI_AS, que
     * distingue tildes ("camara" no encontraria "Cámara"). AI = accent
     * insensitive, solo para esta comparacion.
     *
     * Devuelve como maximo LIMITE_BUSQUEDA fichas:
     * [['id' => 14, 'texto' => 'SWITCH ... (v1)', 'conReferencia' => true], ...]
     * (conReferencia: si ya tiene item de referencia; la ventana ofrece
     * "Recordar como sugerida" solo en las que no)
     *
     * @return array<int, array{id: int, texto: string, conReferencia: bool}>
     */
    public function buscar(string $texto): array
    {
        $palabras = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY);

        if ($palabras === false || $palabras === []) {
            return [];
        }

        $query = Especificacion::find()
            ->alias('e')
            ->with(['versionActual'])
            ->andWhere(['e.activo' => true]);

        // Relevancia: cada palabra suma 2 si esta en el titulo y 1 si esta en
        // el modelo sugerido. Las fichas que solo coinciden por sus
        // caracteristicas (ej. una antena que menciona PoE) quedan al final.
        $puntaje = [];
        $paramsPuntaje = [];

        foreach ($palabras as $i => $palabra) {
            // Los comodines de LIKE escritos por el usuario se buscan tal cual.
            $patron = '%' . strtr($palabra, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';
            // Un parametro por cada uso: el driver de SQL Server no deja
            // repetir el mismo nombre de parametro en una consulta.
            $p = array_map(static fn (int $n) => ":p{$i}_{$n}", range(1, 6));

            $puntaje[] = "CASE WHEN e.titulo COLLATE Modern_Spanish_CI_AI LIKE {$p[4]} THEN 2 ELSE 0 END"
                . " + CASE WHEN EXISTS (SELECT 1 FROM redes.EspecificacionVersion vm WHERE vm.idEspecificacion = e.id"
                . " AND vm.numero = (SELECT MAX(vm2.numero) FROM redes.EspecificacionVersion vm2 WHERE vm2.idEspecificacion = e.id)"
                . " AND vm.modeloSugerido COLLATE Modern_Spanish_CI_AI LIKE {$p[5]}) THEN 1 ELSE 0 END";
            $paramsPuntaje[$p[4]] = $patron;
            $paramsPuntaje[$p[5]] = $patron;
            $p = array_slice($p, 0, 4);

            $query->andWhere(
                "(e.titulo COLLATE Modern_Spanish_CI_AI LIKE {$p[0]}
                  OR EXISTS (
                      SELECT 1 FROM redes.EspecificacionVersion v
                      WHERE v.idEspecificacion = e.id
                        AND v.numero = (SELECT MAX(v2.numero) FROM redes.EspecificacionVersion v2 WHERE v2.idEspecificacion = e.id)
                        AND (v.modeloSugerido COLLATE Modern_Spanish_CI_AI LIKE {$p[1]}
                             OR EXISTS (
                                 SELECT 1 FROM redes.EspecificacionCaracteristica c
                                 WHERE c.idVersion = v.id
                                   AND (c.nombre COLLATE Modern_Spanish_CI_AI LIKE {$p[2]}
                                        OR c.valor COLLATE Modern_Spanish_CI_AI LIKE {$p[3]})))))",
                array_fill_keys($p, $patron)
            );
        }

        $fichas = $query
            ->orderBy([
                // Expression: SQL escrito a mano dentro del ORDER BY, con sus parametros.
                'puntaje' => new Expression('(' . implode(' + ', $puntaje) . ') DESC', $paramsPuntaje),
                'e.titulo' => SORT_ASC,
            ])
            ->limit(self::LIMITE_BUSQUEDA)
            ->all();

        return array_map(fn (Especificacion $especificacion) => [
            'id' => (int) $especificacion->id,
            'texto' => $this->textoOpcion($especificacion),
            'conReferencia' => $especificacion->idSubcatalogo !== null,
        ], $fichas);
    }

    /**
     * "Recordar como sugerida para este ítem" (ventana "Ver detalle" de la
     * lista): pone $idSubcatalogo como item de referencia de la ficha, SOLO
     * si todavia no tiene uno (nunca pisa uno ya puesto). No crea version: el
     * item de referencia es dato de la ficha, no del contenido tecnico.
     */
    public function recordarItemDeReferencia(int $idEspecificacion, int $idSubcatalogo): void
    {
        Especificacion::updateAll(
            ['idSubcatalogo' => $idSubcatalogo],
            ['id' => $idEspecificacion, 'idSubcatalogo' => null]
        );
    }

    /**
     * Ids de las especificaciones ACTIVAS: las unicas que se pueden enlazar
     * de nuevo (una desactivada solo se conserva donde ya estaba).
     *
     * @return array<int, true>
     */
    public function idsActivas(): array
    {
        $ids = Especificacion::find()->select('id')->andWhere(['activo' => true])->column();

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * Donde esta enlazada la especificacion.
     *
     * @return array{proyectoItems: \backend\models\ProyectoItem[], cotizacionDetalles: \backend\models\CotizacionDetalle[]}
     */
    public function usos(Especificacion $especificacion): array
    {
        return [
            'proyectoItems' => $especificacion->getProyectoItems()
                ->with(['proyecto', 'subcatalogoItem'])
                ->orderBy(['idProyecto' => SORT_DESC, 'orden' => SORT_ASC])
                ->all(),
            'cotizacionDetalles' => $especificacion->getCotizacionDetalles()
                ->with(['cotizacion.proveedor', 'subcatalogoItem'])
                ->orderBy(['idCotizacion' => SORT_DESC])
                ->all(),
        ];
    }

    public function obtener(int $id): ?Especificacion
    {
        return Especificacion::findOne($id);
    }

    /**
     * Una version por su id (no por su numero dentro de la ficha, ver
     * version()). La usa la descarga del documento adjunto.
     */
    public function obtenerVersion(int $id): ?EspecificacionVersion
    {
        return EspecificacionVersion::findOne($id);
    }

    /**
     * Lo que muestra el formulario de una ficha (crear o editar), con los
     * valores guardados:
     *  - version: la version actual, o una vacia si la ficha es nueva (el
     *    formulario usa sus campos: modelo sugerido, garantia, adjunto),
     *  - filas: sus caracteristicas,
     *  - emisiones: en cuantos formularios NET se uso la version actual (si
     *    es mas de 0, guardar cambios creara la version siguiente).
     *
     * @return array{version: EspecificacionVersion, filas: array<int, array{nombre: ?string, valor: string}>, emisiones: int}
     */
    public function formulario(Especificacion $especificacion): array
    {
        $actual = $especificacion->isNewRecord ? null : $especificacion->getVersionActual()->one();

        if ($actual === null) {
            return ['version' => new EspecificacionVersion(), 'filas' => [], 'emisiones' => 0];
        }

        $emisiones = (int) $actual->getEmisiones()->count();
        $version = clone $actual;

        // Si la version ya se emitio, guardar cambios crea la siguiente, y la
        // nota del cambio describe ESE cambio: el formulario la ofrece vacia.
        // Si no, se corrige la misma version y su nota se conserva.
        if ($emisiones > 0) {
            $version->notaCambio = null;
        }

        return [
            'version' => $version,
            'filas' => $this->filasDe($actual),
            'emisiones' => $emisiones,
        ];
    }

    /**
     * Guarda el formulario completo: la ficha (titulo, categoria, activo...)
     * y su contenido tecnico (EspecificacionVersion[...] y Caracteristicas[n]
     * [nombre|valor]), en una sola transaccion. Sirve para crear y editar.
     *
     * El archivo se recibe aparte porque los input type="file" no viajan en
     * $_POST (el controlador lo obtiene con UploadedFile::getInstance()). Si
     * viene uno, reemplaza al adjunto escrito en el campo de texto.
     *
     * Devuelve, ademas de si se guardo, lo necesario para volver a mostrar el
     * formulario con lo que escribio el usuario si algo no es valido.
     *
     * @return array{guardado: bool, version: EspecificacionVersion, filas: array<int, array{nombre: ?string, valor: ?string}>, nueva: bool, errores: string[]}
     */
    public function guardar(Especificacion $especificacion, array $datosFormulario, ?UploadedFile $archivo): array
    {
        $datosVersion = (array) ($datosFormulario['EspecificacionVersion'] ?? []);
        $filas = array_values(array_filter((array) ($datosFormulario['Caracteristicas'] ?? []), 'is_array'));

        // La version "del formulario": solo para volver a mostrar lo escrito.
        $versionFormulario = new EspecificacionVersion();
        $versionFormulario->setAttributes(array_intersect_key($datosVersion, array_flip(['modeloSugerido', 'garantia', 'adjuntoRuta', 'notaCambio'])), false);

        $resultado = ['guardado' => false, 'version' => $versionFormulario, 'filas' => $filas, 'nueva' => false, 'errores' => []];

        if (!$especificacion->load($datosFormulario)) {
            return $resultado;
        }

        $especificacion->archivoEspecificacion = $archivo;

        if (!$especificacion->validate()) {
            return $resultado;
        }

        $rutaArchivo = $archivo !== null ? ArchivoSubido::rutaNueva(self::CARPETA_ADJUNTOS, 'esp_', $archivo->extension) : null;
        if ($rutaArchivo !== null) {
            $versionFormulario->adjuntoRuta = $rutaArchivo;
        }

        $eraNueva = $especificacion->isNewRecord;
        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $especificacion->save(false);

            $version = $this->guardarVersion($especificacion, [
                'modeloSugerido' => $versionFormulario->modeloSugerido,
                'garantia' => $versionFormulario->garantia,
                'adjuntoRuta' => $versionFormulario->adjuntoRuta,
                'notaCambio' => $versionFormulario->notaCambio,
            ], $filas);

            if ($version['errores'] !== []) {
                $transaccion->rollBack();
                // Una ficha nueva que no se llego a guardar sigue siendo nueva
                // (el formulario vuelve a ser el de "crear").
                if ($eraNueva) {
                    $especificacion->setIsNewRecord(true);
                    $especificacion->id = null;
                }
                $resultado['errores'] = $version['errores'];

                return $resultado;
            }

            // El archivo se escribe recien cuando todo lo demas es valido:
            // asi un formulario con errores no deja archivos huerfanos.
            if ($rutaArchivo !== null) {
                ArchivoSubido::guardar($archivo, $rutaArchivo);
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return ['guardado' => true, 'version' => $version['version'], 'filas' => $filas, 'nueva' => $version['nueva'], 'errores' => []];
    }

    /**
     * Historial de versiones de la ficha (de la mas nueva a la mas antigua),
     * cada una con en cuantos formularios NET se uso. Los conteos se traen en
     * UNA consulta agrupada.
     *
     * @return array<int, array{version: EspecificacionVersion, emisiones: int}>
     */
    public function historial(Especificacion $especificacion): array
    {
        $versiones = $especificacion->getVersiones()->all();
        $ids = array_map(static fn (EspecificacionVersion $v) => $v->id, $versiones);

        $emisiones = $ids === [] ? [] : EspecificacionEmision::find()
            ->select(['total' => 'COUNT(*)', 'idVersion'])
            ->andWhere(['idVersion' => $ids])
            ->groupBy('idVersion')
            ->indexBy('idVersion')
            ->column();

        return array_map(static fn (EspecificacionVersion $v) => [
            'version' => $v,
            'emisiones' => (int) ($emisiones[$v->id] ?? 0),
        ], $versiones);
    }

    /**
     * Una version concreta de la ficha (para verla en el historial), o null
     * si ese numero no existe.
     */
    public function version(Especificacion $especificacion, int $numero): ?EspecificacionVersion
    {
        return EspecificacionVersion::findOne(['idEspecificacion' => $especificacion->id, 'numero' => $numero]);
    }

    /**
     * Guarda el contenido tecnico de la ficha (modelo sugerido, garantia,
     * adjunto, caracteristicas) en su version actual o en una nueva.
     *
     * REGLA DE NEGOCIO: una version que ya se uso en una emision NET no se
     * modifica nunca (el documento firmado apunta a ella).
     *  - Ficha sin versiones: se crea la v1.
     *  - Version actual SIN emisiones: se corrige ahi mismo.
     *  - Version actual CON emisiones: si el contenido cambio, se crea la
     *    version siguiente y la anterior queda intacta; si no cambio nada
     *    (ej. solo se edito el titulo o "activo"), no se crea ninguna.
     *
     * $datos puede traer modeloSugerido, garantia, adjuntoRuta y notaCambio.
     * Una clave que no viene conserva el valor de la version actual, salvo
     * notaCambio, que en una version nueva empieza vacia (la nota describe
     * ESE cambio, no el anterior). $filas = null conserva las caracteristicas
     * de la version actual; las filas sin nombre ni valor se ignoran.
     *
     * La ficha tiene que estar guardada (necesita su id).
     *
     * @param array{modeloSugerido?: ?string, garantia?: ?string, adjuntoRuta?: ?string, notaCambio?: ?string} $datos
     * @param array<int, array{nombre?: ?string, valor?: ?string}>|null $filas en el orden en que se imprimen
     * @return array{version: ?EspecificacionVersion, nueva: bool, errores: string[]}
     */
    public function guardarVersion(Especificacion $especificacion, array $datos, ?array $filas = null): array
    {
        $actual = $especificacion->getVersionActual()->one();
        // Limpias igual que las del formulario, para poder compararlas.
        [$filasActuales] = $this->normalizarFilas($actual !== null ? $this->filasDe($actual) : []);

        [$filasNormalizadas, $errores] = $this->normalizarFilas($filas ?? $filasActuales);

        if ($errores !== []) {
            return ['version' => null, 'nueva' => false, 'errores' => $errores];
        }

        $contenido = [];
        foreach (['modeloSugerido', 'garantia', 'adjuntoRuta'] as $campo) {
            $contenido[$campo] = array_key_exists($campo, $datos)
                ? $this->textoONull($datos[$campo])
                : $actual?->$campo;
        }

        $bloqueada = $actual !== null && $actual->estaBloqueada();

        if ($bloqueada && $this->mismoContenido($actual, $filasActuales, $contenido, $filasNormalizadas)) {
            return ['version' => $actual, 'nueva' => false, 'errores' => []];
        }

        $nueva = $actual === null || $bloqueada;

        if ($nueva) {
            $version = new EspecificacionVersion([
                'idEspecificacion' => $especificacion->id,
                'numero' => $actual === null ? 1 : $actual->numero + 1,
                'notaCambio' => $this->textoONull($datos['notaCambio'] ?? null),
            ]);
        } else {
            $version = $actual;
            if (array_key_exists('notaCambio', $datos)) {
                $version->notaCambio = $this->textoONull($datos['notaCambio']);
            }
        }

        $version->setAttributes($contenido, false);

        if (!$version->validate()) {
            return ['version' => null, 'nueva' => false, 'errores' => $version->getErrorSummary(true)];
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $version->save(false);

            // En una version que se corrige ahi mismo, las filas se reemplazan
            // completas: es mas simple y seguro que emparejarlas una por una,
            // y nadie apunta a una fila individual.
            if (!$nueva) {
                EspecificacionCaracteristica::deleteAll(['idVersion' => $version->id]);
            }

            foreach ($filasNormalizadas as $orden => $fila) {
                (new EspecificacionCaracteristica([
                    'idVersion' => $version->id,
                    'orden' => $orden + 1,
                    'nombre' => $fila['nombre'],
                    'valor' => $fila['valor'],
                ]))->save(false);
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        unset($version->caracteristicas);

        return ['version' => $version, 'nueva' => $nueva, 'errores' => []];
    }

    /**
     * Las caracteristicas de una version como filas simples.
     *
     * @return array<int, array{nombre: ?string, valor: string}>
     */
    private function filasDe(EspecificacionVersion $version): array
    {
        return array_map(static fn (EspecificacionCaracteristica $c) => [
            'nombre' => $c->nombre,
            'valor' => $c->valor,
        ], $version->getCaracteristicas()->all());
    }

    /**
     * Limpia las filas del formulario: recorta espacios, descarta las que
     * vienen completamente vacias (la tabla del formulario siempre deja una
     * fila en blanco) y exige valor en las que tienen nombre.
     *
     * @param array<int, array{nombre?: ?string, valor?: ?string}> $filas
     * @return array{0: array<int, array{nombre: ?string, valor: string}>, 1: string[]}
     */
    private function normalizarFilas(array $filas): array
    {
        $limpias = [];
        $errores = [];

        foreach (array_values($filas) as $i => $fila) {
            $nombre = $this->textoONull($fila['nombre'] ?? null);
            $valor = $this->textoONull($fila['valor'] ?? null);

            if ($nombre === null && $valor === null) {
                continue;
            }

            if ($valor === null) {
                $errores[] = 'Fila ' . ($i + 1) . " (\"$nombre\"): falta el valor.";
                continue;
            }

            if ($nombre !== null && mb_strlen($nombre) > 200) {
                $errores[] = 'Fila ' . ($i + 1) . ': el nombre no puede pasar de 200 caracteres.';
                continue;
            }

            $limpias[] = ['nombre' => $nombre, 'valor' => $valor];
        }

        return [$limpias, $errores];
    }

    /**
     * true si guardar no cambiaria nada del contenido tecnico. notaCambio no
     * cuenta: no es parte del documento que ve el proveedor.
     *
     * @param array<int, array{nombre: ?string, valor: string}> $filasActuales
     * @param array<string, ?string> $contenido
     * @param array<int, array{nombre: ?string, valor: string}> $filasNuevas
     */
    private function mismoContenido(EspecificacionVersion $actual, array $filasActuales, array $contenido, array $filasNuevas): bool
    {
        foreach ($contenido as $campo => $valor) {
            if ($this->textoONull($actual->$campo) !== $valor) {
                return false;
            }
        }

        return $filasActuales === $filasNuevas;
    }

    private function textoONull(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /**
     * REGLA DE NEGOCIO: una especificacion enlazada (a un item de proyecto o
     * a una linea de cotizacion) no se elimina; se desactiva. Devuelve null
     * si se elimino, o el motivo por el que no.
     */
    public function eliminar(Especificacion $especificacion): ?string
    {
        $usos = $this->conteoDeUsos([$especificacion->id])[$especificacion->id] ?? 0;

        if ($usos > 0) {
            return "No se puede eliminar \"{$especificacion->nombre()}\": está enlazada en $usos lugar(es). "
                . 'Puede desactivarla desde "Editar" para que ya no se ofrezca en los selectores.';
        }

        $especificacion->delete();

        return null;
    }

    /**
     * Cuantas veces se usa cada especificacion: [idEspecificacion => total].
     *
     * @param int[]|null $ids null = todas
     * @return array<int, int>
     */
    private function conteoDeUsos(?array $ids = null): array
    {
        $totales = [];

        foreach ([ProyectoItem::class, CotizacionDetalle::class] as $clase) {
            $conteos = $clase::find()
                ->select(['total' => 'COUNT(*)', 'idEspecificacion'])
                ->andWhere(['not', ['idEspecificacion' => null]])
                ->andFilterWhere(['idEspecificacion' => $ids])
                ->groupBy('idEspecificacion')
                ->indexBy('idEspecificacion')
                ->column();

            foreach ($conteos as $id => $total) {
                $totales[(int) $id] = ($totales[(int) $id] ?? 0) + (int) $total;
            }
        }

        // Emisiones NET de cualquiera de sus versiones (tambien las anuladas:
        // son documentos que se emitieron).
        $emisiones = EspecificacionEmision::find()
            ->alias('e')
            ->innerJoin(['v' => EspecificacionVersion::tableName()], 'v.id = e.idVersion')
            ->select(['total' => 'COUNT(*)', 'v.idEspecificacion'])
            ->andFilterWhere(['v.idEspecificacion' => $ids])
            ->groupBy('v.idEspecificacion')
            ->indexBy('idEspecificacion')
            ->column();

        foreach ($emisiones as $id => $total) {
            $totales[(int) $id] = ($totales[(int) $id] ?? 0) + (int) $total;
        }

        return $totales;
    }

    /**
     * Lee el archivo (todavia sin guardar la Especificacion) con
     * EspecificacionMetadataExtractor para prellenar el formulario.
     *
     * La extraccion es una AYUDA: si algo falla o un campo no se encuentra
     * (ej. modeloSugerido, que es opcional), se responde igual con lo que si
     * se pudo encontrar -- nunca se bloquea al usuario para que complete el
     * formulario a mano.
     *
     * @return array{metadatos: array}|array{error: string}
     */
    public function extraerMetadatos(?UploadedFile $archivo): array
    {
        if ($archivo === null) {
            return ['error' => 'No se recibió ningún archivo.'];
        }

        $extension = strtolower((string) $archivo->extension);

        if (!in_array($extension, self::EXTENSIONES_EXTRACCION, true)) {
            return ['error' => 'Solo se aceptan archivos PDF o DOCX.'];
        }

        try {
            $extractor = new EspecificacionMetadataExtractor();

            return ['metadatos' => $extractor->extraerDesdeArchivo($archivo->tempName, $extension)];
        } catch (Throwable $e) {
            Yii::error("Error extrayendo metadatos de especificación: {$e->getMessage()}", __METHOD__);

            return ['error' => 'No se pudo leer el archivo. Verifique que no esté dañado y vuelva a intentar.'];
        }
    }
}
