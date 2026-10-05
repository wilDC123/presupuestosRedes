<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\OrdenCambio;
use backend\models\Proyecto;
use Throwable;
use Yii;
use yii\db\Expression;

/**
 * Capa de Servicio para las ordenes de cambio (redes.OrdenCambio).
 *
 * Regla de negocio: una lista ya enviada a cotizacion no se edita
 * libremente. Para modificarla hay que registrar una orden de cambio (motivo,
 * quien, cuando), que devuelve la lista a "en edicion". La orden queda
 * abierta hasta que la lista se vuelve a enviar a cotizacion.
 *
 * Por ahora solo reabre la lista; el alcance 'presupuesto' queda previsto.
 */
class OrdenCambioService
{
    /**
     * Ordenes del proyecto, la mas reciente primero.
     *
     * @return OrdenCambio[]
     */
    public function historial(Proyecto $proyecto): array
    {
        return OrdenCambio::find()
            ->andWhere(['idProyecto' => $proyecto->id])
            ->orderBy(['fecha' => SORT_DESC, 'id' => SORT_DESC])
            ->all();
    }

    /**
     * Registra la orden y reabre la lista, las dos cosas juntas (una
     * transaccion): nunca queda una lista reabierta sin su orden.
     *
     * Devuelve false si la lista no esta "en cotizacion" (no hay nada que
     * reabrir) o si falta el motivo (el error queda en $orden->errors).
     */
    public function solicitarCambioLista(Proyecto $proyecto, OrdenCambio $orden, array $datosFormulario, string $usuario): bool
    {
        if ($proyecto->estadoLista !== Proyecto::LISTA_EN_COTIZACION) {
            $orden->addError('motivo', 'La lista no está en cotización: no hay nada que reabrir.');

            return false;
        }

        $orden->load($datosFormulario);

        // Se asignan despues de load() para que el formulario no pueda cambiarlos.
        $orden->idProyecto = $proyecto->id;
        $orden->alcance = OrdenCambio::ALCANCE_LISTA;
        $orden->solicitadoPor = $usuario;
        $orden->estado = OrdenCambio::ESTADO_ABIERTA;

        if (!$orden->validate()) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $orden->save(false);
            $proyecto->updateAttributes(['estadoLista' => Proyecto::LISTA_EDICION]);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Cierra las ordenes abiertas de ese alcance. Lo llama ListaItemsService
     * cuando la lista se vuelve a enviar a cotizacion.
     */
    public function cerrarAbiertas(Proyecto $proyecto, string $alcance): void
    {
        OrdenCambio::updateAll(
            // Expression: se envia tal cual a SQL Server, que pone su fecha y hora.
            ['estado' => OrdenCambio::ESTADO_CERRADA, 'fechaCierre' => new Expression('GETDATE()')],
            ['idProyecto' => $proyecto->id, 'alcance' => $alcance, 'estado' => OrdenCambio::ESTADO_ABIERTA]
        );
    }
}
