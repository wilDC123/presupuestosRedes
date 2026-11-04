<?php

use yii\db\Migration;

/**
 * Numeracion del formulario de especificacion (2026-11-03): cada fila de una
 * seccion lleva su numero (1.1, 1.2... 2.1...) y las filas que siguen a un
 * SUBTITULO se numeran debajo de el (1.4 Hardware -> 1.4.1, 1.4.2...). Los
 * numeros no se guardan: los calcula NumeracionEspecificacion. Lo unico que
 * se guarda es que fila es subtitulo: la columna esSubtitulo. El nivel de
 * cada fila lo agrega m261103_190000_nivel_en_caracteristicas_de_especificacion.
 *
 * Hasta ahora un subtitulo era una fila sin nombre (solo valor), igual que
 * una frase suelta ("Incluye la configuración."). La migracion marca como
 * subtitulo SOLO los casos seguros: sin nombre, una sola linea, de 40
 * caracteres o menos, sin punto final y con al menos una fila despues en la
 * misma version. El resto se marca a mano en la ficha.
 *
 * Marca tambien filas de versiones ya emitidas: no cambia su texto, solo como
 * se dibujan (decidido el 2026-11-03: al reimprimir, las anteriores tambien
 * salen numeradas).
 */
class m261103_141729_subtitulos_en_caracteristicas_de_especificacion extends Migration
{
    public function safeUp()
    {
        $this->addColumn('redes.EspecificacionCaracteristica', 'esSubtitulo', $this->boolean()->notNull()->defaultValue(0));

        $this->execute(<<<'SQL'
            UPDATE c SET esSubtitulo = 1
            FROM redes.EspecificacionCaracteristica c
            WHERE c.nombre IS NULL
              AND LEN(c.valor) <= 40
              AND CHARINDEX(CHAR(10), c.valor) = 0
              AND CHARINDEX(CHAR(13), c.valor) = 0
              AND RIGHT(RTRIM(c.valor), 1) <> '.'
              AND EXISTS (
                  SELECT 1 FROM redes.EspecificacionCaracteristica s
                  WHERE s.idVersion = c.idVersion AND s.orden > c.orden
              )
            SQL);
    }

    public function safeDown()
    {
        // dropColumn() de Yii elimina antes la restriccion del valor por defecto.
        $this->dropColumn('redes.EspecificacionCaracteristica', 'esSubtitulo');
    }
}
