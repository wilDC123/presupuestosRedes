<?php

declare(strict_types=1);

namespace backend\models;

use backend\components\ArchivoSubido;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * Modelo para la tabla "redes.EspecificacionVersion": una version (v1, v2...)
 * del contenido tecnico de una ficha de la libreria.
 *
 * REGLA DE NEGOCIO: una version que ya se uso en una emision NET no se
 * modifica nunca (el documento emitido apunta a ella y debe seguir diciendo lo
 * mismo). Cambiar la ficha crea la version siguiente; lo controla
 * EspecificacionService::guardarVersion().
 *
 * @property int $id
 * @property int $idEspecificacion
 * @property int $numero 1, 2, 3... dentro de la ficha.
 * @property string|null $modeloSugerido ej. "Cisco C1200-24P-4G o superior".
 * @property string|null $garantia NULL = se usa la garantia por defecto de la configuracion.
 * @property string|null $adjuntoRuta Datasheet o documento original (archivo subido o enlace externo).
 * @property string|null $notaCambio Que cambio respecto de la version anterior.
 * @property string $fechaCreacion
 *
 * @property-read Especificacion $especificacion
 * @property-read EspecificacionCaracteristica[] $caracteristicas
 * @property-read EspecificacionEmision[] $emisiones
 */
class EspecificacionVersion extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.EspecificacionVersion';
    }

    public function rules(): array
    {
        return [
            [['modeloSugerido', 'garantia', 'adjuntoRuta', 'notaCambio'], 'trim'],
            // '' -> NULL: un campo vacio del formulario no es un texto.
            [['modeloSugerido', 'garantia', 'adjuntoRuta', 'notaCambio'], 'default', 'value' => null],
            [['idEspecificacion', 'numero'], 'required'],
            [['idEspecificacion', 'numero'], 'integer'],
            [['modeloSugerido'], 'string', 'max' => 200],
            [['garantia', 'adjuntoRuta'], 'string', 'max' => 500],
            [['notaCambio'], 'string', 'max' => 300],
            [['idEspecificacion'], 'exist', 'skipOnError' => true, 'targetClass' => Especificacion::class, 'targetAttribute' => ['idEspecificacion' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'idEspecificacion' => 'Especificación',
            'numero' => 'Versión',
            'modeloSugerido' => 'Modelo sugerido',
            'garantia' => 'Garantía',
            'adjuntoRuta' => 'Documento adjunto',
            'notaCambio' => 'Nota del cambio',
            'fechaCreacion' => 'Fecha',
        ];
    }

    public function getEspecificacion(): ActiveQuery
    {
        return $this->hasOne(Especificacion::class, ['id' => 'idEspecificacion']);
    }

    public function getCaracteristicas(): ActiveQuery
    {
        return $this->hasMany(EspecificacionCaracteristica::class, ['idVersion' => 'id'])
            ->orderBy(['orden' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getEmisiones(): ActiveQuery
    {
        return $this->hasMany(EspecificacionEmision::class, ['idVersion' => 'id']);
    }

    /**
     * true si ya se uso en alguna emision NET (vigente, reemplazada o
     * anulada: todas fueron documentos emitidos). Una version bloqueada no se
     * modifica.
     */
    public function estaBloqueada(): bool
    {
        return !$this->isNewRecord && $this->getEmisiones()->exists();
    }

    /**
     * Como se muestra: "v2".
     */
    public function etiqueta(): string
    {
        return 'v' . $this->numero;
    }

    /**
     * Enlace para abrir el adjunto (null si no tiene): un archivo subido, que
     * entrega EspecificacionController::actionAdjunto() (exige sesion; el
     * archivo esta fuera de la carpeta publica, ver ArchivoSubido), o un
     * enlace externo escrito a mano (ej. Google Drive), que se usa tal cual.
     */
    public function urlAdjunto(): ?string
    {
        $ruta = trim((string) $this->adjuntoRuta);

        if ($ruta === '') {
            return null;
        }

        return ArchivoSubido::esEnlaceExterno($ruta)
            ? $ruta
            : Url::to(['/especificacion/adjunto', 'id' => $this->id]);
    }
}
