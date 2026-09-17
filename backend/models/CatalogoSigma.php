<?php

declare(strict_types=1);

namespace backend\models;

use yii\base\NotSupportedException;
use yii\db\ActiveRecord;

/**
 * Modelo de SOLO LECTURA para "dbo.CatalogoSigma": el catálogo nacional del
 * gobierno (~72,000 ítems, actualmente solo 509 accesibles en desarrollo).
 * Es una tabla institucional externa al esquema `redes` -- NUNCA se hace
 * INSERT, UPDATE ni DELETE sobre ella desde este sistema.
 *
 * insert()/update()/delete() estan sobreescritos para lanzar una excepcion
 * si algo llega a llamarlos por error, como segunda barrera ademas de no
 * exponer ningun formulario de escritura contra esta tabla.
 *
 * La tabla no tiene una primary key definida en la base de datos (es
 * institucional, no la administramos nosotros), por eso primaryKey() se
 * declara a mano con IdSigma en vez de dejar que Yii la detecte del schema.
 *
 * @property string $IdSigma
 * @property string $Clase
 * @property string $Descripcion
 * @property string $RamaComercial
 * @property string|null $Especificacion
 * @property string|null $IdGasto
 * @property string|null $PrecioReferencial
 * @property string|null $CodigoEstado
 * @property string|null $FechaHoraRegistro
 * @property string|null $CodigoUsuario
 */
class CatalogoSigma extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'dbo.CatalogoSigma';
    }

    public static function primaryKey(): array
    {
        return ['IdSigma'];
    }

    /**
     * Busca por texto libre en Descripcion, RamaComercial y Clase con un
     * LIKE simple. La condicion 'like' de Yii arma la consulta con
     * parametros bindeados automaticamente, asi que esto ya es seguro
     * contra inyeccion SQL sin necesidad de escapar el texto a mano.
     *
     * NOTA DE RENDIMIENTO / PENDIENTE: este LIKE simple es aceptable hoy
     * porque en desarrollo solo hay 509 filas accesibles en
     * dbo.CatalogoSigma. Si en producción se habilita el catálogo
     * institucional completo (decenas de miles de filas), esta búsqueda
     * debería migrarse a un índice de Full-Text Search de SQL Server sobre
     * dbo.CatalogoSigma para mantener buen rendimiento -- eso requiere
     * coordinación con el equipo técnico institucional, ya que esa tabla no
     * pertenece al esquema `redes` y no se administra desde las migraciones
     * de este proyecto.
     *
     * @return static[]
     */
    public static function buscar(string $texto): array
    {
        $texto = trim($texto);

        if ($texto === '') {
            return [];
        }

        return self::find()
            ->andWhere(['or',
                ['like', 'Descripcion', $texto],
                ['like', 'RamaComercial', $texto],
                ['like', 'Clase', $texto],
            ])
            ->orderBy(['Descripcion' => SORT_ASC])
            ->limit(100)
            ->all();
    }

    /**
     * @throws NotSupportedException siempre -- ver comentario de clase.
     */
    public function insert($runValidation = true, $attributes = null): bool
    {
        throw new NotSupportedException('dbo.CatalogoSigma es de solo lectura: no se permite insert().');
    }

    /**
     * @throws NotSupportedException siempre -- ver comentario de clase.
     */
    public function update($runValidation = true, $attributeNames = null): int|false
    {
        throw new NotSupportedException('dbo.CatalogoSigma es de solo lectura: no se permite update().');
    }

    /**
     * @throws NotSupportedException siempre -- ver comentario de clase.
     */
    public function delete(): int|false
    {
        throw new NotSupportedException('dbo.CatalogoSigma es de solo lectura: no se permite delete().');
    }
}
