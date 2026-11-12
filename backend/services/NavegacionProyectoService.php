<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Presupuesto;
use backend\models\Proyecto;

/**
 * Como se recorre un proyecto en las pantallas: la BARRA DE PASOS (Datos,
 * Plantilla, Lista, Cotizacion, Presupuesto; sale en todas las pantallas de
 * un paso), el enlace al paso Presupuesto y la FRANJA "FASES" de la vista
 * del proyecto. Solo lee: no cambia datos.
 */
class NavegacionProyectoService
{
    private FichaTecnicaService $ficha;

    public function __construct()
    {
        $this->ficha = new FichaTecnicaService();
    }

    /**
     * Titulo del paso de la lista en la barra de pasos (ver pasos()). La
     * pantalla de la lista lo usa para resaltarse.
     */
    public const PASO_LISTA = 'Lista de ítems y especificaciones';

    /**
     * Pasos del proyecto, para la barra de navegacion de su pantalla
     * principal. Cada paso dice en que estado esta:
     *  - 'hecho'      ya se completo
     *  - 'actual'     es el siguiente por hacer
     *  - 'pendiente'  todavia no corresponde
     * y la ruta a la que lleva ('url' => null: ese paso ya no se puede
     * abrir en el estado actual del proyecto, ej. Plantilla con la lista
     * enviada a cotizar).
     *
     * @return array<int, array{titulo: string, estado: string, url: array|null}>
     */
    public function pasos(Proyecto $proyecto): array
    {
        $presupuesto = $proyecto->presupuesto;
        $urlPresupuesto = $this->urlPresupuesto($proyecto);

        $listaEnEdicion = $proyecto->estadoLista === Proyecto::LISTA_EDICION;

        if ($presupuesto === null) {
            // El presupuesto nace de la lista: mientras la lista este en
            // edicion, el paso actual es la lista (o la plantilla).
            $estadoPresupuesto = $listaEnEdicion ? 'pendiente' : 'actual';
        } else {
            $estadoPresupuesto = $presupuesto->puedeEditarse() ? 'actual' : 'hecho';
        }

        if (!$proyecto->listaIniciada) {
            $estadoLista = 'pendiente';
        } else {
            $estadoLista = $listaEnEdicion ? 'actual' : 'hecho';
        }

        return [
            ['titulo' => 'Datos', 'estado' => 'hecho', 'url' => ['/proyecto/view', 'id' => $proyecto->id]],
            [
                'titulo' => 'Plantilla',
                'estado' => $proyecto->listaIniciada ? 'hecho' : 'actual',
                // Con el proyecto cerrado o la lista ya enviada a cotizacion,
                // la pantalla de plantilla ya no aplica.
                'url' => $proyecto->esEditable() && $listaEnEdicion ? ['/proyecto/plantilla', 'id' => $proyecto->id] : null,
            ],
            // Un solo paso: las especificaciones se enlazan item por item EN la
            // lista (son opcionales), en la misma pantalla y al mismo tiempo.
            ['titulo' => self::PASO_LISTA, 'estado' => $estadoLista, 'url' => ['/lista-items/index', 'id' => $proyecto->id]],
            [
                // Solicitudes de cotizacion: se arman con la lista ya enviada a cotizar.
                'titulo' => 'Cotización',
                'estado' => $listaEnEdicion ? 'pendiente' : ($presupuesto === null ? 'actual' : 'hecho'),
                'url' => ['/solicitud-cotizacion/index', 'id' => $proyecto->id],
            ],
            ['titulo' => 'Presupuesto', 'estado' => $estadoPresupuesto, 'url' => $urlPresupuesto],
        ];
    }

    /**
     * El paso "Presupuesto" del proyecto: la pantalla unica donde se elabora
     * (ElaborarPresupuestoController).
     */
    public function urlPresupuesto(Proyecto $proyecto): array
    {
        return ['/elaborar-presupuesto/index', 'idProyecto' => $proyecto->id];
    }

    /**
     * La franja "FASES" de la vista del proyecto (2026-11-10), encima de la
     * barra de pasos: un grupo por fase, cada uno con la fase y despues sus
     * ordenes de cambio, con el presupuesto y su total. Asi se pasa de una
     * fase a otra sin bajar hasta el recuadro "Fases del proyecto". En una
     * orden de cambio, las fases de su fase de origen. VACIA (no se muestra)
     * con fase unica y sin ordenes de cambio.
     *
     * @return array<int, array<int, array{id: int, texto: string, presupuesto: ?Presupuesto, monto: ?float, actual: bool}>>
     */
    public function franjaFases(Proyecto $proyecto): array
    {
        $base = $proyecto->esOrdenCambio() ? $proyecto->faseOrigen : $proyecto;
        /** @var Proyecto[] $fases */
        $fases = $base->numeroProyecto !== null && $base->fases !== [] ? $base->fases : [$base];

        if (count($fases) === 1 && $fases[0]->ordenesCambio === []) {
            return [];
        }

        $grupos = [];
        foreach ($fases as $fase) {
            $grupo = [];
            foreach (array_merge([$fase], $fase->ordenesCambio) as $fila) {
                $grupo[] = [
                    'id' => (int) $fila->id,
                    'texto' => $fila->esOrdenCambio() ? $fila->textoOrdenCambio() : 'Fase ' . (int) $fila->numeroFase,
                    'presupuesto' => $fila->presupuesto,
                    'monto' => $this->ficha->montoPresupuesto($fila),
                    'actual' => (int) $fila->id === (int) $proyecto->id,
                ];
            }
            $grupos[] = $grupo;
        }

        return $grupos;
    }
}
