<?php

declare(strict_types=1);

namespace backend\models;

use backend\components\NumeracionEspecificacion;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.EspecificacionCaracteristica": una fila tecnica
 * de una version de la ficha (en el formulario NET, una fila de la seccion
 * "I. Requisitos necesarios").
 *
 * Las filas de "marca y modelo" y "URL" no se guardan aqui: el formulario las
 * agrega siempre.
 *
 * Un SUBTITULO (esSubtitulo, 2026-11-03) es una fila de un solo texto (valor,
 * sin nombre), en negrita. El NIVEL (1 a 3) dice cuanto va adentro y de el
 * sale el numero: 1.4 (nivel 1), 1.4.1 (2), 1.4.1.1 (3). Ver
 * NumeracionEspecificacion.
 *
 * @property int $id
 * @property int $idVersion
 * @property int $orden
 * @property string|null $nombre ej. "Calibre del conductor" (opcional: hay filas que son un solo texto).
 * @property string $valor ej. "23 AWG"; en un subtitulo, su texto ("Hardware").
 * @property bool $esSubtitulo
 * @property int $nivel 1 a NumeracionEspecificacion::NIVEL_MAXIMO
 *
 * @property-read EspecificacionVersion $version
 */
class EspecificacionCaracteristica extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.EspecificacionCaracteristica';
    }

    public function rules(): array
    {
        return [
            [['nombre', 'valor'], 'trim'],
            [['nombre'], 'default', 'value' => null],
            [['idVersion', 'valor'], 'required'],
            [['idVersion', 'orden'], 'integer'],
            [['orden'], 'default', 'value' => 0],
            [['esSubtitulo'], 'boolean'],
            [['esSubtitulo'], 'default', 'value' => false],
            [['nivel'], 'default', 'value' => 1],
            [['nivel'], 'integer', 'min' => 1, 'max' => NumeracionEspecificacion::NIVEL_MAXIMO],
            [['nombre'], 'string', 'max' => 200],
            [['valor'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idVersion' => 'Versión',
            'orden' => 'Orden',
            'nombre' => 'Característica',
            'valor' => 'Valor',
            'esSubtitulo' => 'Subtítulo',
            'nivel' => 'Nivel',
        ];
    }

    public function getVersion(): ActiveQuery
    {
        return $this->hasOne(EspecificacionVersion::class, ['id' => 'idVersion']);
    }

    /**
     * Como se imprime: "Calibre del conductor: 23 AWG", o solo el valor si la
     * fila no tiene nombre (o es un subtitulo).
     */
    public function texto(): string
    {
        return !$this->esSubtitulo && $this->nombre !== null && $this->nombre !== ''
            ? $this->nombre . ': ' . $this->valor
            : $this->valor;
    }

    /**
     * true si una fila importada de un Word parece un subtitulo: sin nombre,
     * una sola linea, de 40 caracteres o menos y sin punto final. Es el mismo
     * criterio que uso la migracion m261103_141729 para marcar las que ya
     * estaban (alli tambien se exige una fila despues); en pantalla se
     * corrige con la casilla "Subtítulo".
     */
    public static function pareceSubtitulo(?string $nombre, string $valor): bool
    {
        $valor = trim($valor);

        return ($nombre === null || trim($nombre) === '')
            && $valor !== ''
            && mb_strlen($valor) <= 40
            && preg_match('/\R/u', $valor) !== 1
            && !str_ends_with($valor, '.');
    }
}
