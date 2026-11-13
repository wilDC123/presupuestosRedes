<?php

declare(strict_types=1);

namespace backend\components;

use Yii;
use yii\base\Model;
use yii\validators\NumberValidator;

/**
 * Cantidades y precios: lo que cabe en las columnas decimal(10,2) de la base
 * (cantidad, precioUnitario y el total calculado precioTotal de
 * CotizacionDetalle y PresupuestoDetalle). Es el UNICO lugar de ese limite.
 *
 * Por que hace falta: la regla 'number' de Yii acepta "1e5" (notacion
 * cientifica) y numeros de cualquier tamano, pero SQL Server rechaza "1e5"
 * ("Error converting data type nvarchar to numeric") y lo que no cabe en
 * decimal(10,2) ("Arithmetic overflow"): el usuario veia un error del
 * servidor en vez de un aviso. Y "12.345" se redondeaba a 12.35 sin decir nada.
 */
final class Monto
{
    /** El mayor valor de una columna decimal(10,2). */
    public const MAXIMO = 99999999.99;

    /** Un numero sin signo, con punto decimal y hasta 2 decimales: "1250", "1250.5", "1250.50". */
    public const PATRON = '/^\d+(\.\d{1,2})?$/';

    /**
     * La regla para rules() de un modelo: numero >= 0, hasta 2 decimales y que
     * quepa en decimal(10,2).
     *
     * @param string[] $atributos
     */
    public static function regla(array $atributos): array
    {
        return [
            $atributos,
            'number',
            'min' => 0,
            'max' => self::MAXIMO,
            'numberPattern' => self::PATRON,
            'message' => '{attribute} debe ser un número con hasta 2 decimales, sin espacios ni comas (ej. 1250.50).',
            'tooBig' => '{attribute} no puede pasar de ' . self::texto(self::MAXIMO) . '.',
        ];
    }

    /**
     * true si el texto es un monto valido (la misma regla que regla()).
     */
    public static function valido(string $valor): bool
    {
        return (new NumberValidator(['min' => 0, 'max' => self::MAXIMO, 'numberPattern' => self::PATRON]))->validate($valor);
    }

    /**
     * true si cantidad x precio cabe en el total de la linea (precioTotal,
     * decimal(10,2)). Con datos que no son numeros devuelve true: eso lo avisa
     * la regla de cada campo.
     */
    public static function totalCabe(mixed $cantidad, mixed $precio): bool
    {
        if (!is_numeric($cantidad) || !is_numeric($precio)) {
            return true;
        }

        return round((float) $cantidad * (float) $precio, 2) <= self::MAXIMO;
    }

    /**
     * Validador en linea para un modelo con cantidad y precioUnitario: agrega
     * el error si el total de la linea no cabe.
     */
    public static function validarTotal(Model $modelo, string $atributo): void
    {
        if (!self::totalCabe($modelo->cantidad ?? null, $modelo->precioUnitario ?? null)) {
            $modelo->addError($atributo, 'El total de la línea (cantidad × precio) no puede pasar de ' . self::texto(self::MAXIMO) . '.');
        }
    }

    /**
     * Una cantidad para mostrar: sin decimales si es entera ("2", "1,200") y
     * con 2 si tiene fraccion ("30.50"). La columna es decimal(10,2), asi que
     * de la base llega "2.00"; mostrarlo asi en los impresos confundia (dos
     * unidades no son "2.00"). El formato usa el formatter de Yii, el mismo
     * separador de miles que los precios.
     */
    public static function cantidad(mixed $valor): string
    {
        $numero = is_numeric($valor) ? (float) $valor : 0.0;
        $entera = round($numero, 2) == floor(round($numero, 2));

        return Yii::$app->formatter->asDecimal($numero, $entera ? 0 : 2);
    }

    private static function texto(float $valor): string
    {
        return number_format($valor, 2, '.', ',');
    }
}
