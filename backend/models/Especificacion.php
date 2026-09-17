<?php

declare(strict_types=1);

namespace backend\models;

use DateTime;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * Modelo para la tabla "redes.Especificacion".
 *
 * @property int $id
 * @property int $idSubcatalogo
 * @property string|null $codigoNet
 * @property string|null $titulo
 * @property string|null $dependencia
 * @property string|null $modeloSugerido
 * @property string|null $documentoRuta
 * @property string|null $fechaEmision
 * @property bool $activo
 *
 * @property-read SubcatalogoItem $subcatalogoItem
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
            [['idSubcatalogo'], 'required'],
            [['idSubcatalogo'], 'integer'],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
            [['fechaEmision'], 'date', 'format' => 'php:Y-m-d'],
            [['codigoNet'], 'string', 'max' => 20],
            [['titulo'], 'string', 'max' => 300],
            [['dependencia', 'modeloSugerido'], 'string', 'max' => 200],
            [['documentoRuta'], 'string', 'max' => 500],
            [['archivoEspecificacion'], 'file', 'extensions' => 'pdf, docx', 'maxSize' => 10 * 1024 * 1024],
            [['idSubcatalogo'], 'exist', 'skipOnError' => true, 'targetClass' => SubcatalogoItem::class, 'targetAttribute' => ['idSubcatalogo' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idSubcatalogo' => 'Ítem de subcatálogo',
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

        if ($this->fechaEmision === '') {
            $this->fechaEmision = null;
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
