<?php

use yii\db\Migration;

/**
 * Proyecto.unidadDependencia: la unidad BENEFICIARIA, a donde va el proyecto
 * (revision con el Ing. de DTIC del 2026-10-29). Es la que sale como
 * "Dependencia" o "Unidad" en los documentos.
 *
 * Proyecto.unidadSolicitante sigue siendo QUIEN pide (puede ser otra: el
 * Rector pide camaras para el Canal Universitario). Las dos son texto libre.
 *
 * Los proyectos existentes copian la unidad solicitante: hasta hoy ese campo
 * se usaba como beneficiaria (es la que salia en los documentos).
 */
class m261029_232347_agregar_unidad_dependencia_a_proyecto extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('redes.Proyecto', 'unidadDependencia', $this->string(250)->null());
        $this->execute('UPDATE redes.Proyecto SET unidadDependencia = unidadSolicitante WHERE unidadSolicitante IS NOT NULL');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('redes.Proyecto', 'unidadDependencia');
    }
}
