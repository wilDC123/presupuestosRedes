<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Plantilla;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use Throwable;
use Yii;

/**
 * Capa de Servicio para la LISTA DE ITEMS de un proyecto (redes.ProyectoItem):
 * solo items y cantidades, sin precios.
 *
 * Por ahora cubre el paso "Plantilla": iniciar la lista copiando una
 * plantilla o empezarla en blanco.
 */
class ListaItemsService
{
    public function contar(Proyecto $proyecto): int
    {
        return (int) ProyectoItem::find()->andWhere(['idProyecto' => $proyecto->id])->count();
    }

    /**
     * COPIA los items de la plantilla a la lista del proyecto, con su
     * clasificacion, cantidad sugerida y observacion. No queda ningun
     * enlace entre la lista y la plantilla: si la plantilla cambia despues,
     * este proyecto no se entera.
     *
     * Si la lista ya tenia items, solo se agregan los items del subcatalogo
     * que la lista todavia no tiene; nunca se borra ni se duplica nada.
     *
     * @return int cuantos items se agregaron
     */
    public function usarPlantilla(Proyecto $proyecto, Plantilla $plantilla): int
    {
        // Lo que la lista ya tenia ANTES de cargar. Dentro de la plantilla
        // un item si puede repetirse (patch cord de 1 m y de 3 m).
        $yaEnLista = ProyectoItem::find()
            ->select('idSubcatalogo')
            ->andWhere(['idProyecto' => $proyecto->id])
            ->column();

        $orden = (int) ProyectoItem::find()->andWhere(['idProyecto' => $proyecto->id])->max('orden');
        $agregados = 0;

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach ($plantilla->getItems()->with('subcatalogoItem')->all() as $itemPlantilla) {
                if (in_array($itemPlantilla->idSubcatalogo, $yaEnLista, false)) {
                    continue;
                }

                $item = new ProyectoItem();
                $item->idProyecto = $proyecto->id;
                $item->idSubcatalogo = $itemPlantilla->idSubcatalogo;
                $item->idCategoria = $itemPlantilla->idCategoria;
                $item->cantidad = $itemPlantilla->cantidadSugerida ?? 0;
                $item->unidadMedida = $itemPlantilla->subcatalogoItem->unidadDefecto ?? null;
                $item->observacion = $itemPlantilla->observacion;
                $item->orden = ++$orden;
                $item->save(false);
                $agregados++;
            }

            // updateAttributes() escribe solo estas columnas, sin pasar por
            // la validacion ni tocar el resto del proyecto.
            $proyecto->updateAttributes([
                'idPlantillaOrigen' => $plantilla->id,
                'listaIniciada' => true,
            ]);

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return $agregados;
    }

    /**
     * "Omitir, empezar en blanco": el proyecto no usa plantilla y arma su
     * lista con items sueltos. Solo marca el paso como resuelto.
     */
    public function empezarEnBlanco(Proyecto $proyecto): void
    {
        $proyecto->updateAttributes(['listaIniciada' => true]);
    }
}
