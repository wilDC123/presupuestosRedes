<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\Busqueda;
use backend\services\contracts\InfraestructuraProvider;
use Yii;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Query;

/**
 * Infraestructura fisica leida de la base UsfxGEO (de Desarrollo, solo
 * lectura): Edificios -> BloquesAreas -> Niveles -> Espacios.
 *
 * Es la UNICA clase que sabe que los datos vienen de esa base. Usa su propia
 * conexion (componente "dbGeo", configurado en common/config/main-local.php),
 * porque UsfxGEO es otra base, no POAPresupuestos.
 *
 * Solo se ofrece lo vigente (Estado = 'V'). Las columnas char de UsfxGEO
 * vienen rellenas con espacios ("0 "): por eso los RTRIM.
 */
class UsfxGeoInfraestructuraProvider implements InfraestructuraProvider
{
    private const VIGENTE = 'V';

    /**
     * @param string $componente id del componente de conexion en la configuracion de Yii
     */
    public function __construct(private string $componente = 'dbGeo')
    {
    }

    public function edificios(): array
    {
        $filas = (new Query())
            ->select(['id' => new Expression('RTRIM(IdEdificio)'), 'nombre' => new Expression('LTRIM(RTRIM(Nombre))')])
            ->from('dbo.Edificios')
            ->where(['Estado' => self::VIGENTE])
            ->orderBy(['nombre' => SORT_ASC])
            ->all($this->db());

        return array_column($filas, 'nombre', 'id');
    }

    public function bloques(string $idEdificio): array
    {
        $filas = (new Query())
            ->select(['IdBloqueArea', 'CodigoBloque', 'Nombre'])
            ->from('dbo.BloquesAreas')
            ->where(['IdEdificio' => $idEdificio, 'Estado' => self::VIGENTE])
            ->orderBy(['CodigoBloque' => SORT_ASC, 'Nombre' => SORT_ASC])
            ->all($this->db());

        $bloques = [];

        foreach ($filas as $fila) {
            $bloques[(int) $fila['IdBloqueArea']] = self::nombreBloque($fila['CodigoBloque'], $fila['Nombre']);
        }

        return $bloques;
    }

    public function niveles(int $idBloqueArea): array
    {
        $filas = (new Query())
            ->select(['numero' => new Expression('RTRIM(NumeroNivel)'), 'Descripcion'])
            ->from('dbo.Niveles')
            ->where(['IdBloqueArea' => $idBloqueArea, 'Estado' => self::VIGENTE])
            // NumeroNivel es texto: "10" iria antes que "2" sin convertirlo.
            ->orderBy(new Expression('TRY_CAST(RTRIM(NumeroNivel) AS int), NumeroNivel'))
            ->all($this->db());

        $niveles = [];

        foreach ($filas as $fila) {
            $niveles[$fila['numero']] = self::nombreNivel($fila['numero'], $fila['Descripcion']);
        }

        return $niveles;
    }

    public function ambientes(int $idBloqueArea, string $numeroNivel): array
    {
        $filas = (new Query())
            ->select(['id' => new Expression('RTRIM(IdEspacio)'), 'CodigoEspacio', 'Nombre'])
            ->from('dbo.Espacios')
            ->where(['IdBloqueArea' => $idBloqueArea, 'NumeroNivel' => $numeroNivel, 'Estado' => self::VIGENTE])
            ->orderBy(['CodigoEspacio' => SORT_ASC, 'Nombre' => SORT_ASC])
            ->all($this->db());

        $ambientes = [];

        foreach ($filas as $fila) {
            $ambientes[$fila['id']] = self::nombreAmbiente($fila['CodigoEspacio'], $fila['Nombre']);
        }

        return $ambientes;
    }

    public function buscarEdificios(string $texto, int $limite = 10): array
    {
        $query = (new Query())
            ->select(['idEdificio' => new Expression('RTRIM(IdEdificio)'), 'edificio' => new Expression('LTRIM(RTRIM(Nombre))')])
            ->from('dbo.Edificios')
            ->where(['Estado' => self::VIGENTE])
            ->orderBy(['edificio' => SORT_ASC])
            ->limit($limite);

        if (!self::filtrarPorPalabras($query, $texto, "ISNULL(Nombre, '') + ' ' + ISNULL(NombreCorto, '') + ' ' + ISNULL(Descripcion, '')")) {
            return [];
        }

        return $query->all($this->db());
    }

    public function buscarAmbientes(string $texto, int $limite = 30): array
    {
        $query = $this->consultaRuta()
            ->andWhere(['e.Estado' => self::VIGENTE, 'b.Estado' => self::VIGENTE, 'n.Estado' => self::VIGENTE, 's.Estado' => self::VIGENTE])
            ->orderBy(['e.Nombre' => SORT_ASC, 'b.CodigoBloque' => SORT_ASC, 's.CodigoEspacio' => SORT_ASC])
            ->limit($limite);

        $columnas = "ISNULL(s.CodigoEspacio, '') + ' ' + ISNULL(s.Nombre, '') + ' ' + ISNULL(b.Nombre, '') + ' ' + ISNULL(e.Nombre, '') + ' ' + ISNULL(e.NombreCorto, '')";

        if (!self::filtrarPorPalabras($query, $texto, $columnas)) {
            return [];
        }

        return array_map([$this, 'filaRuta'], $query->all($this->db()));
    }

    /**
     * Agrega a la consulta una condicion por palabra: todas deben estar en
     * algun lugar del texto (como el buscador de especificaciones).
     * COLLATE ..._AI ignora las tildes, y se quitan los guiones de los dos
     * lados para que "E303" encuentre "E-303". false si no hay palabras.
     */
    private static function filtrarPorPalabras(Query $query, string $texto, string $columnas): bool
    {
        $palabras = Busqueda::palabras(str_replace('-', '', $texto));

        if ($palabras === []) {
            return false;
        }

        $donde = "REPLACE($columnas, '-', '') COLLATE Modern_Spanish_CI_AI";

        foreach (array_values($palabras) as $i => $palabra) {
            $query->andWhere(new Expression("$donde LIKE :palabra$i", [":palabra$i" => '%' . strtr($palabra, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%']));
        }

        return true;
    }

    public function ruta(string $idEdificio, ?int $idBloqueArea, ?string $numeroNivel, ?string $idEspacio): ?array
    {
        // No se puede saltar un nivel: ambiente sin nivel, nivel sin bloque...
        if (($idEspacio !== null && $numeroNivel === null) || ($numeroNivel !== null && $idBloqueArea === null)) {
            return null;
        }

        $edificio = (new Query())
            ->select(['nombre' => new Expression('LTRIM(RTRIM(Nombre))')])
            ->from('dbo.Edificios')
            ->where(['IdEdificio' => $idEdificio, 'Estado' => self::VIGENTE])
            ->scalar($this->db());

        if ($edificio === false) {
            return null;
        }

        $ruta = ['edificio' => (string) $edificio, 'bloque' => null, 'nivel' => null, 'codigoAmbiente' => null, 'ambiente' => null];

        if ($idBloqueArea === null) {
            return $ruta;
        }

        $bloque = $this->bloques($idEdificio)[$idBloqueArea] ?? null;

        if ($bloque === null) {
            return null;
        }

        $ruta['bloque'] = $bloque;

        if ($numeroNivel === null) {
            return $ruta;
        }

        $nivel = $this->niveles($idBloqueArea)[$numeroNivel] ?? null;

        if ($nivel === null) {
            return null;
        }

        $ruta['nivel'] = $nivel;

        if ($idEspacio === null) {
            return $ruta;
        }

        $espacio = (new Query())
            ->select(['CodigoEspacio', 'Nombre'])
            ->from('dbo.Espacios')
            ->where(['IdEspacio' => $idEspacio, 'IdBloqueArea' => $idBloqueArea, 'NumeroNivel' => $numeroNivel, 'Estado' => self::VIGENTE])
            ->one($this->db());

        if ($espacio === false) {
            return null;
        }

        $ruta['codigoAmbiente'] = self::textoONull($espacio['CodigoEspacio']);
        $ruta['ambiente'] = self::nombreAmbiente($espacio['CodigoEspacio'], $espacio['Nombre']);

        return $ruta;
    }

    /**
     * "Bloque A (Patio 1)"; si el bloque no tiene nombre, "Bloque A".
     */
    public static function nombreBloque(?string $codigo, ?string $nombre): string
    {
        return self::textoONull($nombre) ?? 'Bloque ' . trim((string) $codigo);
    }

    public static function nombreNivel(string $numero, ?string $descripcion): string
    {
        return self::textoONull($descripcion) ?? 'Nivel ' . $numero;
    }

    /**
     * "B101 Laboratorio de computación". Si el nombre ya trae el codigo
     * ("Laboratorio de Informatica E102") no se repite.
     */
    public static function nombreAmbiente(?string $codigo, ?string $nombre): string
    {
        $codigo = self::textoONull($codigo);
        $nombre = self::textoONull($nombre);

        if ($codigo === null || ($nombre !== null && mb_stripos($nombre, $codigo) !== false)) {
            return (string) $nombre;
        }

        return $nombre === null ? $codigo : $codigo . ' ' . $nombre;
    }

    /**
     * Edificio, bloque, nivel y ambiente unidos, con los nombres que se
     * muestran y se guardan.
     */
    private function consultaRuta(): Query
    {
        return (new Query())
            ->select([
                'idEdificio' => new Expression('RTRIM(e.IdEdificio)'),
                'edificio' => new Expression('LTRIM(RTRIM(e.Nombre))'),
                'b.IdBloqueArea', 'b.CodigoBloque', 'bloqueNombre' => 'b.Nombre',
                'numeroNivel' => new Expression('RTRIM(n.NumeroNivel)'), 'n.Descripcion',
                'idEspacio' => new Expression('RTRIM(s.IdEspacio)'), 's.CodigoEspacio', 'espacioNombre' => 's.Nombre',
            ])
            ->from(['s' => 'dbo.Espacios'])
            ->innerJoin(['n' => 'dbo.Niveles'], 'n.IdBloqueArea = s.IdBloqueArea AND n.NumeroNivel = s.NumeroNivel')
            ->innerJoin(['b' => 'dbo.BloquesAreas'], 'b.IdBloqueArea = s.IdBloqueArea')
            ->innerJoin(['e' => 'dbo.Edificios'], 'e.IdEdificio = b.IdEdificio');
    }

    private function filaRuta(array $fila): array
    {
        return [
            'idEdificio' => $fila['idEdificio'],
            'edificio' => $fila['edificio'],
            'idBloqueArea' => (int) $fila['IdBloqueArea'],
            'bloque' => self::nombreBloque($fila['CodigoBloque'], $fila['bloqueNombre']),
            'numeroNivel' => $fila['numeroNivel'],
            'nivel' => self::nombreNivel($fila['numeroNivel'], $fila['Descripcion']),
            'idEspacio' => $fila['idEspacio'],
            'codigoAmbiente' => (string) self::textoONull($fila['CodigoEspacio']),
            'ambiente' => self::nombreAmbiente($fila['CodigoEspacio'], $fila['espacioNombre']),
        ];
    }

    private static function textoONull(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : $texto;
    }

    private function db(): Connection
    {
        /** @var Connection $db */
        $db = Yii::$app->get($this->componente);

        return $db;
    }
}
