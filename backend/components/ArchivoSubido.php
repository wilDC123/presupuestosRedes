<?php

declare(strict_types=1);

namespace backend\components;

use RuntimeException;
use Yii;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * Donde se guardan los archivos que suben los usuarios (PDF de cotizaciones,
 * adjuntos de especificaciones) y como se encuentran despues.
 *
 * Se guardan en backend/uploads, FUERA de la carpeta publica backend/web: asi
 * Nginx no los entrega directamente a cualquiera que adivine la direccion.
 * Los entregan acciones de controlador (cotizacion/documento,
 * especificacion/adjunto), que exigen sesion iniciada como todo el sistema.
 *
 * En la base se sigue guardando la ruta relativa ("uploads/cotizaciones/x.pdf").
 */
class ArchivoSubido
{
    private const BASE = '@backend';
    private const CARPETA = 'uploads';

    /**
     * Ruta relativa con la que se guardara un archivo nuevo, por ejemplo
     * "uploads/especificaciones/esp_6ad05...pdf". Todavia no escribe nada.
     */
    public static function rutaNueva(string $subcarpeta, string $prefijo, string $extension): string
    {
        return self::CARPETA . '/' . $subcarpeta . '/' . uniqid($prefijo, true) . '.' . strtolower($extension);
    }

    /**
     * Guarda el archivo subido en la ruta relativa dada (ver rutaNueva()).
     */
    public static function guardar(UploadedFile $archivo, string $rutaRelativa): void
    {
        $destino = Yii::getAlias(self::BASE) . '/' . $rutaRelativa;
        FileHelper::createDirectory(dirname($destino));

        if (!$archivo->saveAs($destino)) {
            throw new RuntimeException('No se pudo guardar el archivo subido.');
        }
    }

    /**
     * Ruta absoluta del archivo para entregarlo, o null si no existe o si la
     * ruta guardada apunta fuera de backend/uploads.
     *
     * REGLA DE SEGURIDAD: el adjunto de una especificacion tambien se puede
     * escribir a mano. Sin esta verificacion, alguien podria escribir
     * "../../common/config/main-local.php" y descargar la configuracion con
     * la contrasena de la base de datos.
     */
    public static function rutaAbsoluta(?string $rutaRelativa): ?string
    {
        $rutaRelativa = trim((string) $rutaRelativa);

        if ($rutaRelativa === '' || self::esEnlaceExterno($rutaRelativa)) {
            return null;
        }

        $carpeta = realpath(Yii::getAlias(self::BASE) . '/' . self::CARPETA);
        $archivo = realpath(Yii::getAlias(self::BASE) . '/' . ltrim($rutaRelativa, '/\\'));

        if ($carpeta === false || $archivo === false || !is_file($archivo)) {
            return null;
        }

        if (!str_starts_with($archivo, $carpeta . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $archivo;
    }

    /**
     * Un enlace escrito a mano (ej. Google Drive) se usa tal cual.
     */
    public static function esEnlaceExterno(?string $ruta): bool
    {
        return preg_match('#^https?://#i', trim((string) $ruta)) === 1;
    }
}
