<?php

declare(strict_types=1);

namespace backend\models;

use DateTime;
use Yii;
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
 * @property int $id
 * @property int|null $idSubcatalogo Item de referencia (opcional): solo informativo.
 * @property int|null $idCategoria Clasificacion en la libreria (mismas categorias del subcatalogo).
 * @property string|null $codigoNet
 * @property string|null $titulo
 * @property string|null $dependencia
 * @property string|null $modeloSugerido
 * @property string|null $documentoRuta
 * @property string|null $fechaEmision
 * @property bool $activo
 *
 * @property-read SubcatalogoItem|null $subcatalogoItem
 * @property-read Categoria|null $categoria
 * @property-read ProyectoItem[] $proyectoItems
 * @property-read CotizacionDetalle[] $cotizacionDetalles
 */
class Especificacion extends ActiveRecord
{
    /**
     * Atributo virtual: no existe como columna en la tabla. Es el PDF/DOCX
     * que el usuario sube para (a) extraer metadatos automaticamente y
     * (b) guardarlo como documentoRuta definitivo al confirmar el formulario.
     * Separado de documentoRuta, que sigue siendo editable a mano como texto
     * (por si el documento ya vive en otro lugar y solo se quiere referenciar
     * la ruta, sin subir nada).
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
            [['idCategoria'], 'required'],
            [['idSubcatalogo', 'idCategoria'], 'integer'],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
            [['fechaEmision'], 'date', 'format' => 'php:Y-m-d'],
            [['codigoNet'], 'string', 'max' => 20],
            [['titulo'], 'string', 'max' => 300],
            [['dependencia', 'modeloSugerido'], 'string', 'max' => 200],
            [['documentoRuta'], 'string', 'max' => 500],
            [['archivoEspecificacion'], 'file', 'extensions' => 'pdf, docx', 'maxSize' => 10 * 1024 * 1024],
            [['idSubcatalogo'], 'exist', 'skipOnError' => true, 'targetClass' => SubcatalogoItem::class, 'targetAttribute' => ['idSubcatalogo' => 'id']],
            [['idCategoria'], 'exist', 'skipOnError' => true, 'targetClass' => Categoria::class, 'targetAttribute' => ['idCategoria' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idSubcatalogo' => 'Ítem de referencia (opcional)',
            'idCategoria' => 'Categoría',
            'codigoNet' => 'Código NET',
            'titulo' => 'Título',
            'dependencia' => 'Dependencia',
            'modeloSugerido' => 'Modelo sugerido',
            'documentoRuta' => 'Ruta del documento',
            'fechaEmision' => 'Fecha de emisión',
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
     * Nombre corto para listados: el titulo, o el codigo NET, o "#id".
     */
    public function nombre(): string
    {
        return $this->titulo ?: ($this->codigoNet ?: '#' . $this->id);
    }

    /**
     * Como se cita en los impresos ("Segun especificacion tecnica NET:012/26"):
     * el codigo NET si lo tiene; si no, el titulo.
     */
    public function referencia(): string
    {
        return $this->codigoNet ?: $this->nombre();
    }

    /**
     * Enlace para abrir el documento (null si no tiene). documentoRuta puede
     * ser un archivo subido (ruta relativa a backend/web) o un enlace externo
     * escrito a mano (ej. Google Drive), que se usa tal cual.
     */
    public function urlDocumento(): ?string
    {
        $ruta = trim((string) $this->documentoRuta);

        if ($ruta === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $ruta) === 1) {
            return $ruta;
        }

        return Yii::getAlias('@web/' . ltrim($ruta, '/'));
    }

    /**
     * fechaEmision permite NULL, pero el input tipo "date" envia '' cuando se
     * deja en blanco. SQL Server no puede convertir '' a date, asi que hay
     * que normalizar a null antes de guardar (mismo riesgo que idSigma en
     * SubcatalogoItem).
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // Los selects opcionales y el input de fecha envian '' cuando se
        // dejan en blanco; SQL Server no convierte '' a int ni a date.
        foreach (['fechaEmision', 'idSubcatalogo', 'idCategoria'] as $atributo) {
            if ($this->$atributo === '') {
                $this->$atributo = null;
            }
        }

        return true;
    }

    /**
     * El formulario muestra y recibe fechaEmision en dd/mm/aaaa (convencion
     * local), pero el validador 'date' de rules() y la columna date de SQL
     * Server esperan aaaa-mm-dd. Esto tiene que pasar en beforeValidate() --
     * no en beforeSave() -- porque el validador 'date' corre ANTES que
     * beforeSave() y rechazaria "11/08/2026" como formato invalido.
     */
    public function beforeValidate(): bool
    {
        $this->fechaEmision = self::convertirFechaFormularioABd($this->fechaEmision);

        return parent::beforeValidate();
    }

    /**
     * Convierte "dd/mm/aaaa" (o "d/m/aaaa") a "aaaa-mm-dd". Si el valor no
     * coincide con ese patron (ya viene en aaaa-mm-dd, esta vacio, o es
     * invalido) se devuelve sin cambios para que el validador 'date' decida.
     */
    private static function convertirFechaFormularioABd(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', trim($valor), $partes) === 1) {
            [, $dia, $mes, $anio] = $partes;

            return sprintf('%04d-%02d-%02d', (int) $anio, (int) $mes, (int) $dia);
        }

        return $valor;
    }

    /**
     * fechaEmision para mostrar en pantalla (dd/mm/aaaa), no para guardar.
     */
    public function fechaEmisionFormateada(): string
    {
        if (empty($this->fechaEmision)) {
            return '';
        }

        $fecha = DateTime::createFromFormat('Y-m-d', $this->fechaEmision);

        return $fecha !== false ? $fecha->format('d/m/Y') : $this->fechaEmision;
    }
}
