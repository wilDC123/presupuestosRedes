<?php

use yii\db\Migration;

/**
 * Catalogo de unidades de medida (redes.UnidadMedida), para que la unidad
 * deje de escribirse a mano.
 *
 *  - redes.SubcatalogoItem: la columna de texto unidadDefecto se reemplaza
 *    por la FK idUnidadMedida.
 *  - redes.ProyectoItem, redes.PresupuestoDetalle y redes.ComputoMetrico
 *    SIGUEN guardando la unidad como texto (la abreviatura, ej. "pza"),
 *    copiada al crear la fila: igual que el precio y la partida, lo que ya
 *    se presento no cambia si despues se edita la unidad en el catalogo.
 *
 * Los textos que ya habia se normalizan: "unidad"/"unidades" pasan a la
 * misma unidad, "pieza"/"piezas" igual, se quitan espacios sobrantes, etc.
 * Un texto que no se reconoce no se pierde: se crea como unidad nueva.
 */
class m261015_233726_create_unidad_medida extends Migration
{
    /**
     * Unidades iniciales: abreviatura => nombre.
     */
    private const UNIDADES = [
        'u' => 'Unidad',
        'pza' => 'Pieza',
        'cja' => 'Caja',
        'rll' => 'Rollo',
        'm' => 'Metro',
        'm2' => 'Metro cuadrado',
        'paq' => 'Paquete',
        'jgo' => 'Juego',
        'pto' => 'Punto',
        'glb' => 'Global',
    ];

    /**
     * Textos ya escritos a mano (en minusculas y sin espacios a los lados)
     * => abreviatura de la unidad a la que corresponden.
     */
    private const SINONIMOS = [
        'unidad' => 'u', 'unidades' => 'u', 'und' => 'u', 'u' => 'u',
        'pieza' => 'pza', 'piezas' => 'pza', 'pza' => 'pza',
        'caja' => 'cja', 'cajas' => 'cja', 'cja' => 'cja',
        'rollo' => 'rll', 'rollos' => 'rll', 'rll' => 'rll',
        'metro' => 'm', 'metros' => 'm', 'm' => 'm',
        'metro cuadrado' => 'm2', 'metros cuadrados' => 'm2', 'm2' => 'm2',
        'paquete' => 'paq', 'paquetes' => 'paq', 'paq' => 'paq',
        'juego' => 'jgo', 'juegos' => 'jgo', 'jgo' => 'jgo',
        'punto' => 'pto', 'puntos' => 'pto', 'pto' => 'pto',
        'global' => 'glb', 'glb' => 'glb',
    ];

    /**
     * Columnas de texto que se normalizan: tabla => columna.
     */
    private const COLUMNAS_TEXTO = [
        'redes.ProyectoItem' => 'unidadMedida',
        'redes.PresupuestoDetalle' => 'unidadMedida',
        'redes.ComputoMetrico' => 'unidad',
    ];

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('redes.UnidadMedida', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(50)->notNull(),
            'abreviatura' => $this->string(10)->notNull(),
            'activo' => $this->boolean()->notNull()->defaultValue(1),
        ]);

        // Sin repetidos: dos "Metro" (o dos "m") harian ambiguo el desplegable.
        $this->createIndex('ux-unidadmedida-nombre', 'redes.UnidadMedida', 'nombre', true);
        $this->createIndex('ux-unidadmedida-abreviatura', 'redes.UnidadMedida', 'abreviatura', true);

        foreach (self::UNIDADES as $abreviatura => $nombre) {
            $this->insert('redes.UnidadMedida', ['nombre' => $nombre, 'abreviatura' => $abreviatura]);
        }

        $this->addColumn('redes.SubcatalogoItem', 'idUnidadMedida', $this->integer()->null());
        // Sin ON DELETE: borrar una unidad que usa algun item debe fallar.
        $this->addForeignKey('fk-subcatalogoitem-idunidadmedida', 'redes.SubcatalogoItem', 'idUnidadMedida', 'redes.UnidadMedida', 'id');
        // SQL Server no crea solo el indice de una FK.
        $this->createIndex('idx-subcatalogoitem-idunidadmedida', 'redes.SubcatalogoItem', 'idUnidadMedida');

        // Cada texto distinto (sin mayusculas ni espacios a los lados) de las
        // cuatro columnas, convertido a su unidad.
        $columnas = self::COLUMNAS_TEXTO + ['redes.SubcatalogoItem' => 'unidadDefecto'];
        $consultas = [];
        foreach ($columnas as $tabla => $columna) {
            $consultas[] = "SELECT LOWER(LTRIM(RTRIM([$columna]))) AS texto FROM $tabla";
        }
        $textos = $this->db->createCommand(
            'SELECT DISTINCT texto FROM (' . implode(' UNION ALL ', $consultas) . ") t WHERE texto <> ''"
        )->queryColumn();

        foreach ($textos as $texto) {
            $abreviatura = $this->abreviaturaPara($texto);
            $idUnidad = $this->idDe($abreviatura);

            echo "    > \"$texto\" => $abreviatura\n";

            $this->update(
                'redes.SubcatalogoItem',
                ['idUnidadMedida' => $idUnidad],
                'LOWER(LTRIM(RTRIM(unidadDefecto))) = :texto',
                [':texto' => $texto]
            );

            foreach (self::COLUMNAS_TEXTO as $tabla => $columna) {
                $this->update(
                    $tabla,
                    [$columna => $abreviatura],
                    "LOWER(LTRIM(RTRIM([$columna]))) = :texto",
                    [':texto' => $texto]
                );
            }
        }

        $this->dropColumn('redes.SubcatalogoItem', 'unidadDefecto');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Vuelve el texto al subcatalogo con el NOMBRE de la unidad. Los
        // textos originales ("unidades", "piezas"...) no se recuperan: la
        // normalizacion es a proposito. ProyectoItem, PresupuestoDetalle y
        // ComputoMetrico se quedan con la abreviatura, que es texto valido.
        $this->addColumn('redes.SubcatalogoItem', 'unidadDefecto', $this->string(20));
        $this->execute(
            'UPDATE s SET s.unidadDefecto = u.nombre
             FROM redes.SubcatalogoItem s
             INNER JOIN redes.UnidadMedida u ON u.id = s.idUnidadMedida'
        );

        $this->dropIndex('idx-subcatalogoitem-idunidadmedida', 'redes.SubcatalogoItem');
        $this->dropForeignKey('fk-subcatalogoitem-idunidadmedida', 'redes.SubcatalogoItem');
        $this->dropColumn('redes.SubcatalogoItem', 'idUnidadMedida');

        $this->dropTable('redes.UnidadMedida');
    }

    /**
     * Abreviatura de la unidad que corresponde a un texto escrito a mano. Si
     * no es una unidad conocida, la crea con ese mismo texto.
     */
    private function abreviaturaPara(string $texto): string
    {
        if (isset(self::SINONIMOS[$texto])) {
            return self::SINONIMOS[$texto];
        }

        $abreviatura = mb_substr($texto, 0, 10);

        if ($this->idDe($abreviatura) === null) {
            $this->insert('redes.UnidadMedida', [
                'nombre' => mb_convert_case(mb_substr($texto, 0, 50), MB_CASE_TITLE),
                'abreviatura' => $abreviatura,
            ]);
        }

        return $abreviatura;
    }

    private function idDe(string $abreviatura): ?int
    {
        $id = $this->db->createCommand(
            'SELECT id FROM redes.UnidadMedida WHERE abreviatura = :abreviatura',
            [':abreviatura' => $abreviatura]
        )->queryScalar();

        return $id === false ? null : (int) $id;
    }
}
