<?php

declare(strict_types=1);

namespace backend\models;

use backend\components\VersionDocumento;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ListaMaterialesAprobacion": una VERSION
 * EMITIDA de la "Lista de materiales y condiciones previas" del proyecto
 * (revision del Ing. de DTIC del 2026-10-29, paso 8b).
 *
 * Desde la revision del 2026-11-05 (punto 7) la lista NO se aprueba (la que
 * se aprueba es el presupuesto): solo lleva "Elaborado por". La tabla y sus
 * columnas conservan el nombre de antes ("Aprobacion", fechaAprobacion), pero
 * una fila es la version que se ENVIO a cotizacion, y fechaAprobacion la
 * fecha de ese envio. Cada "Enviar a cotizacion" emite la version siguiente
 * (1.0, y una mas despues de cada orden de cambio); ver
 * ListaItemsService::enviarACotizacion(). Una fila nunca se modifica.
 * Las firmas son la COPIA de las de la configuracion del presupuesto ese dia
 * (trait FirmasDeControl, sin reglas: no llegan de formularios); el impreso
 * usa solo la 1 (elaborado por).
 *
 * Desde el 2026-11-05 la lista tiene codigo propio, RD-<proceso>-LM-NNN
 * (Proyecto.codigoLista); antes llevaba el del proyecto.
 *
 * @property int $id
 * @property int $idProyecto
 * @property int $version
 * @property string|null $fechaAprobacion Y-m-d del envio a cotizacion; NULL = enviada antes de este registro.
 * @property string|null $usuario Copia del nombre de usuario; NULL = antes de este registro.
 * @property string|null $firma1Prefijo
 * @property string|null $firma1Nombre
 * @property string|null $firma1Cargo
 * @property string|null $firma2Prefijo
 * @property string|null $firma2Nombre
 * @property string|null $firma2Cargo
 *
 * @property-read Proyecto $proyecto
 */
class ListaMaterialesAprobacion extends ActiveRecord
{
    use FirmasDeControl;

    /** Titulo del documento, en la cabecera del PDF y en las pantallas. */
    public const TITULO = 'LISTA DE MATERIALES Y CONDICIONES PREVIAS';

    public static function tableName(): string
    {
        return 'redes.ListaMaterialesAprobacion';
    }

    public function getProyecto(): ActiveQuery
    {
        return $this->hasOne(Proyecto::class, ['id' => 'idProyecto']);
    }

    /** "1.0", "1.1"... como en la casilla "Versión" de la cabecera (ver VersionDocumento). */
    public static function versionTexto(int $version): string
    {
        return VersionDocumento::texto($version);
    }

    /** dd/mm/aaaa, o '' si no se conoce. */
    public function fechaAprobacionFormateada(): string
    {
        return $this->fechaAprobacion ? date('d/m/Y', strtotime((string) $this->fechaAprobacion)) : '';
    }
}
