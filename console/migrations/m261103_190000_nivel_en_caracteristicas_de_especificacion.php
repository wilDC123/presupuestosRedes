<?php

use yii\db\Migration;

/**
 * Nivel de cada fila de una especificacion (2026-11-03). Con solo la casilla
 * esSubtitulo (m261103_141729), todo lo que seguia a un subtitulo quedaba
 * dentro de el hasta el siguiente: en el NVR de 32 canales, de "1.9 Ancho de
 * banda de la red" colgaban USB, idiomas y discos (1.9.3 a 1.9.18). Ahora cada
 * fila dice su nivel, como en un indice: 1 = 1.4, 2 = 1.4.1, 3 = 1.4.1.1
 * (NumeracionEspecificacion), y una fila de nivel 1 cierra el grupo.
 *
 * Para que ningun numero cambie, las filas que siguen a un subtitulo quedan
 * en 2 y el resto en 1 (NumeracionEspecificacion::nivelesPorSubtitulo()). Las
 * que en el Word estaban fuera del grupo se corrigen a mano con las flechas
 * del formulario. Vale tambien para versiones emitidas (solo cambia como se
 * dibujan, como la migracion de los subtitulos).
 */
class m261103_190000_nivel_en_caracteristicas_de_especificacion extends Migration
{
    public function safeUp()
    {
        $this->addColumn('redes.EspecificacionCaracteristica', 'nivel', $this->tinyInteger()->notNull()->defaultValue(1));

        $this->execute(<<<'SQL'
            UPDATE c SET nivel = 2
            FROM redes.EspecificacionCaracteristica c
            WHERE c.esSubtitulo = 0
              AND EXISTS (
                  SELECT 1 FROM redes.EspecificacionCaracteristica s
                  WHERE s.idVersion = c.idVersion AND s.orden < c.orden AND s.esSubtitulo = 1
              )
            SQL);

        $this->execute('ALTER TABLE redes.EspecificacionCaracteristica ADD CONSTRAINT CK_EspecificacionCaracteristica_nivel CHECK (nivel BETWEEN 1 AND 3)');
    }

    public function safeDown()
    {
        $this->execute('ALTER TABLE redes.EspecificacionCaracteristica DROP CONSTRAINT CK_EspecificacionCaracteristica_nivel');
        // dropColumn() de Yii elimina antes la restriccion del valor por defecto.
        $this->dropColumn('redes.EspecificacionCaracteristica', 'nivel');
    }
}
