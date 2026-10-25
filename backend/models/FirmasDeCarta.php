<?php

declare(strict_types=1);

namespace backend\models;

/**
 * Las tres firmas de la carta de entrega (prefijo, nombre y cargo de cada
 * una), que comparten ConfiguracionCarta (los valores por defecto) y
 * CartaPresupuesto (la copia de cada carta). Las dos primeras van lado a
 * lado; la tercera (el V°B° del jefe) centrada debajo.
 *
 * Un "trait" de PHP es un bloque de metodos que se "pega" dentro de una
 * clase con `use FirmasDeCarta;`: asi las dos clases tienen el mismo codigo
 * sin heredar una de la otra.
 */
trait FirmasDeCarta
{
    /**
     * @return string[] los 9 atributos firmaNPrefijo/Nombre/Cargo
     */
    public static function atributosFirmas(): array
    {
        $atributos = [];

        foreach ([1, 2, 3] as $n) {
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
        $reglas = [
            [self::atributosFirmas(), 'trim'],
            [self::atributosFirmas(), 'default', 'value' => null],
            [['firma1Cargo', 'firma2Cargo', 'firma3Cargo'], 'filter', 'filter' => static fn ($valor) => is_string($valor) ? mb_strtoupper($valor, 'UTF-8') : $valor],
            [['firma1Prefijo', 'firma2Prefijo', 'firma3Prefijo'], 'string', 'max' => 20],
            [['firma1Nombre', 'firma2Nombre', 'firma3Nombre'], 'string', 'max' => 150],
            [['firma1Cargo', 'firma2Cargo', 'firma3Cargo'], 'string', 'max' => 200],
        ];

        foreach ([1 => 'izquierda', 2 => 'derecha', 3 => 'de abajo (V°B°)'] as $n => $lugar) {
            $reglas[] = ["firma{$n}Nombre", 'required',
                'when' => static fn ($m) => (string) $m->{"firma{$n}Prefijo"} !== '' || (string) $m->{"firma{$n}Cargo"} !== '',
                'whenClient' => 'function () { return false; }',
                'message' => "Escriba el nombre de la firma {$lugar} (sin nombre, la firma no sale)."];
        }

        return $reglas;
    }

    protected static function etiquetasFirmas(): array
    {
        $etiquetas = [];

        foreach ([1, 2, 3] as $n) {
            $etiquetas["firma{$n}Prefijo"] = 'Prefijo';
            $etiquetas["firma{$n}Nombre"] = 'Nombre';
            $etiquetas["firma{$n}Cargo"] = 'Cargo';
        }

        return $etiquetas;
    }

    /**
     * Las firmas con nombre, por posicion (1, 2 = arriba; 3 = abajo).
     *
     * @return array<int, array{prefijo: string, nombre: string, cargo: string}>
     */
    public function firmas(): array
    {
        $firmas = [];

        foreach ([1, 2, 3] as $n) {
            if ((string) $this->{"firma{$n}Nombre"} !== '') {
                $firmas[$n] = [
                    'prefijo' => (string) $this->{"firma{$n}Prefijo"},
                    'nombre' => (string) $this->{"firma{$n}Nombre"},
                    'cargo' => (string) $this->{"firma{$n}Cargo"},
                ];
            }
        }

        return $firmas;
    }
}
