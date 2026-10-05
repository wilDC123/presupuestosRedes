<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.Proyecto".
 *
 * REGLA DE NEGOCIO: una ampliacion (Fase 2, 3...) NO es una version del mismo
 * proyecto: es un proyecto NUEVO, con su propio codigo, enlazado al de la
 * fase anterior por idProyectoAnterior. Un proyecto cuyo presupuesto fue
 * aprobado nunca se modifica.
 *
 * @property int $id
 * @property string|null $codigo Generado por ProyectoService al crear (RD-PI-PT-NN).
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $tipo Codigo del tipo de proyecto (ver ParametrosProyectoService).
 * @property string|null $codigoFacultad
 * @property string|null $facultad Nombre de la facultad, copiado al guardar.
 * @property string|null $codigoEdificio
 * @property string|null $edificio Nombre del edificio, copiado al guardar.
 * @property int|null $idProyectoAnterior
 * @property int $numeroFase
 * @property string $fechaCreacion
 *
 * @property-read ProyectoBloque[] $bloques
 * @property-read ProyectoProyectista[] $proyectistas
 * @property-read Proyecto|null $proyectoAnterior
 * @property-read Proyecto[] $fasesSiguientes
 * @property-read Presupuesto|null $presupuesto
 */
class Proyecto extends ActiveRecord
{
    /**
     * Atributos virtuales: no son columnas de redes.Proyecto. Reciben del
     * formulario los bloques y proyectistas elegidos; ProyectoService los
     * guarda en redes.ProyectoBloque y redes.ProyectoProyectista.
     *
     * @var string[]|string codigos de bloque
     */
    public $codigosBloque = [];

    /** @var string[]|string nombres de proyectistas */
    public $nombresProyectista = [];

    public static function tableName(): string
    {
        return 'redes.Proyecto';
    }

    /**
     * codigo, facultad, edificio y numeroFase NO tienen regla a proposito:
     * un atributo sin reglas no es "seguro" y load() lo ignora, asi que no
     * se pueden enviar desde el formulario. Los calcula ProyectoService.
     */
    public function rules(): array
    {
        return [
            [['nombre'], 'required'],
            [['nombre'], 'string', 'max' => 300],
            [['descripcion'], 'string', 'max' => 1000],
            [['tipo'], 'string', 'max' => 50],
            [['codigoFacultad', 'codigoEdificio'], 'string', 'max' => 30],
            [['idProyectoAnterior'], 'integer'],
            [['idProyectoAnterior'], 'exist', 'skipOnError' => true, 'targetClass' => self::class, 'targetAttribute' => ['idProyectoAnterior' => 'id']],
            // 'each' aplica la regla indicada a cada elemento del arreglo.
            [['codigosBloque'], 'each', 'rule' => ['string', 'max' => 30]],
            [['nombresProyectista'], 'each', 'rule' => ['string', 'max' => 200]],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'codigo' => 'Código de proyecto',
            'nombre' => 'Nombre del proyecto',
            'descripcion' => 'Descripción',
            'tipo' => 'Tipo de proyecto',
            'codigoFacultad' => 'Facultad',
            'facultad' => 'Facultad',
            'codigoEdificio' => 'Edificio',
            'edificio' => 'Edificio',
            'codigosBloque' => 'Bloques involucrados',
            'nombresProyectista' => 'Proyectistas asignados',
            'idProyectoAnterior' => 'Proyecto de fase anterior (opcional)',
            'numeroFase' => 'Fase',
            'fechaCreacion' => 'Fecha de creación',
        ];
    }

    public function getBloques(): ActiveQuery
    {
        return $this->hasMany(ProyectoBloque::class, ['idProyecto' => 'id'])->orderBy(['nombre' => SORT_ASC]);
    }

    public function getProyectistas(): ActiveQuery
    {
        return $this->hasMany(ProyectoProyectista::class, ['idProyecto' => 'id'])->orderBy(['id' => SORT_ASC]);
    }

    public function getProyectoAnterior(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'idProyectoAnterior']);
    }

    /**
     * Proyectos que declararon a este como su fase anterior.
     */
    public function getFasesSiguientes(): ActiveQuery
    {
        return $this->hasMany(self::class, ['idProyectoAnterior' => 'id']);
    }

    /**
     * hasOne porque cada proyecto tiene un solo presupuesto (indice unico
     * sobre redes.Presupuesto.idProyecto).
     */
    public function getPresupuesto(): ActiveQuery
    {
        return $this->hasOne(Presupuesto::class, ['idProyecto' => 'id']);
    }

    /**
     * "Facultad / Edificio / Bloque A, Bloque B" para mostrar en pantalla y
     * en el PDF. Omite las partes que esten vacias.
     */
    public function ubicacion(): string
    {
        $bloques = implode(', ', array_map(static fn (ProyectoBloque $bloque) => $bloque->nombre, $this->bloques));

        return implode(' / ', array_filter([$this->facultad, $this->edificio, $bloques]));
    }

    /**
     * "RD-PI-PT-05 — Nombre" para listas desplegables y enlaces.
     */
    public function etiqueta(): string
    {
        return ($this->codigo ? $this->codigo . ' — ' : '') . $this->nombre;
    }
}
