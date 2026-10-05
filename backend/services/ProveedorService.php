<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Cotizacion;
use backend\models\CotizacionDetalle;
use backend\models\Proveedor;
use yii\db\IntegrityException;

/**
 * Capa de Servicio para Proveedor.
 *
 * El controlador no habla directamente con Proveedor::find() / findOne() / save():
 * todo pasa por aqui. Asi, si manana los datos vienen de otro lado (ej. un
 * servicio web), solo cambia esta clase y el controlador queda igual.
 */
class ProveedorService
{
    /**
     * Proveedores para la pantalla de listado, cada uno con la cantidad de
     * cotizaciones que tiene registradas. Si llega un texto, se filtra por
     * razon social o NIT.
     *
     * El conteo se trae en UNA consulta agrupada (no una por proveedor) y se
     * cruza en PHP por id.
     *
     * @return array<int, array{proveedor: Proveedor, cotizaciones: int}>
     */
    public function listar(string $buscar = ''): array
    {
        $query = Proveedor::find()->orderBy(['razonSocial' => SORT_ASC]);

        if ($buscar !== '') {
            $query->andWhere(['or',
                ['like', 'razonSocial', $buscar],
                ['like', 'nit', $buscar],
            ]);
        }

        $conteos = Cotizacion::find()
            ->select(['total' => 'COUNT(*)', 'idProveedor'])
            ->groupBy('idProveedor')
            ->indexBy('idProveedor')
            ->asArray()
            ->column();

        $filas = [];

        foreach ($query->all() as $proveedor) {
            $filas[] = [
                'proveedor' => $proveedor,
                'cotizaciones' => (int) ($conteos[$proveedor->id] ?? 0),
            ];
        }

        return $filas;
    }

    public function obtener(int $id): ?Proveedor
    {
        return Proveedor::findOne($id);
    }

    /**
     * Datos del panel "Proveedor seleccionado": sus cotizaciones (de la mas
     * reciente a la mas antigua), cuanto suman y la fecha de la ultima.
     *
     * @return array{cotizaciones: Cotizacion[], montoTotal: float, ultimaCotizacion: Cotizacion|null}
     */
    public function resumen(Proveedor $proveedor): array
    {
        $cotizaciones = Cotizacion::find()
            ->andWhere(['idProveedor' => $proveedor->id])
            ->orderBy(['fecha' => SORT_DESC, 'id' => SORT_DESC])
            ->all();

        // precioTotal es la columna calculada (cantidad * precioUnitario) de
        // cada linea de cotizacion: SQL Server hace la suma.
        $montoTotal = (float) CotizacionDetalle::find()
            ->alias('d')
            ->innerJoin(['c' => Cotizacion::tableName()], 'c.id = d.idCotizacion')
            ->andWhere(['c.idProveedor' => $proveedor->id])
            ->sum('d.precioTotal');

        return [
            'cotizaciones' => $cotizaciones,
            'montoTotal' => $montoTotal,
            'ultimaCotizacion' => $cotizaciones[0] ?? null,
        ];
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

    /**
     * Devuelve false si el proveedor no se puede borrar porque tiene
     * cotizaciones (la llave foranea de redes.Cotizacion lo impide). En ese
     * caso lo correcto es desactivarlo, no borrarlo.
     */
    public function eliminar(Proveedor $proveedor): bool
    {
        try {
            return $proveedor->delete() !== false;
        } catch (IntegrityException $e) {
            return false;
        }
    }
}
