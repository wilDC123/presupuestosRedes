<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\EspecificacionMetadataExtractor;
use backend\models\CotizacionDetalle;
use backend\models\Especificacion;
use backend\models\EspecificacionCaracteristica;
use backend\models\EspecificacionEmision;
use backend\models\EspecificacionVersion;
use backend\models\ProyectoItem;
use Throwable;
use Yii;
use yii\helpers\FileHelper;
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
    private const UPLOAD_SUBPATH = 'uploads/especificaciones';
    private const EXTENSIONES_EXTRACCION = ['pdf', 'docx'];

    /**
     * Libreria filtrada, cada especificacion con su cantidad de usos (items
     * de proyecto + lineas de cotizacion). Los conteos se traen en UNA
     * consulta agrupada por tabla, no una consulta por especificacion.
     *
     * Filtros (todos opcionales): 'categoria' (id), 'buscar' (texto en
     * titulo, codigo NET o modelo sugerido), 'activo' ('1' / '0').
     *
     * @param array{categoria?: string, buscar?: string, activo?: string} $filtros
     * @return array<int, array{especificacion: Especificacion, usos: int}>
     */
    public function listar(array $filtros = []): array
    {
        $query = Especificacion::find()
            ->with(['categoria', 'subcatalogoItem'])
            ->orderBy(['titulo' => SORT_ASC, 'id' => SORT_ASC]);

        $categoria = trim((string) ($filtros['categoria'] ?? ''));
        if (ctype_digit($categoria)) {
            $query->andWhere(['idCategoria' => (int) $categoria]);
        }

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $query->andWhere(['or',
                ['like', 'titulo', $buscar],
                ['like', 'codigoNet', $buscar],
                ['like', 'modeloSugerido', $buscar],
            ]);
        }

        $activo = (string) ($filtros['activo'] ?? '');
        if ($activo === '1' || $activo === '0') {
            $query->andWhere(['activo' => $activo === '1']);
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
     *
     * Solo especificaciones ACTIVAS, mas las de $incluirIds aunque esten
     * desactivadas: son las que ya estan enlazadas en la pantalla y no deben
     * perderse al volver a guardar.
     *
     * @param int[] $incluirIds
     * @return array{grupos: array<int, array{nombre: string, opciones: array<int, string>}>, urls: array<int, string>}
     */
    public function selector(array $incluirIds = []): array
    {
        $query = Especificacion::find()->with('categoria')->andWhere(['activo' => true]);
        $incluirIds = array_values(array_filter(array_map('intval', $incluirIds)));

        if ($incluirIds !== []) {
            $query->orWhere(['id' => $incluirIds]);
        }

        $especificaciones = $query->all();

        usort($especificaciones, static fn (Especificacion $a, Especificacion $b) =>
            [$a->categoria->orden ?? PHP_INT_MAX, $a->nombre()] <=> [$b->categoria->orden ?? PHP_INT_MAX, $b->nombre()]);

        $grupos = [];
        $urls = [];

        foreach ($especificaciones as $especificacion) {
            $idCategoria = (int) $especificacion->idCategoria;
            $grupos[$idCategoria]['nombre'] ??= $especificacion->categoria->nombre ?? 'Sin categoría';
            $grupos[$idCategoria]['opciones'][$especificacion->id] = $especificacion->nombre()
                . ($especificacion->codigoNet && $especificacion->titulo ? " ({$especificacion->codigoNet})" : '')
                . ($especificacion->activo ? '' : ' — inactiva');

            $url = $especificacion->urlDocumento();
            if ($url !== null) {
                $urls[$especificacion->id] = $url;
            }
        }

        return ['grupos' => $grupos, 'urls' => $urls];
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
     * Sirve tanto para crear como para actualizar: carga el formulario,
     * valida, guarda el archivo adjunto (si hay) y recien ahi guarda la fila.
     * El archivo se recibe aparte porque los input type="file" no viajan en
     * $_POST (el controlador lo obtiene con UploadedFile::getInstance()).
     */
    public function guardar(Especificacion $especificacion, array $datosFormulario, ?UploadedFile $archivo): bool
    {
        if (!$especificacion->load($datosFormulario)) {
            return false;
        }

        $especificacion->archivoEspecificacion = $archivo;

        if (!$especificacion->validate()) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $this->guardarArchivoSubido($especificacion);
            $especificacion->save(false);

            // Mientras el formulario siga editando modeloSugerido y
            // documentoRuta en la ficha (hasta el paso 3 del plan), se copian
            // a la version para que las dos no se separen. Si la version ya
            // se emitio, esto crea la siguiente.
            $resultado = $this->guardarVersion($especificacion, [
                'modeloSugerido' => $especificacion->modeloSugerido,
                'adjuntoRuta' => $especificacion->documentoRuta,
            ]);

            if ($resultado['errores'] !== []) {
                $transaccion->rollBack();
                $especificacion->addError('modeloSugerido', implode(' ', $resultado['errores']));

                return false;
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
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

    /**
     * Si el usuario adjuntó un archivo nuevo, lo guarda en
     * backend/web/uploads/especificaciones y actualiza documentoRuta con la
     * ruta relativa (la que se guarda en la BD). Este es el mismo archivo
     * que, si se usó, ya sirvió para prellenar el formulario via
     * extraerMetadatos() -- aca se persiste como el documento definitivo.
     * Si no adjuntó nada, documentoRuta queda tal como el usuario lo haya
     * escrito a mano en el campo de texto.
     */
    private function guardarArchivoSubido(Especificacion $especificacion): void
    {
        if ($especificacion->archivoEspecificacion === null) {
            return;
        }

        $uploadDir = Yii::getAlias('@backend/web/' . self::UPLOAD_SUBPATH);
        FileHelper::createDirectory($uploadDir);

        $fileName = uniqid('esp_', true) . '.' . $especificacion->archivoEspecificacion->extension;
        $especificacion->archivoEspecificacion->saveAs($uploadDir . '/' . $fileName);

        $especificacion->documentoRuta = self::UPLOAD_SUBPATH . '/' . $fileName;
    }
}
