<?php

use yii\db\Migration;

/**
 * Agrega la partida presupuestaria (5 digitos, ej. 39700) a `redes.SubcatalogoItem`.
 */
class m261005_033949_add_partida_to_subcatalogo_item extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Se escribe 'char(5) NULL' a mano porque $this->char(5) en SQL Server
        // genera nchar(5); la partida son solo digitos, no necesita Unicode.
        // NULL porque los items que no vienen de SIGMA (ej. mano de obra) la
        // reciben a mano desde el formulario, no desde esta migracion.
        $this->addColumn('redes.SubcatalogoItem', 'partida', 'char(5) NULL');

        // Relleno de los items que ya existian: la partida es el IdGasto de su
        // fila en dbo.CatalogoSigma. Solo se LEE de dbo.CatalogoSigma, nunca se
        // modifica. Si no hay items (o ninguno tiene idSigma) el UPDATE afecta
        // 0 filas y la migracion sigue sin error.
        $this->execute(
            "UPDATE s
                SET s.partida = CAST(LTRIM(RTRIM(c.IdGasto)) AS char(5))
               FROM redes.SubcatalogoItem s
              INNER JOIN dbo.CatalogoSigma c ON c.IdSigma = s.idSigma
              WHERE LEN(LTRIM(RTRIM(c.IdGasto))) = 5"
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('redes.SubcatalogoItem', 'partida');
    }
}
