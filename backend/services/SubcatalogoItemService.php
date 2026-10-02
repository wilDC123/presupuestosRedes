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
        return $item->load($datosFormulario) && $item->save();
    }

    public function actualizar(SubcatalogoItem $item, array $datosFormulario): bool
    {
        return $item->load($datosFormulario) && $item->save();
    }

    public function eliminar(SubcatalogoItem $item): bool
    {
        return $item->delete() !== false;
    }
}
