<?php

declare(strict_types=1);

namespace backend\services;

use backend\services\contracts\ParametrosProyectoProvider;
use Yii;
use yii\base\InvalidConfigException;

/**
 * Fuente de parametros de proyecto que lee un archivo JSON
 * (backend/config/parametros-proyecto.json).
 *
 * Es la unica clase que sabe que los datos vienen de un archivo: el resto
 * del sistema solo conoce la interface ParametrosProyectoProvider.
 */
class JsonParametrosProyectoProvider implements ParametrosProyectoProvider
{
    /** @var array<string, mixed>|null contenido del archivo, leido una sola vez por peticion */
    private ?array $datos = null;

    /**
     * @param string $rutaArchivo ruta o alias de Yii (ej. "@backend/config/parametros-proyecto.json")
     */
    public function __construct(private string $rutaArchivo)
    {
    }

    public function tiposProyecto(): array
    {
        return $this->datos()['tiposProyecto'] ?? [];
    }

    public function facultades(): array
    {
        return $this->datos()['facultades'] ?? [];
    }

    public function proyectistas(): array
    {
        return $this->datos()['proyectistas'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(): array
    {
        if ($this->datos !== null) {
            return $this->datos;
        }

        $ruta = Yii::getAlias($this->rutaArchivo);
        $contenido = is_file($ruta) ? file_get_contents($ruta) : false;

        if ($contenido === false) {
            throw new InvalidConfigException("No se pudo leer el archivo de parámetros de proyecto: $ruta");
        }

        $datos = json_decode($contenido, true);

        if (!is_array($datos)) {
            throw new InvalidConfigException("El archivo de parámetros de proyecto no es un JSON válido: $ruta");
        }

        return $this->datos = $datos;
    }
}
