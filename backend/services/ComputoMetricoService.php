<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\ComputoMetrico;
use yii\data\ActiveDataProvider;

/**
 * Capa de Servicio para ComputoMetrico.
 *
 * El controlador no habla directamente con ComputoMetrico::find() / findOne() / save():
 * todo pasa por aqui. Asi, si manana los datos vienen de otro lado (ej. un
 * servicio web), solo cambia esta clase y el controlador queda igual.
 *
 * Recordatorio de alcance: el sistema SOLO ALMACENA los computos metricos
 * (con su referencia de origen: plano, relevamiento). Nunca los calcula, por
 * eso este servicio no tiene ningun metodo de calculo.
 */
class ComputoMetricoService
{
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => ComputoMetrico::find()->with('proyecto')->orderBy(['id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }

    public function obtener(int $id): ?ComputoMetrico
    {
        return ComputoMetrico::findOne($id);
    }

    /**
     * Carga los datos del formulario (el arreglo completo de POST, Yii toma
     * solo la parte "ComputoMetrico[...]") y guarda. Devuelve false si no llegaron datos
     * o si la validacion fallo -- en ese caso los errores quedan en
     * $computo->errors para que la vista los muestre.
     */
    public function crear(ComputoMetrico $computo, array $datosFormulario): bool
    {
        return $computo->load($datosFormulario) && $computo->save();
    }

    public function actualizar(ComputoMetrico $computo, array $datosFormulario): bool
    {
        return $computo->load($datosFormulario) && $computo->save();
    }

    public function eliminar(ComputoMetrico $computo): bool
    {
        return $computo->delete() !== false;
    }
}
