<?php

use yii\db\Migration;

/**
 * Limpieza de las especificaciones (docs/plan-especificaciones.md, paso 4).
 *
 *  - redes.Especificacion deja de ser "un documento emitido" y pasa a ser
 *    solo la ficha de la libreria: se quitan codigoNet, dependencia y
 *    fechaEmision (ahora son de cada emision NET) y modeloSugerido y
 *    documentoRuta (ahora son de cada version; la migracion
 *    m261017_231816 ya los copio a la v1).
 *  - redes.EspecificacionEmision.garantia: copia CONGELADA de la garantia
 *    que se imprimio (la de la version o, si no tenia, la de la
 *    configuracion). Sin ella, cambiar la garantia por defecto cambiaria
 *    documentos ya firmados.
 *
 * CotizacionDetalle.idEspecificacion se queda: una cotizacion suelta (sin
 * solicitud) elige una ficha y no tiene NET.
 */
class m261018_000758_limpieza_especificacion_y_garantia_emision extends Migration
{
    private const COLUMNAS = ['codigoNet', 'dependencia', 'fechaEmision', 'modeloSugerido', 'documentoRuta'];

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Un codigo NET escrito a mano en una ficha seria la referencia de un
        // documento real: si hay alguno, se detiene para no perderlo.
        $conNet = $this->db->createCommand(
            "SELECT id, codigoNet FROM redes.Especificacion WHERE codigoNet IS NOT NULL AND LTRIM(RTRIM(codigoNet)) <> ''"
        )->queryAll();

        if ($conNet !== []) {
            echo "No se puede aplicar: estas fichas tienen un código NET cargado a mano:\n";
            foreach ($conNet as $fila) {
                echo "  - ficha {$fila['id']}: {$fila['codigoNet']}\n";
            }
            echo "Anótelos (son documentos ya emitidos) y vacíe el campo antes de volver a intentar.\n";

            return false;
        }

        // Por seguridad: si alguna ficha no tiene v1, sus datos no se copiaron.
        $sinVersion = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM redes.Especificacion e
             WHERE NOT EXISTS (SELECT 1 FROM redes.EspecificacionVersion v WHERE v.idEspecificacion = e.id)
               AND (e.modeloSugerido IS NOT NULL OR e.documentoRuta IS NOT NULL)'
        )->queryScalar();

        if ($sinVersion > 0) {
            echo "No se puede aplicar: $sinVersion ficha(s) tienen modelo sugerido o documento pero ninguna versión.\n";

            return false;
        }

        foreach (self::COLUMNAS as $columna) {
            $this->dropColumn('redes.Especificacion', $columna);
        }

        $this->addColumn('redes.EspecificacionEmision', 'garantia', $this->string(500)->null());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('redes.EspecificacionEmision', 'garantia');

        // Las columnas vuelven, y modeloSugerido y documentoRuta se rellenan
        // desde la version actual. codigoNet, dependencia y fechaEmision
        // vuelven vacias: ahora viven en las emisiones.
        $this->addColumn('redes.Especificacion', 'codigoNet', $this->string(20));
        $this->addColumn('redes.Especificacion', 'dependencia', $this->string(200));
        $this->addColumn('redes.Especificacion', 'modeloSugerido', $this->string(200));
        $this->addColumn('redes.Especificacion', 'documentoRuta', $this->string(500));
        $this->addColumn('redes.Especificacion', 'fechaEmision', $this->date());

        $this->execute(
            'UPDATE e SET e.modeloSugerido = v.modeloSugerido, e.documentoRuta = v.adjuntoRuta
             FROM redes.Especificacion e
             JOIN redes.EspecificacionVersion v ON v.idEspecificacion = e.id
             WHERE v.numero = (SELECT MAX(v2.numero) FROM redes.EspecificacionVersion v2 WHERE v2.idEspecificacion = e.id)'
        );
    }
}
