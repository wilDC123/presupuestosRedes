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
     * Maximo de filas que devuelve buscar(). La pantalla avisa cuando se
     * alcanza, porque en ese caso puede haber mas resultados sin mostrar.
     */
    public const LIMITE_BUSQUEDA = 100;

    /**
     * Unico valor de CodigoEstado que se considera vigente. Hoy todas las
     * filas del catalogo estan en 'V', pero se filtra igual para que un item
     * dado de baja en SIGMA no se pueda agregar al subcatalogo.
     */
    public const ESTADO_VIGENTE = 'V';

    /**
     * Busca por texto libre entre los items vigentes. El texto se separa en
     * palabras y se exige que aparezcan TODAS (AND), cada una en Clase,
     * Descripcion o RamaComercial (OR), en cualquier orden. Asi "jack cat 6"
     * encuentra "JACK; TIPO RJ-45 HEMBRA AUTOPONCHABLE CAT 6; ...", que una
     * busqueda de la frase completa no encontraba.
     *
     * La condicion 'like' de Yii arma la consulta con parametros bindeados
     * y escapa los comodines % y _, asi que esto ya es seguro contra
     * inyeccion SQL sin necesidad de escapar el texto a mano.
     *
     * LIMITACION CONOCIDA (no es un bug): cada palabra se busca tal cual, asi
     * que "RJ45" no encuentra "RJ-45". Hay que escribirla como esta en SIGMA.
     *
     * NOTA DE RENDIMIENTO / PENDIENTE: un LIKE '%palabra%' no puede usar
     * indices, asi que SQL Server recorre toda la tabla por cada busqueda.
     * Es aceptable con el tamano actual de dbo.CatalogoSigma (decenas de
     * miles de filas), pero si la busqueda se vuelve lenta deberia migrarse
     * a un indice de Full-Text Search de SQL Server sobre dbo.CatalogoSigma
     * -- eso requiere coordinacion con el equipo tecnico institucional, ya
     * que esa tabla no pertenece al esquema `redes` y no se administra desde
     * las migraciones de este proyecto.
     *
     * @return static[]
     */
    public static function buscar(string $texto): array
    {
        $palabras = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY);

        if ($palabras === false || $palabras === []) {
            return [];
        }

        $query = self::find()->andWhere(['CodigoEstado' => self::ESTADO_VIGENTE]);

        // Un andWhere() por palabra: Yii los une con AND entre si.
        foreach ($palabras as $palabra) {
            $query->andWhere(['or',
                ['like', 'Clase', $palabra],
                ['like', 'Descripcion', $palabra],
                ['like', 'RamaComercial', $palabra],
            ]);
        }

        return $query
            ->orderBy(['Descripcion' => SORT_ASC])
            ->limit(self::LIMITE_BUSQUEDA)
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
