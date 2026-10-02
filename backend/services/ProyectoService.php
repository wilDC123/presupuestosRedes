<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Proyecto;
use yii\data\ActiveDataProvider;

/**
 * Capa de Servicio para Proyecto.
 *
 * El controlador no habla directamente con Proyecto::find() / findOne() / save():
 * todo pasa por aqui. Asi, si manana los datos vienen de otro lado (ej. un
 * servicio web), solo cambia esta clase y el controlador queda igual.
 */
class ProyectoService
{
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Proyecto::find()->orderBy(['fechaCreacion' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }

    public function obtener(int $id): ?Proyecto
    {
        return Proyecto::findOne($id);
    }

    /**
     * Carga los datos del formulario (el arreglo completo de POST, Yii toma
     * solo la parte "Proyecto[...]") y guarda. Devuelve false si no llegaron datos
     * o si la validacion fallo -- en ese caso los errores quedan en
     * $proyecto->errors para que la vista los muestre.
     */
    public function crear(Proyecto $proyecto, array $datosFormulario): bool
    {
        return $proyecto->load($datosFormulario) && $proyecto->save();
    }

    public function actualizar(Proyecto $proyecto, array $datosFormulario): bool
    {
        return $proyecto->load($datosFormulario) && $proyecto->save();
    }

    public function eliminar(Proyecto $proyecto): bool
    {
        return $proyecto->delete() !== false;
    }
}
