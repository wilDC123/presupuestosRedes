<?php

use yii\db\Migration;

/**
 * El texto que sale debajo de la cabecera del formulario de especificacion
 * (ConfiguracionEspecificacion.textoCabecera, 2026-11-10) pasa a escribirse
 * como en el Word v3: "San Francisco Xavier" entre comillas. Solo si nadie lo
 * habia editado (sigue igual al de siempre). Las emisiones ya hechas
 * conservan su copia.
 */
class m261110_050000_universidad_con_comillas_en_especificacion extends Migration
{
    private const ANTES = "Universidad Mayor, Real y Pontificia de San Francisco Xavier de Chuquisaca\nD.T.I.C.";
    private const DESPUES = "Universidad Mayor, Real y Pontificia de “San Francisco Xavier” de Chuquisaca\nD.T.I.C.";

    public function safeUp()
    {
        $this->cambiar(self::ANTES, self::DESPUES);
    }

    public function safeDown()
    {
        $this->cambiar(self::DESPUES, self::ANTES);
    }

    private function cambiar(string $de, string $a): void
    {
        // Se compara sin los \r (el texto pudo guardarse desde un formulario de Windows).
        $this->execute(
            "UPDATE redes.ConfiguracionEspecificacion SET textoCabecera = :a WHERE REPLACE(textoCabecera, CHAR(13), '') = :de",
            [':a' => $a, ':de' => $de]
        );
    }
}
