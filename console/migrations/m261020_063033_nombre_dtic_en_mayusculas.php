<?php

use yii\db\Migration;

/**
 * Pasa a MAYUSCULAS el nombre DTIC (SubcatalogoItem.descripcion) de los
 * items que ya existen. Los nuevos o editados se convierten solos en el
 * modelo (regla "filter" de SubcatalogoItem).
 *
 * UPPER() de SQL Server convierte tambien las letras con tilde (á -> Á);
 * LTRIM/RTRIM quitan los espacios sobrantes, igual que el modelo.
 */
class m261020_063033_nombre_dtic_en_mayusculas extends Migration
{
    public function safeUp()
    {
        $this->execute('UPDATE [redes].[SubcatalogoItem] SET [descripcion] = UPPER(LTRIM(RTRIM([descripcion])))');
    }

    public function safeDown()
    {
        // No se puede deshacer: las mayusculas/minusculas originales no se
        // guardaron. Deshacer solo saca la migracion del historial; los
        // nombres se quedan en mayusculas (no rompe nada).
        echo "    > Los nombres DTIC se quedan en mayúsculas (el cambio no se puede revertir).\n";

        return true;
    }
}
