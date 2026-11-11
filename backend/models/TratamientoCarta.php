<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.TratamientoCarta": los tratamientos con los
 * que se encabeza al destinatario de la carta de entrega ("Señora:").
 * Se administran en "Configuración de la carta" (CartaService::
 * guardarConfiguracion(), que reemplaza la lista entera).
 *
 * La carta guarda una COPIA del nombre (CartaPresupuesto.tratamiento), sin
 * FK: quitar uno de aqui no cambia las cartas ya escritas.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $femenino El cargo sugerido va con DECANA.
 * @property int $orden
 */
class TratamientoCarta extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.TratamientoCarta';
    }

    /**
     * Todos, en el orden de la lista.
     *
     * @return self[]
     */
    public static function lista(): array
    {
        /** @var self[] $lista */
        $lista = self::find()->orderBy(['orden' => SORT_ASC, 'id' => SORT_ASC])->all();

        return $lista;
    }

    /**
     * nombre => femenino, en el orden de la lista.
     *
     * @return array<string, bool>
     */
    public static function opciones(): array
    {
        $opciones = [];
        foreach (self::lista() as $tratamiento) {
            $opciones[$tratamiento->nombre] = (bool) $tratamiento->femenino;
        }

        return $opciones;
    }

    /**
     * Si el cargo va en femenino con este tratamiento. Uno que ya no esta en
     * la lista (carta de antes) cuenta como masculino.
     */
    public static function esFemenino(string $nombre): bool
    {
        return self::opciones()[$nombre] ?? false;
    }
}
