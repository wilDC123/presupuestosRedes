<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\EspecificacionMetadataExtractor;
use backend\models\CotizacionDetalle;
use backend\models\Especificacion;
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
 *  - una especificacion en uso no se elimina: se desactiva.
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

        $this->guardarArchivoSubido($especificacion);

        return $especificacion->save(false);
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
