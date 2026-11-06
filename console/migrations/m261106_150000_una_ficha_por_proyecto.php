<?php

use yii\db\Migration;

/**
 * UNA SOLA FICHA TECNICA POR PROYECTO (revision del Ing. de DTIC del
 * 2026-11-05, punto 2; pedido del desarrollador el 2026-11-06: "solo debe
 * existir un solo PT, no 26 y 27"). El codigo RD-PI-PT-NNN es el de la ficha
 * y la ficha es una por proyecto, con un bloque por fase: solo la fase 1
 * conserva su codigo. Las fases siguientes que tenian uno propio (en la base
 * local, el RD-PI-PT-027 de la fase 2 del proyecto 001) lo pierden; queda
 * anotado en el historial de esa fase y el numero no se reutiliza (el
 * correlativo PT no se toca). El registro lleva como usuario "migración" (no fue
 * una persona).
 */
class m261106_150000_una_ficha_por_proyecto extends Migration
{
    private const MARCA = 'Una sola ficha técnica por proyecto: el código ';

    public function safeUp()
    {
        // Primero el historial (copia del codigo que se quita), despues el cambio.
        $this->execute(<<<SQL
            INSERT INTO redes.HistorialProyecto (fecha, usuario, idProyecto, codigoProyecto, accion, estado, detalle)
            SELECT SYSDATETIME(), N'migración', p.id, p.codigo, 'proyecto_modificado', NULL,
                   N'{$this->marca()}' + p.codigo + N' se quitó: la ficha del proyecto es la de su fase 1.'
            FROM redes.Proyecto p
            WHERE p.numeroFase > 1 AND p.codigo IS NOT NULL
            SQL);

        $this->execute('UPDATE redes.Proyecto SET codigo = NULL WHERE numeroFase > 1 AND codigo IS NOT NULL');
    }

    public function safeDown()
    {
        // Devuelve a cada fase el codigo que tenia (guardado en codigoProyecto de su registro).
        $marca = $this->marca();
        $this->execute(<<<SQL
            UPDATE p SET codigo = h.codigoProyecto
            FROM redes.Proyecto p
            JOIN redes.HistorialProyecto h ON h.idProyecto = p.id
            WHERE h.detalle LIKE N'{$marca}%'
            SQL);
        $this->execute("DELETE FROM redes.HistorialProyecto WHERE detalle LIKE N'{$marca}%'");
    }

    private function marca(): string
    {
        return self::MARCA;
    }
}
