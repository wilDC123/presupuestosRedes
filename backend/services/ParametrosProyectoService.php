<?php

declare(strict_types=1);

namespace backend\services;

use backend\services\contracts\ParametrosProyectoProvider;

/**
 * Capa de Servicio para los datos parametrizados de un proyecto (facultad,
 * edificio, bloques, proyectistas). El tipo de proyecto ya no es un
 * parametro: es la tabla redes.TipoProyecto (ver TipoProyectoService).
 *
 * No sabe de donde vienen los datos: recibe en el constructor "algo que
 * cumple la interface ParametrosProyectoProvider". Yii decide cual clase
 * concreta entregar segun backend/config/main.php ('container'). A eso se le
 * llama inyeccion de dependencias.
 *
 * Los controladores y ProyectoService usan esta clase; ninguna vista tiene
 * la lista de facultades escrita a mano.
 */
class ParametrosProyectoService
{
    public function __construct(private ParametrosProyectoProvider $provider)
    {
    }

    /**
     * @return array<string, string> [codigo => nombre] para el desplegable de facultad
     */
    public function opcionesFacultad(): array
    {
        return array_column($this->provider->facultades(), 'nombre', 'codigo');
    }

    /**
     * @return array<string, string> [codigo => nombre] de los edificios de una facultad ([] si no existe)
     */
    public function opcionesEdificio(?string $codigoFacultad): array
    {
        return array_column($this->facultad($codigoFacultad)['edificios'] ?? [], 'nombre', 'codigo');
    }

    /**
     * @return array<string, string> [codigo => nombre] de los bloques de un edificio ([] si no existe)
     */
    public function opcionesBloque(?string $codigoFacultad, ?string $codigoEdificio): array
    {
        return array_column($this->edificio($codigoFacultad, $codigoEdificio)['bloques'] ?? [], 'nombre', 'codigo');
    }

    /**
     * @return string[] nombres de proyectistas disponibles
     */
    public function proyectistas(): array
    {
        return $this->provider->proyectistas();
    }

    public function nombreFacultad(?string $codigo): ?string
    {
        return $this->facultad($codigo)['nombre'] ?? null;
    }

    public function nombreEdificio(?string $codigoFacultad, ?string $codigoEdificio): ?string
    {
        return $this->edificio($codigoFacultad, $codigoEdificio)['nombre'] ?? null;
    }

    /**
     * Arbol completo facultad -> edificios -> bloques. El formulario lo
     * recibe como JSON para que, al cambiar la facultad, el navegador
     * actualice los edificios y bloques sin recargar la pagina.
     */
    public function arbolUbicacion(): array
    {
        return $this->provider->facultades();
    }

    private function facultad(?string $codigo): ?array
    {
        foreach ($this->provider->facultades() as $facultad) {
            if ($facultad['codigo'] === $codigo) {
                return $facultad;
            }
        }

        return null;
    }

    private function edificio(?string $codigoFacultad, ?string $codigoEdificio): ?array
    {
        foreach ($this->facultad($codigoFacultad)['edificios'] ?? [] as $edificio) {
            if ($edificio['codigo'] === $codigoEdificio) {
                return $edificio;
            }
        }

        return null;
    }
}
