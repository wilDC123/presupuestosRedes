<?php

declare(strict_types=1);

namespace backend\services\contracts;

/**
 * Contrato de la FUENTE de los datos parametrizados de un proyecto: hoy solo
 * las facultades, que sirven de sugerencia para la unidad solicitante. (Los
 * tipos de proyecto y el personal son tablas que administra el
 * encargado; los edificios salen de InfraestructuraProvider.)
 *
 * Una "interface" solo dice QUE metodos existen y que devuelven, no como se
 * obtienen los datos. Hoy la implementa JsonParametrosProyectoProvider (lee
 * un archivo JSON). Si manana la universidad expone estos datos en un
 * servicio web, se escribe otra clase que implemente esta misma interface y
 * se cambia UNA linea en backend/config/main.php ('container'): ni
 * ParametrosProyectoService, ni los controladores, ni las vistas se tocan.
 */
interface ParametrosProyectoProvider
{
    /**
     * @return array<int, array{codigo: string, nombre: string}>
     */
    public function facultades(): array;
}
