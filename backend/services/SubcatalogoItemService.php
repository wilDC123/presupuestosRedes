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

    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => SubcatalogoItem::find()->with('categoria')->orderBy(['descripcion' => SORT_ASC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
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
     * idSigma ya usados en el subcatalogo, para saber que fila del catalogo
     * institucional ya fue agregada y no ofrecer duplicarla.
     *
     * @return string[]
     */
    public function idsSigmaExistentes(): array
    {
        return SubcatalogoItem::find()
            ->select('idSigma')
            ->andWhere(['not', ['idSigma' => null]])
            ->column();
    }

    /**
     * Prellenado desde "Buscar en catalogo institucional": el usuario eligio
     * una fila de dbo.CatalogoSigma y solo le falta confirmar/ajustar
     * categoria y unidad por defecto antes de guardar.
     */
    public function prellenarDesdeSigma(SubcatalogoItem $item, string $idSigma, string $descripcion): void
    {
        $item->idSigma = $idSigma;
        $item->descripcion = $descripcion;
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
        $idSigma = trim((string) $item->idSigma);

        // Si el texto no tiene forma de uniqueidentifier, SQL Server daria un
        // error de conversion al compararlo con IdSigma: no se consulta.
        if (preg_match(self::PATRON_ID_SIGMA, $idSigma) !== 1) {
            return null;
        }

        $sigma = CatalogoSigma::findOne(['IdSigma' => $idSigma]);
        $partida = trim((string) ($sigma->IdGasto ?? ''));

        return preg_match('/^\d{5}$/', $partida) === 1 ? $partida : null;
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
