<?php

declare(strict_types=1);

namespace backend\services;

use backend\services\contracts\ParametrosProyectoProvider;

/**
 * Capa de Servicio para los datos parametrizados de un proyecto: hoy solo
 * las facultades, que sirven de SUGERENCIA para la unidad solicitante (ver
 * ProyectoService::sugerenciasUnidad()). Lo demas ya no esta aqui: los tipos
 * de proyecto y los proyectistas son tablas que administra el encargado
 * (redes.TipoProyecto, redes.Proyectista) y los edificios salen de la
 * infraestructura de la universidad (InfraestructuraProvider).
 *
 * No sabe de donde vienen los datos: recibe en el constructor "algo que
 * cumple la interface ParametrosProyectoProvider". Yii decide cual clase
 * concreta entregar segun backend/config/main.php ('container'). A eso se le
 * llama inyeccion de dependencias.
 */
class ParametrosProyectoService
{
    public function __construct(private ParametrosProyectoProvider $provider)
    {
    }

    /**
     * @return array<string, string> [codigo => nombre] de las facultades
     */
    public function opcionesFacultad(): array
    {
        return array_column($this->provider->facultades(), 'nombre', 'codigo');
    }
}
