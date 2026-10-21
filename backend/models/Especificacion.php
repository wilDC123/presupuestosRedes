<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * Modelo para la tabla "redes.Especificacion": una ficha de la LIBRERIA de
 * especificaciones tecnicas.
 *
 * La especificacion no pertenece a un item ni a un proyecto: se clasifica
 * por categoria y se ENLAZA a los items de la lista de un proyecto
 * (ProyectoItem::idEspecificacion). Siempre es opcional.
 *
 * La ficha solo tiene lo que no cambia entre versiones (titulo, categoria,
 * activo). El contenido tecnico vive en sus VERSIONES, y lo que se entrega al
 * proveedor (codigo NET, cantidad, dependencia, fecha) en cada EMISION.
 *
 * @property int $id
 * @property int|null $idSubcatalogo Item de referencia (opcional): solo informativo.
 * @property int|null $idCategoria Clasificacion en la libreria (mismas categorias del subcatalogo).
 * @property string|null $titulo
 * @property bool $activo
 *
 * @property-read SubcatalogoItem|null $subcatalogoItem
 * @property-read Categoria|null $categoria
 * @property-read ProyectoItem[] $proyectoItems
 * @property-read CotizacionDetalle[] $cotizacionDetalles
 * @property-read EspecificacionVersion[] $versiones De la mas nueva a la mas antigua.
 * @property-read EspecificacionVersion|null $versionActual La mas nueva: la que usan las emisiones nuevas.
 */
class Especificacion extends ActiveRecord
{
    /**
     * Atributo virtual: no existe como columna en la tabla. Es el PDF/DOCX
     * que el usuario sube para (a) extraer los datos automaticamente y
     * (b) guardarlo como adjunto de la version al confirmar el formulario.
     * Separado de EspecificacionVersion::adjuntoRuta, que sigue siendo
     * editable a mano como texto (por si el documento ya vive en otro lugar y
     * solo se quiere referenciar la ruta, sin subir nada).
     */
    public ?UploadedFile $archivoEspecificacion = null;

    public static function tableName(): string
    {
        return 'redes.Especificacion';
    }

    public function rules(): array
    {
        return [
            // La columna permite NULL (fichas antiguas o importadas), pero
            // toda ficha creada o editada desde el formulario queda clasificada.
            [['titulo'], 'trim'],
            [['idCategoria', 'titulo'], 'required'],
            [['idSubcatalogo', 'idCategoria'], 'integer'],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
            [['titulo'], 'string', 'max' => 300],
            [['archivoEspecificacion'], 'file', 'extensions' => 'pdf, docx', 'maxSize' => 10 * 1024 * 1024],
            // El item de referencia tiene que estar activo, salvo que la
            // ficha ya lo tuviera guardado (se sigue editando aunque despues
            // se haya desactivado). isAttributeChanged(..., false): el "3" del
            // formulario es igual al 3 guardado.
            [['idSubcatalogo'], 'exist', 'skipOnError' => true, 'targetClass' => SubcatalogoItem::class, 'targetAttribute' => ['idSubcatalogo' => 'id'],
                'filter' => ['activo' => true],
                'when' => static fn (self $model) => $model->isAttributeChanged('idSubcatalogo', false),
                'message' => 'El ítem de referencia elegido no existe o está inactivo.'],
            [['idCategoria'], 'exist', 'skipOnError' => true, 'targetClass' => Categoria::class, 'targetAttribute' => ['idCategoria' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idSubcatalogo' => 'Ítem de referencia (opcional)',
            'idCategoria' => 'Categoría',
            'titulo' => 'Título',
            'activo' => 'Activo',
            'archivoEspecificacion' => 'Documento de especificación (PDF o DOCX)',
        ];
    }

    /**
     * archivoEspecificacion queda fuera de los atributos "safe" por el mismo
     * motivo que documentoFile en Cotizacion: la regla 'file' lo hace
     * validable pero tambien asignable en masa, y si load() encuentra un
     * valor vacio con ese nombre en $_POST intenta asignar un string a una
     * propiedad tipada como ?UploadedFile y explota con TypeError. El
     * controlador lo asigna explicitamente con UploadedFile::getInstance()
     * justo despues de load().
     */
    public function safeAttributes(): array
    {
        return array_values(array_diff(parent::safeAttributes(), ['archivoEspecificacion']));
    }

    public function getSubcatalogoItem(): ActiveQuery
    {
        return $this->hasOne(SubcatalogoItem::class, ['id' => 'idSubcatalogo']);
    }

    public function getCategoria(): ActiveQuery
    {
        return $this->hasOne(Categoria::class, ['id' => 'idCategoria']);
    }

    /**
     * Items de listas de proyecto que tienen enlazada esta especificacion.
     */
    public function getProyectoItems(): ActiveQuery
    {
        return $this->hasMany(ProyectoItem::class, ['idEspecificacion' => 'id']);
    }

    /**
     * Lineas de cotizacion que citan esta especificacion.
     */
    public function getCotizacionDetalles(): ActiveQuery
    {
        return $this->hasMany(CotizacionDetalle::class, ['idEspecificacion' => 'id']);
    }

    /**
     * Versiones del contenido tecnico, de la mas nueva a la mas antigua.
     */
    public function getVersiones(): ActiveQuery
    {
        return $this->hasMany(EspecificacionVersion::class, ['idEspecificacion' => 'id'])
            ->orderBy(['numero' => SORT_DESC]);
    }

    /**
     * La version mas nueva. Funciona tambien con with('versionActual'): en
     * una relacion hasOne, Yii se queda con la PRIMERA fila de cada ficha, y
     * el orden descendente hace que esa sea la de numero mas alto.
     */
    public function getVersionActual(): ActiveQuery
    {
        return $this->hasOne(EspecificacionVersion::class, ['idEspecificacion' => 'id'])
            ->orderBy(['numero' => SORT_DESC]);
    }

    /**
     * Nombre corto para listados: el titulo, o "#id". Como se cita en los
     * impresos (con su codigo NET) lo da ProyectoItem::referenciaEspecificacion().
     */
    public function nombre(): string
    {
        return $this->titulo ?: '#' . $this->id;
    }

    /**
     * Enlace para abrir el documento adjunto de la VERSION ACTUAL (null si no
     * tiene). Ver EspecificacionVersion::urlAdjunto().
     */
    public function urlDocumento(): ?string
    {
        return $this->versionActual?->urlAdjunto();
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // Los selects opcionales envian '' cuando se dejan en blanco; SQL
        // Server no convierte '' a int.
        foreach (['idSubcatalogo', 'idCategoria'] as $atributo) {
            if ($this->$atributo === '') {
                $this->$atributo = null;
            }
        }

        return true;
    }
}
