<?php

declare(strict_types=1);

namespace backend\services\contracts;

/**
 * Contrato de la FUENTE de la infraestructura fisica de la universidad:
 * edificio -> bloque -> nivel -> ambiente. De aqui sale el "area de
 * intervencion" de un proyecto.
 *
 * Este sistema SOLO CONSULTA: no se registran edificios ni ambientes aqui
 * (pedido del Ing. de DTIC, 2026-10-27: "no quiero que se duplique nada de
 * informacion"). Si algo falta, lo registra su dueno.
 *
 * Hoy la implementa UsfxGeoInfraestructuraProvider (lee la base UsfxGEO). Si
 * manana Desarrollo entrega vistas o un servicio web, se escribe otra clase
 * con esta misma interface y se cambia UNA linea en backend/config/main.php
 * ('container'); el resto del sistema no se toca.
 *
 * Solo se ofrece lo VIGENTE. Los identificadores son los de la fuente y se
 * devuelven como texto (salvo idBloqueArea, que es numero).
 */
interface InfraestructuraProvider
{
    /**
     * @return array<string, string> [idEdificio => nombre], ordenados por nombre
     */
    public function edificios(): array;

    /**
     * @return array<int, string> [idBloqueArea => nombre] de un edificio
     */
    public function bloques(string $idEdificio): array;

    /**
     * @return array<string, string> [numeroNivel => descripcion] de un bloque, de abajo hacia arriba
     */
    public function niveles(int $idBloqueArea): array;

    /**
     * @return array<string, string> [idEspacio => "B101 Laboratorio de computación"] de un nivel
     */
    public function ambientes(int $idBloqueArea, string $numeroNivel): array;

    /**
     * Busca edificios por palabras (en su nombre, nombre corto y
     * descripcion), sin importar tildes.
     *
     * @return array<int, array{idEdificio: string, edificio: string}>
     */
    public function buscarEdificios(string $texto, int $limite = 10): array;

    /**
     * Busca ambientes por palabras (en el codigo y nombre del ambiente y en
     * los nombres del bloque y del edificio), sin importar tildes ni guiones
     * ("E303" encuentra "E-303").
     *
     * @return array<int, array{idEdificio: string, edificio: string, idBloqueArea: int, bloque: string, numeroNivel: string, nivel: string, idEspacio: string, codigoAmbiente: string, ambiente: string}>
     */
    public function buscarAmbientes(string $texto, int $limite = 30): array;

    /**
     * Verifica que la cadena exista y sea coherente (el bloque es de ese
     * edificio, el nivel de ese bloque, el ambiente de ese nivel) y devuelve
     * los nombres de cada parte. Se puede cortar en cualquier nivel (ej. solo
     * edificio y bloque), pero no saltarse uno.
     *
     * @return array{edificio: string, bloque: ?string, nivel: ?string, codigoAmbiente: ?string, ambiente: ?string}|null
     *         null si algo no existe, no esta vigente o no corresponde
     */
    public function ruta(string $idEdificio, ?int $idBloqueArea, ?string $numeroNivel, ?string $idEspacio): ?array;
}
