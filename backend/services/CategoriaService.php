<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Categoria;
use yii\data\ActiveDataProvider;

/**
 * Capa de Servicio para Categoria.
 *
 * El controlador no habla directamente con Categoria::find() / findOne() / save():
 * todo pasa por aqui. Asi, si manana los datos vienen de otro lado (ej. un
 * servicio web), solo cambia esta clase y el controlador queda igual.
 */
class CategoriaService
{
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Categoria::find()->orderBy(['orden' => SORT_ASC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }

    public function obtener(int $id): ?Categoria
    {
        return Categoria::findOne($id);
    }

    /**
     * Carga los datos del formulario (el arreglo completo de POST, Yii toma
     * solo la parte "Categoria[...]") y guarda. Devuelve false si no llegaron datos
     * o si la validacion fallo -- en ese caso los errores quedan en
     * $categoria->errors para que la vista los muestre.
     */
    public function crear(Categoria $categoria, array $datosFormulario): bool
    {
        return $categoria->load($datosFormulario) && $categoria->save();
    }

    public function actualizar(Categoria $categoria, array $datosFormulario): bool
    {
        return $categoria->load($datosFormulario) && $categoria->save();
    }

    public function eliminar(Categoria $categoria): bool
    {
        return $categoria->delete() !== false;
    }
}
