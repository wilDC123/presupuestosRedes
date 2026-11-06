<?php

declare(strict_types=1);

namespace backend\models;

use backend\components\VersionDocumento;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ListaMaterialesAprobacion": una VERSION
 * APROBADA de la "Lista de materiales y condiciones previas" del proyecto
 * (revision del Ing. de DTIC del 2026-10-29, paso 8b).
 *
 * La lista es un documento del lineamiento RD-GD-LI-001 SIN codigo propio:
 * lleva el codigo del proyecto (decidido el 2026-10-30). Cada "Enviar a
 * cotizacion" aprueba la version siguiente (01, y +1 despues de cada orden
 * de cambio); ver ListaItemsService::enviarACotizacion(). Una fila nunca se
 * modifica. Las firmas son la COPIA de las de la configuracion del
 * presupuesto el dia de la aprobacion (trait FirmasDeControl, sin reglas: no
 * llegan de formularios).
 *
 * @property int $id
 * @property int $idProyecto
 * @property int $version
 * @property string|null $fechaAprobacion Y-m-d; NULL = aprobada antes de este registro.
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
