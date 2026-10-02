<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Proveedor;
use yii\data\ActiveDataProvider;

/**
 * Capa de Servicio para Proveedor.
 *
 * El controlador no habla directamente con Proveedor::find() / findOne() / save():
 * todo pasa por aqui. Asi, si manana los datos vienen de otro lado (ej. un
 * servicio web), solo cambia esta clase y el controlador queda igual.
 */
class ProveedorService
{
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Proveedor::find()->orderBy(['razonSocial' => SORT_ASC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }

    public function obtener(int $id): ?Proveedor
    {
        return Proveedor::findOne($id);
    }

    /**
     * Carga los datos del formulario (el arreglo completo de POST, Yii toma
     * solo la parte "Proveedor[...]") y guarda. Devuelve false si no llegaron datos
     * o si la validacion fallo -- en ese caso los errores quedan en
     * $proveedor->errors para que la vista los muestre.
     */
    public function crear(Proveedor $proveedor, array $datosFormulario): bool
    {
        return $proveedor->load($datosFormulario) && $proveedor->save();
    }

    public function actualizar(Proveedor $proveedor, array $datosFormulario): bool
    {
        return $proveedor->load($datosFormulario) && $proveedor->save();
    }

    public function eliminar(Proveedor $proveedor): bool
    {
        return $proveedor->delete() !== false;
    }
}
