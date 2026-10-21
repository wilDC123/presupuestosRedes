<?php

declare(strict_types=1);

namespace backend\models;

use DateTime;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * Modelo para la tabla "redes.CartaPresupuesto": la carta de entrega de UN
 * presupuesto (como maximo una, indice unico).
 *
 * Guarda su propio texto (copiado de ConfiguracionCarta al crearla y
 * corregido por el encargado): se puede escribir con el presupuesto aun "en
 * proceso" e imprimir despues. Los marcadores {oficio} y {numeroPresupuesto}
 * quedan en el texto y se reemplazan AL IMPRIMIR con los del presupuesto
 * (CartaService::textoParaImprimir()).
 *
 * @property int $id
 * @property int $idPresupuesto
 * @property string $fecha aaaa-mm-dd en la base; el formulario usa dd/mm/aaaa
 * @property string $tratamiento Ver TRATAMIENTOS.
 * @property string $destinatarioNombre Con su grado, ej. "Msc. María Esther Santos Parraga".
 * @property string $destinatarioCargo En mayusculas.
 * @property string $referencia En mayusculas, sin el "Ref." (lo pone el PDF).
 * @property string|null $saludo
 * @property string $cuerpo Parrafos separados por una linea en blanco.
 * @property string|null $despedida
 * @property string|null $firma1Prefijo
 * @property string|null $firma1Nombre
 * @property string|null $firma1Cargo
 * @property string|null $firma2Prefijo
 * @property string|null $firma2Nombre
 * @property string|null $firma2Cargo
 * @property string|null $firma3Prefijo
 * @property string|null $firma3Nombre
 * @property string|null $firma3Cargo
 * @property string|null $copia
 * @property string|null $adjunto
 * @property string $fechaCreacion
 * @property string|null $fechaModificacion
 *
 * @property-read Presupuesto $presupuesto
 */
class CartaPresupuesto extends ActiveRecord
{
    use FirmasDeCarta;

    /**
     * Como se encabeza el destinatario ("Señora:"). El cargo sugerido
     * cambia con el: DECANO / DECANA (ver CartaService::cargoSugerido()).
     */
    public const TRATAMIENTOS = ['Señor', 'Señora', 'Señores', 'Señoras'];

    /**
     * Marcadores que la carta reemplaza (ver CartaService), escritos como se
     * reconocen. Ver normalizarMarcadores().
     */
    public const MARCADORES = ['{proyecto}', '{facultad}', '{oficio}', '{numeroPresupuesto}', '{especificaciones}', '{anio}'];

    /**
     * Devuelve cada marcador conocido a su forma original, sin importar como
     * se escribio ({PROYECTO}, {Proyecto} -> {proyecto}). Asi un texto pasado
     * a mayusculas, o escrito a mano, sigue funcionando.
     */
    public static function normalizarMarcadores(string $texto): string
    {
        $porNombre = [];
        foreach (self::MARCADORES as $marcador) {
            $porNombre[mb_strtolower($marcador, 'UTF-8')] = $marcador;
        }

        return preg_replace_callback('/\{[A-Za-z]+\}/', static fn (array $m) => $porNombre[mb_strtolower($m[0], 'UTF-8')] ?? $m[0], $texto);
    }

    /**
     * Mayusculas para la referencia y el cargo, sin romper los marcadores.
     */
    public static function mayusculas(?string $texto): ?string
    {
        return is_string($texto) ? self::normalizarMarcadores(mb_strtoupper($texto, 'UTF-8')) : $texto;
    }

    public static function tableName(): string
    {
        return 'redes.CartaPresupuesto';
    }

    public function rules(): array
    {
        $textos = ['tratamiento', 'destinatarioNombre', 'destinatarioCargo', 'referencia', 'saludo', 'cuerpo', 'despedida', 'copia', 'adjunto'];

        return array_merge([
            [$textos, 'trim'],
            [['saludo', 'despedida', 'copia', 'adjunto'], 'default', 'value' => null],
            [['fecha', 'tratamiento', 'destinatarioNombre', 'destinatarioCargo', 'referencia', 'cuerpo'], 'required'],
            [['fecha'], 'date', 'format' => 'php:Y-m-d', 'message' => 'Escriba la fecha como dd/mm/aaaa.'],
            [['tratamiento'], 'in', 'range' => self::TRATAMIENTOS],
            [['destinatarioCargo', 'referencia'], 'filter', 'filter' => [self::class, 'mayusculas']],
            [['destinatarioNombre'], 'string', 'max' => 150],
            [['destinatarioCargo'], 'string', 'max' => 200],
            [['referencia'], 'string', 'max' => 500],
            [['saludo', 'despedida'], 'string', 'max' => 150],
            [['cuerpo'], 'string', 'max' => 4000],
            [['copia'], 'string', 'max' => 200],
            [['adjunto'], 'string', 'max' => 300],
        ], $this->reglasFirmas());
    }

    public function attributeLabels(): array
    {
        return [
            'fecha' => 'Fecha de la carta',
            'tratamiento' => 'Tratamiento',
            'destinatarioNombre' => 'Destinatario',
            'destinatarioCargo' => 'Cargo del destinatario',
            'referencia' => 'Referencia',
            'saludo' => 'Saludo',
            'cuerpo' => 'Cuerpo de la carta',
            'despedida' => 'Despedida',
            'copia' => 'Copia',
            'adjunto' => 'Adjunto',
        ] + self::etiquetasFirmas();
    }

    public function getPresupuesto(): ActiveQuery
    {
        return $this->hasOne(Presupuesto::class, ['id' => 'idPresupuesto']);
    }

    /**
     * El formulario recibe la fecha en dd/mm/aaaa; el validador 'date' y la
     * columna esperan aaaa-mm-dd (igual que Presupuesto).
     */
    public function beforeValidate(): bool
    {
        $this->fecha = Presupuesto::convertirFechaFormularioABd($this->fecha);

        return parent::beforeValidate();
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if (!$insert) {
            $this->fechaModificacion = new Expression('GETDATE()');
        }

        return true;
    }

    public function fechaFormateada(): string
    {
        $fecha = DateTime::createFromFormat('Y-m-d', (string) $this->fecha);

        return $fecha !== false ? $fecha->format('d/m/Y') : (string) $this->fecha;
    }

    /**
     * Los parrafos del cuerpo (separados por una o mas lineas en blanco).
     *
     * @return string[]
     */
    public static function parrafos(string $texto): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R\s*\R/', $texto)),
            static fn (string $parrafo) => $parrafo !== ''
        ));
    }
}
