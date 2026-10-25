<?php

use yii\db\Migration;

/**
 * Fecha de envio de la solicitud de cotizacion (2026-10-24): la que sale en
 * el PDF. Opcional: NULL = el PDF lleva la fecha del dia en que se imprime,
 * como hasta ahora. Con fecha, el documento sale siempre igual aunque se
 * reimprima despues.
 *
 * La columna "fecha" que ya existe es la de CREACION (la pone GETDATE()) y
 * no sirve para el documento: la solicitud se arma un dia y se envia otro.
 */
class m261025_014520_agregar_fecha_envio_a_solicitud_cotizacion extends Migration
{
    public function safeUp()
    {
        $this->addColumn('redes.SolicitudCotizacion', 'fechaEnvio', $this->date()->null());
    }

    public function safeDown()
    {
        $this->dropColumn('redes.SolicitudCotizacion', 'fechaEnvio');
    }
}
