<?php

declare(strict_types=1);

namespace backend\models;

/**
 * Las dos firmas del "control de elaboracion" del lineamiento de la DTIC
 * RD-GD-LI-001 (8.12) en el formulario de especificacion tecnica: firma 1 =
 * elaborado por, firma 2 = aprobado por (con su V°B°). Prefijo, nombre y
 * cargo de cada una. Las comparten ConfiguracionEspecificacion (los valores
 * por defecto) y EspecificacionEmision (la copia de cada formulario), igual
 * que FirmasDeCarta en la carta.
 *
 * (El presupuesto tiene las mismas dos firmas en ConfiguracionPresupuesto.)
 */
trait FirmasDeControl
{
    /**
     * @return string[] los 6 atributos firmaNPrefijo/Nombre/Cargo
     */
    public static function atributosFirmas(): array
    {
        $atributos = [];

        foreach ([1, 2] as $n) {
            array_push($atributos, "firma{$n}Prefijo", "firma{$n}Nombre", "firma{$n}Cargo");
        }

        return $atributos;
    }

    /**
     * Reglas de las firmas: opcionales, cargo en mayusculas y, si hay
     * prefijo o cargo, tambien nombre (sin nombre la firma no sale).
     */
    protected function reglasFirmas(): array
    {
        return [
            [self::atributosFirmas(), 'trim'],
            [self::atributosFirmas(), 'default', 'value' => null],
            [['firma1Cargo', 'firma2Cargo'], 'filter', 'filter' => static fn ($valor) => is_string($valor) ? mb_strtoupper($valor, 'UTF-8') : $valor],
            [['firma1Prefijo', 'firma2Prefijo'], 'string', 'max' => 20],
            [['firma1Nombre', 'firma2Nombre'], 'string', 'max' => 150],
            [['firma1Cargo', 'firma2Cargo'], 'string', 'max' => 200],
            [['firma1Nombre'], 'required', 'when' => static fn ($m) => (string) $m->firma1Prefijo !== '' || (string) $m->firma1Cargo !== '', 'whenClient' => 'function () { return false; }', 'message' => 'Escriba el nombre de quien elabora (sin nombre, el prefijo y el cargo no salen).'],
            [['firma2Nombre'], 'required', 'when' => static fn ($m) => (string) $m->firma2Prefijo !== '' || (string) $m->firma2Cargo !== '', 'whenClient' => 'function () { return false; }', 'message' => 'Escriba el nombre de quien aprueba (sin nombre, el prefijo y el cargo no salen).'],
        ];
    }

    protected static function etiquetasFirmas(): array
    {
        $etiquetas = [];

        foreach ([1, 2] as $n) {
            $etiquetas["firma{$n}Prefijo"] = 'Prefijo';
            $etiquetas["firma{$n}Nombre"] = 'Nombre';
            $etiquetas["firma{$n}Cargo"] = 'Cargo';
        }

        return $etiquetas;
    }

    /**
     * La firma 1 (elaborado por) o 2 (aprobado por), o null si no tiene
     * nombre: en el PDF queda en blanco, para llenarla a mano.
     *
     * @return array{prefijo: string, nombre: string, cargo: string}|null
     */
    public function firma(int $n): ?array
    {
        if ((string) $this->{"firma{$n}Nombre"} === '') {
            return null;
        }

        return [
            'prefijo' => (string) $this->{"firma{$n}Prefijo"},
            'nombre' => (string) $this->{"firma{$n}Nombre"},
            'cargo' => (string) $this->{"firma{$n}Cargo"},
        ];
    }
}
