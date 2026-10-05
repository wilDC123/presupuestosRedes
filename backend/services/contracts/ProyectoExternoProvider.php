<?php

declare(strict_types=1);

namespace backend\services\contracts;

/**
 * Contrato para traer los datos basicos de un proyecto desde OTRO sistema
 * institucional (por ejemplo, si el proyecto ya fue registrado en el sistema
 * de planificacion y aqui solo se elabora su presupuesto).
 *
 * TODAVIA NO TIENE IMPLEMENTACION: no se sabe que sistema seria ni que
 * formato entrega. Queda definido el contrato para que, cuando exista, se
 * escriba una clase que lo implemente y ProyectoService la use para
 * prellenar el formulario "Nuevo proyecto", sin cambiar controladores ni
 * vistas.
 */
interface ProyectoExternoProvider
{
    /**
     * Busca un proyecto por su codigo en el sistema externo.
     *
     * @return array{nombre: string, descripcion?: string, tipo?: string, codigoFacultad?: string, codigoEdificio?: string}|null
     *         null si el sistema externo no conoce ese codigo
     */
    public function buscarPorCodigo(string $codigo): ?array;
}
