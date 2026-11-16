<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\PresupuestoPdfGenerator;
use backend\components\UsuarioActual;
use backend\models\ConfiguracionPresupuesto;
use backend\models\HistorialProyecto;
use backend\models\ListaMaterialesAprobacion;
use backend\models\OrdenCambio;
use backend\models\Proyecto;
use DateTimeImmutable;

/**
 * Capa de Servicio para las VERSIONES del documento "Lista de materiales y
 * condiciones previas" (redes.ListaMaterialesAprobacion): emitir la version
 * siguiente al enviar la lista a cotizacion, saber que version imprime el PDF
 * (y si es borrador) y armar ese PDF.
 *
 * Se separo de ListaItemsService (que queda con editar la lista y sus
 * estados) porque una version emitida es un documento: no cambia aunque la
 * lista se reabra despues.
 */
class ListaVersionService
{
    /**
     * Crea la version siguiente de la lista (emitida, sin aprobacion): la 1.0
     * la primera vez, una mas despues de cada orden de cambio. Desde el
     * 2026-11-05 la lista no se aprueba (solo lleva "Elaborado por"): la
     * version queda con la fecha del envio, el usuario y la COPIA de las
     * firmas de la configuracion del presupuesto, y se anota en el historial
     * del proyecto. Se llama dentro de la transaccion de
     * ListaItemsService::enviarACotizacion().
     */
    public function emitir(Proyecto $proyecto): ListaMaterialesAprobacion
    {
        $historial = new HistorialService();
        $ultima = (int) ListaMaterialesAprobacion::find()->where(['idProyecto' => $proyecto->id])->max('version');

        $aprobacion = new ListaMaterialesAprobacion([
            'idProyecto' => $proyecto->id,
            'version' => $ultima + 1,
            'fechaAprobacion' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'usuario' => UsuarioActual::nombre(),
        ]);

        // COPIA de las firmas: una version emitida no cambia aunque despues se edite la configuracion.
        $configuracion = ConfiguracionPresupuesto::obtener();
        foreach (ListaMaterialesAprobacion::atributosFirmas() as $atributo) {
            $aprobacion->$atributo = $configuracion->$atributo;
        }

        $aprobacion->save(false);
        unset($proyecto->aprobacionesLista);

        $historial->registrar(
            $proyecto,
            HistorialProyecto::ACCION_LISTA_APROBADA,
            'Versión ' . ListaMaterialesAprobacion::versionTexto($aprobacion->version) . ', enviada a cotización'
        );

        return $aprobacion;
    }

    /**
     * Que version de la lista imprime el PDF y si es borrador: en edicion, la
     * SIGUIENTE a la ultima emitida (todavia sin enviar: marca de agua
     * BORRADOR, firmas de la configuracion de hoy); enviada o cerrada, la
     * ultima emitida ('aprobacion' = su fila, con la fecha del envio).
     *
     * 'fechaCreacion' (dd/mm/aaaa) es la de la cabecera: "arriba va la fecha
     * de creacion del documento" (revision del 2026-10-29, paso 9). La v01
     * nace con el proyecto; cada version siguiente, con la orden de cambio que
     * reabrio la lista (la 1.a orden crea la v02, la 2.a la v03...). '' si no
     * se puede saber (listas enviadas antes de que hubiera ordenes).
     *
     * @return array{version: string, borrador: bool, aprobacion: ListaMaterialesAprobacion|null, fechaCreacion: string}
     */
    public function documento(Proyecto $proyecto): array
    {
        $ultima = $proyecto->ultimaAprobacionLista();
        $borrador = $proyecto->estadoLista === Proyecto::LISTA_EDICION || $ultima === null;
        $version = $borrador ? ($ultima->version ?? 0) + 1 : (int) $ultima->version;

        if ($version === 1) {
            $creacion = $proyecto->fechaCreacion;
        } else {
            $creacion = OrdenCambio::find()
                ->select('fecha')
                ->where(['idProyecto' => $proyecto->id, 'alcance' => OrdenCambio::ALCANCE_LISTA])
                ->orderBy(['fecha' => SORT_ASC, 'id' => SORT_ASC])
                ->offset($version - 2)
                ->limit(1)
                ->scalar();
        }

        return [
            'version' => ListaMaterialesAprobacion::versionTexto($version),
            'borrador' => $borrador,
            'aprobacion' => $borrador ? null : $ultima,
            'fechaCreacion' => $creacion ? date('d/m/Y', strtotime((string) $creacion)) : '',
        ];
    }

    /**
     * Convierte el HTML de la vista "pdf" (la arma el controlador) en el PDF
     * de la lista, con el mismo generador que usa el presupuesto. $borrador:
     * marca de agua BORRADOR (version todavia sin enviar, ver documento()).
     *
     * @return array{contenido: string, nombreArchivo: string}
     */
    public function generarPdf(Proyecto $proyecto, string $html, bool $borrador = false): array
    {
        $referencia = $proyecto->codigoLista ?: ($proyecto->codigo ?: (string) $proyecto->id);

        return [
            'contenido' => (new PresupuestoPdfGenerator())->generarDesdeHtml($html, $borrador ? PresupuestoPdfGenerator::MARCA_BORRADOR : null),
            'nombreArchivo' => 'lista_materiales_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $referencia) . '.pdf',
        ];
    }
}
