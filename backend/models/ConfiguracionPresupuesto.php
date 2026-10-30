<?php

declare(strict_types=1);

namespace backend\models;

use RuntimeException;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ConfiguracionPresupuesto": UNA sola fila
 * (id = 1, lo garantiza un CHECK en la base) con lo que lleva el PDF del
 * presupuesto despues del total: nota, las dos firmas del "control de
 * elaboracion" del lineamiento RD-GD-LI-001 (firma 1 = elaborado por, firma
 * 2 = aprobado por, con su V°B°). La banda de pie con los contactos se quito
 * el 2026-10-30 (revision del Ing. de DTIC: un documento de la gestion
 * documental no lleva pie de pagina). Las firmas las usa tambien la lista de
 * materiales al aprobarse (ListaItemsService::aprobarVersion()).
 * Editable por el encargado (presupuesto/configuracion).
 *
 * El PDF se arma al imprimir, asi que un cambio aqui tambien se ve al
 * reimprimir presupuestos anteriores, SALVO las firmas de los aprobados:
 * esas se copian al presupuesto al aprobarlo (Presupuesto::firmaDeControl()).
 *
 * @property int $id
 * @property string|null $textoNota
 * @property string|null $firma1Prefijo
 * @property string|null $firma1Nombre
 * @property string|null $firma1Cargo
 * @property string|null $firma2Prefijo
 * @property string|null $firma2Nombre
 * @property string|null $firma2Cargo
 */
class ConfiguracionPresupuesto extends ActiveRecord
{
    private const TEXTOS = [
        'textoNota',
        'firma1Prefijo', 'firma1Nombre', 'firma1Cargo',
        'firma2Prefijo', 'firma2Nombre', 'firma2Cargo',
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

    public function rules(): array
    {
        return [
            [self::TEXTOS, 'trim'],
            // Todo es opcional: un campo vacio se guarda como NULL y no sale.
            [self::TEXTOS, 'default', 'value' => null],
            // El cargo sale siempre en mayusculas (mb_: tambien con tildes).
            [['firma1Cargo', 'firma2Cargo'], 'filter', 'filter' => static fn ($valor) => is_string($valor) ? mb_strtoupper($valor, 'UTF-8') : $valor],
            [['textoNota'], 'string', 'max' => 500],
            [['firma1Prefijo', 'firma2Prefijo'], 'string', 'max' => 20],
            [['firma1Nombre', 'firma2Nombre'], 'string', 'max' => 150],
            [['firma1Cargo', 'firma2Cargo'], 'string', 'max' => 200],
            // Un prefijo o un cargo sin nombre no saldria en el PDF.
            [['firma1Nombre'], 'required', 'when' => static fn (self $m) => (string) $m->firma1Prefijo !== '' || (string) $m->firma1Cargo !== '', 'whenClient' => 'function () { return false; }', 'message' => 'Escriba el nombre de quien elabora (sin nombre, el prefijo y el cargo no salen).'],
            [['firma2Nombre'], 'required', 'when' => static fn (self $m) => (string) $m->firma2Prefijo !== '' || (string) $m->firma2Cargo !== '', 'whenClient' => 'function () { return false; }', 'message' => 'Escriba el nombre de quien aprueba (sin nombre, el prefijo y el cargo no salen).'],
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
        ];
    }
}
