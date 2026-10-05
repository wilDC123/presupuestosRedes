<?php

declare(strict_types=1);

namespace backend\services\contracts;

/**
 * Contrato de la FUENTE de los datos parametrizados de un proyecto: tipos de
 * proyecto, facultades con sus edificios y bloques, y proyectistas.
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
    public function tiposProyecto(): array;

    /**
     * Facultades con sus edificios y los bloques de cada edificio.
     *
     * @return array<int, array{codigo: string, nombre: string, edificios: array<int, array{codigo: string, nombre: string, bloques: array<int, array{codigo: string, nombre: string}>}>}>
     */
    public function facultades(): array;

    /**
     * @return string[] nombres de las personas que pueden asignarse como proyectistas
     */
    public function proyectistas(): array;
}
