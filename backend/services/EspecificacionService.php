<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\EspecificacionMetadataExtractor;
use backend\models\Especificacion;
use Throwable;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * Capa de Servicio para Especificacion (un item del subcatalogo puede tener
 * varias especificaciones tecnicas).
 *
 * Ademas del CRUD, aqui vive: donde y con que nombre se guarda el documento
 * adjunto, y la extraccion automatica de metadatos desde PDF/DOCX.
 */
class EspecificacionService
{
    private const UPLOAD_SUBPATH = 'uploads/especificaciones';
    private const EXTENSIONES_EXTRACCION = ['pdf', 'docx'];

    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Especificacion::find()->with('subcatalogoItem')->orderBy(['id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
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

    public function eliminar(Especificacion $especificacion): bool
    {
        return $especificacion->delete() !== false;
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
