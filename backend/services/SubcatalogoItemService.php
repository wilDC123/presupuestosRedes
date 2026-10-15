<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CatalogoSigma;
use backend\models\SubcatalogoItem;
use yii\data\ActiveDataProvider;

/**
 * Capa de Servicio para SubcatalogoItem.
 *
 * Ademas del CRUD, aqui vive la busqueda en el catalogo institucional
 * (dbo.CatalogoSigma). Es el punto clave de esta capa: hoy la busqueda va
 * contra una tabla de SQL Server, pero si manana SIGMA se consulta via un
 * servicio web, solo cambia buscarEnCatalogoSigma() -- el controlador no se
 * entera de donde vienen los datos.
 */
class SubcatalogoItemService
{
    private const PATRON_ID_SIGMA = '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i';

    /**
     * Listado paginado del subcatalogo, con los filtros de la pantalla:
     *  - buscar: texto dentro del nombre DTIC (descripcion)
     *  - idCategoria: una categoria
     *  - partida: una partida de 5 digitos
     *  - estado: 'activo' o 'inactivo'
     * Un filtro vacio o con un valor que no corresponde se ignora.
     *
     * with() carga categoria y catalogoSigma de todas las filas en 2
     * consultas extra, en vez de una consulta por cada fila del listado.
     *
     * @param array{buscar?: string, idCategoria?: string, partida?: string, estado?: string} $filtros
     */
    public function listar(array $filtros = []): ActiveDataProvider
    {
        $query = SubcatalogoItem::find()
            ->with(['categoria', 'catalogoSigma'])
            ->orderBy(['descripcion' => SORT_ASC]);

        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        if ($buscar !== '') {
            $query->andWhere(['like', 'descripcion', $buscar]);
        }

        if (ctype_digit((string) ($filtros['idCategoria'] ?? ''))) {
            $query->andWhere(['idCategoria' => (int) $filtros['idCategoria']]);
        }

        if (preg_match('/^\d{5}$/', (string) ($filtros['partida'] ?? '')) === 1) {
            $query->andWhere(['partida' => $filtros['partida']]);
        }

        $estados = ['activo' => true, 'inactivo' => false];

        if (isset($estados[$filtros['estado'] ?? ''])) {
            $query->andWhere(['activo' => $estados[$filtros['estado']]]);
        }

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }

    /**
     * Cuantos items tiene el subcatalogo (sin filtrar) y cuantos items
     * vigentes tiene el catalogo SIGMA, para el subtitulo de la pantalla.
     *
     * @return array{items: int, sigma: int}
     */
    public function totales(): array
    {
        return [
            'items' => (int) SubcatalogoItem::find()->count(),
            'sigma' => (int) CatalogoSigma::find()->andWhere(['CodigoEstado' => CatalogoSigma::ESTADO_VIGENTE])->count(),
        ];
    }

    /**
     * Partidas distintas que ya tienen items, para el filtro del listado.
     *
     * @return string[]
     */
    public function partidasEnUso(): array
    {
        return SubcatalogoItem::find()
            ->select('partida')
            ->distinct()
            ->andWhere(['not', ['partida' => null]])
            ->orderBy(['partida' => SORT_ASC])
            ->column();
    }

    /**
     * Items activos del subcatalogo, por nombre DTIC, para las pantallas que
     * dejan elegir items (plantillas, lista del proyecto).
     *
     * @return SubcatalogoItem[]
     */
    public function activos(): array
    {
        return SubcatalogoItem::find()
            ->andWhere(['activo' => true])
            // with(): trae las categorias en una sola consulta extra.
            ->with('categoria')
            ->orderBy(['descripcion' => SORT_ASC])
            ->all();
    }

    /**
     * Todos los items como [id => nombre DTIC], por nombre, para los
     * desplegables (especificacion, lineas de cotizacion). Las vistas la
     * reciben del controlador: asi ninguna vista consulta la base de datos.
     *
     * @return array<int, string>
     */
    public function opciones(): array
    {
        return SubcatalogoItem::find()
            ->select(['descripcion', 'id'])
            ->orderBy(['descripcion' => SORT_ASC])
            ->indexBy('id')
            ->column();
    }

    public function obtener(int $id): ?SubcatalogoItem
    {
        return SubcatalogoItem::findOne($id);
    }

    /**
     * @return CatalogoSigma[]
     */
    public function buscarEnCatalogoSigma(string $texto): array
    {
        return CatalogoSigma::buscar($texto);
    }

    /**
     * true si la busqueda devolvio el maximo de filas permitido: puede haber
     * mas resultados que no se muestran, y conviene afinar el texto.
     *
     * @param CatalogoSigma[] $resultados resultado de buscarEnCatalogoSigma()
     */
    public function limiteDeBusquedaAlcanzado(array $resultados): bool
    {
        return count($resultados) >= CatalogoSigma::LIMITE_BUSQUEDA;
    }

    /**
     * Nombres DTIC de los items del subcatalogo, agrupados por el idSigma al
     * que apuntan. Sirve para AVISAR (sin bloquear) que una fila de SIGMA ya
     * fue usada: SIGMA es generico, asi que varios items DTIC pueden apuntar
     * al mismo idSigma (ej. "Switch PoE 24p" y "Switch 48p").
     *
     * La clave va en minusculas porque un uniqueidentifier puede llegar en
     * mayusculas o minusculas segun de donde venga; quien consulte el mapa
     * debe buscar tambien con strtolower().
     *
     * @return array<string, string[]> idSigma en minusculas => nombres DTIC
     */
    public function nombresDticPorIdSigma(): array
    {
        $filas = SubcatalogoItem::find()
            ->select(['idSigma', 'descripcion'])
            ->andWhere(['not', ['idSigma' => null]])
            ->orderBy(['descripcion' => SORT_ASC])
            ->asArray()
            ->all();

        $mapa = [];

        foreach ($filas as $fila) {
            $mapa[strtolower((string) $fila['idSigma'])][] = $fila['descripcion'];
        }

        return $mapa;
    }

    /**
     * Nombres DTIC de los OTROS items que apuntan al mismo idSigma que este
     * (para el aviso del formulario). No hace falta strtolower() aqui: la
     * comparacion la hace SQL Server, que no distingue mayusculas en un
     * uniqueidentifier.
     *
     * @return string[]
     */
    public function otrosItemsConMismoSigma(SubcatalogoItem $item): array
    {
        $idSigma = $this->idSigmaValido($item);

        if ($idSigma === null) {
            return [];
        }

        $query = SubcatalogoItem::find()
            ->select('descripcion')
            ->andWhere(['idSigma' => $idSigma])
            ->orderBy(['descripcion' => SORT_ASC]);

        if (!$item->isNewRecord) {
            $query->andWhere(['<>', 'id', $item->id]);
        }

        return $query->column();
    }

    /**
     * Prellenado desde "Buscar en catalogo institucional": el usuario eligio
     * una fila de dbo.CatalogoSigma.
     *
     * "descripcion" es el NOMBRE DTIC (corto y generico, el que sale en
     * listados, presupuesto y PDF), no el nombre SIGMA. Por eso arranca solo
     * con la Clase de SIGMA ("JACK" -> "Jack") para que el usuario la
     * complete a su manera, en vez de copiar la descripcion larga oficial.
     * La Clase se lee aqui desde la base con el idSigma, no desde la URL.
     */
    public function prellenarDesdeSigma(SubcatalogoItem $item, string $idSigma): void
    {
        $item->idSigma = $idSigma;

        $clase = trim((string) ($this->sigmaDe($item)->Clase ?? ''));

        if ($clase !== '') {
            $item->descripcion = mb_strtoupper(mb_substr($clase, 0, 1)) . mb_strtolower(mb_substr($clase, 1));
        }
    }

    public function crear(SubcatalogoItem $item, array $datosFormulario): bool
    {
        return $this->guardar($item, $datosFormulario);
    }

    public function actualizar(SubcatalogoItem $item, array $datosFormulario): bool
    {
        return $this->guardar($item, $datosFormulario);
    }

    /**
     * Fila de dbo.CatalogoSigma a la que apunta el idSigma del item, o null
     * si no tiene idSigma o no existe en el catalogo. El formulario la usa
     * para mostrar, en solo lectura, que eligio el usuario.
     */
    public function sigmaDe(SubcatalogoItem $item): ?CatalogoSigma
    {
        $idSigma = $this->idSigmaValido($item);

        return $idSigma === null ? null : CatalogoSigma::findOne(['IdSigma' => $idSigma]);
    }

    /**
     * Partida presupuestaria (IdGasto) que dbo.CatalogoSigma tiene para el
     * idSigma del item, o null si no se puede obtener: el item no tiene
     * idSigma (ej. mano de obra), el idSigma no existe en el catalogo, o
     * SIGMA no trae una partida de 5 digitos.
     *
     * El controlador tambien la usa para decirle al formulario si el campo
     * partida se muestra en solo lectura (copiada) o editable (a mano).
     */
    public function partidaDesdeSigma(SubcatalogoItem $item): ?string
    {
        $partida = trim((string) ($this->sigmaDe($item)->IdGasto ?? ''));

        return preg_match('/^\d{5}$/', $partida) === 1 ? $partida : null;
    }

    /**
     * idSigma del item solo si tiene forma de uniqueidentifier. Si no la
     * tiene, SQL Server daria un error de conversion al compararlo con
     * IdSigma, asi que no se debe consultar con el.
     */
    private function idSigmaValido(SubcatalogoItem $item): ?string
    {
        $idSigma = trim((string) $item->idSigma);

        return preg_match(self::PATRON_ID_SIGMA, $idSigma) === 1 ? $idSigma : null;
    }

    /**
     * ORDEN IMPORTANTE: en Yii2 la validacion corre DENTRO de save(). Por eso
     * la partida se copia desde SIGMA despues de load() (ya se conoce el
     * idSigma enviado) y ANTES de save(): asi la regla 'required' de
     * SubcatalogoItem::rules() ya encuentra el valor puesto.
     *
     * Si SIGMA trae partida, siempre gana sobre lo que venga del formulario
     * (el dato oficial no se edita a mano). Si no se pudo obtener, queda lo
     * que el usuario escribio y la validacion exige que no este vacio.
     */
    private function guardar(SubcatalogoItem $item, array $datosFormulario): bool
    {
        if (!$item->load($datosFormulario)) {
            return false;
        }

        $partidaSigma = $this->partidaDesdeSigma($item);

        if ($partidaSigma !== null) {
            $item->partida = $partidaSigma;
        }

        return $item->save();
    }

    public function eliminar(SubcatalogoItem $item): bool
    {
        return $item->delete() !== false;
    }
}
