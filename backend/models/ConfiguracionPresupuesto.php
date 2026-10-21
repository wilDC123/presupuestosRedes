<?php

declare(strict_types=1);

namespace backend\models;

use RuntimeException;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ConfiguracionPresupuesto": UNA sola fila
 * (id = 1, lo garantiza un CHECK en la base) con lo que lleva el PDF del
 * presupuesto despues del total: nota, dos firmas y la banda de pie con los
 * contactos. Editable por el encargado (presupuesto/configuracion).
 *
 * Como la solicitud de cotizacion, el presupuesto no guarda copia: el PDF se
 * arma al imprimir, asi que un cambio aqui tambien se ve al reimprimir
 * presupuestos anteriores.
 *
 * @property int $id
 * @property string|null $textoNota
 * @property string|null $firma1Prefijo
 * @property string|null $firma1Nombre
 * @property string|null $firma1Cargo
 * @property string|null $firma2Prefijo
 * @property string|null $firma2Nombre
 * @property string|null $firma2Cargo
 * @property string|null $pieIzquierdaTitulo
 * @property string|null $pieIzquierdaLineas Una linea por renglon.
 * @property string|null $pieDerechaTitulo
 * @property string|null $pieDerechaLineas Una linea por renglon.
 */
class ConfiguracionPresupuesto extends ActiveRecord
{
    private const TEXTOS = [
        'textoNota',
        'firma1Prefijo', 'firma1Nombre', 'firma1Cargo',
        'firma2Prefijo', 'firma2Nombre', 'firma2Cargo',
        'pieIzquierdaTitulo', 'pieIzquierdaLineas', 'pieDerechaTitulo', 'pieDerechaLineas',
    ];

    public static function tableName(): string
    {
        return 'redes.ConfiguracionPresupuesto';
    }

    /**
     * La unica fila. La crea la migracion; si falta, la base esta incompleta
     * y es mejor detenerse con un mensaje claro que inventar valores.
     */
    public static function obtener(): self
    {
        $configuracion = self::findOne(1);

        if ($configuracion === null) {
            throw new RuntimeException('Falta la configuración del presupuesto (redes.ConfiguracionPresupuesto). Ejecute "php yii migrate".');
        }

        return $configuracion;
    }

    /**
     * Las firmas que tienen nombre, de izquierda a derecha.
     *
     * @return array<int, array{prefijo: string, nombre: string, cargo: string}>
     */
    public function firmas(): array
    {
        $firmas = [];

        foreach ([1, 2] as $n) {
            if ((string) $this->{"firma{$n}Nombre"} !== '') {
                $firmas[] = [
                    'prefijo' => (string) $this->{"firma{$n}Prefijo"},
                    'nombre' => (string) $this->{"firma{$n}Nombre"},
                    'cargo' => (string) $this->{"firma{$n}Cargo"},
                ];
            }
        }

        return $firmas;
    }

    /**
     * Las columnas de la banda de pie que tienen algo (titulo o renglones),
     * de izquierda a derecha, con los renglones sin lineas vacias.
     *
     * @return array<int, array{titulo: string, lineas: string[]}>
     */
    public function columnasPie(): array
    {
        $columnas = [];

        foreach (['Izquierda', 'Derecha'] as $lado) {
            $titulo = (string) $this->{"pie{$lado}Titulo"};
            $lineas = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->{"pie{$lado}Lineas"})), static fn (string $linea) => $linea !== ''));

            if ($titulo !== '' || $lineas !== []) {
                $columnas[] = ['titulo' => $titulo, 'lineas' => $lineas];
            }
        }

        return $columnas;
    }

    public function rules(): array
    {
        return [
            [self::TEXTOS, 'trim'],
            // Todo es opcional: un campo vacio se guarda como NULL y no sale.
            [self::TEXTOS, 'default', 'value' => null],
            // El cargo sale siempre en mayusculas (mb_: tambien con tildes).
            [['firma1Cargo', 'firma2Cargo'], 'filter', 'filter' => static fn ($valor) => is_string($valor) ? mb_strtoupper($valor, 'UTF-8') : $valor],
            [['textoNota', 'pieIzquierdaLineas', 'pieDerechaLineas'], 'string', 'max' => 500],
            [['firma1Prefijo', 'firma2Prefijo'], 'string', 'max' => 20],
            [['firma1Nombre', 'firma2Nombre', 'pieIzquierdaTitulo', 'pieDerechaTitulo'], 'string', 'max' => 150],
            [['firma1Cargo', 'firma2Cargo'], 'string', 'max' => 200],
            // Un prefijo o un cargo sin nombre no saldria en el PDF.
            [['firma1Nombre'], 'required', 'when' => static fn (self $m) => (string) $m->firma1Prefijo !== '' || (string) $m->firma1Cargo !== '', 'whenClient' => 'function () { return false; }', 'message' => 'Escriba el nombre de la firma izquierda (sin nombre, la firma no sale).'],
            [['firma2Nombre'], 'required', 'when' => static fn (self $m) => (string) $m->firma2Prefijo !== '' || (string) $m->firma2Cargo !== '', 'whenClient' => 'function () { return false; }', 'message' => 'Escriba el nombre de la firma derecha (sin nombre, la firma no sale).'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'textoNota' => 'Nota',
            'firma1Prefijo' => 'Prefijo',
            'firma1Nombre' => 'Nombre',
            'firma1Cargo' => 'Cargo',
            'firma2Prefijo' => 'Prefijo',
            'firma2Nombre' => 'Nombre',
            'firma2Cargo' => 'Cargo',
            'pieIzquierdaTitulo' => 'Título',
            'pieIzquierdaLineas' => 'Renglones',
            'pieDerechaTitulo' => 'Título',
            'pieDerechaLineas' => 'Renglones',
        ];
    }
}
