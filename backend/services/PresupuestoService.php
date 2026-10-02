<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\PresupuestoPdfGenerator;
use backend\models\CotizacionDetalle;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use yii\data\ActiveDataProvider;

/**
 * Capa de Servicio para Presupuesto y sus lineas (PresupuestoDetalle).
 *
 * Es el servicio con mas reglas de negocio del sistema:
 *  - que presupuestos se pueden editar (aprobado/cancelado NO),
 *  - que cambios de estado son validos,
 *  - como se crea una nueva version/fase (VACIA, sin copiar lineas),
 *  - el precio unitario "congelado" al agregar una linea,
 *  - la agrupacion por categoria con subtotales y total general.
 */
class PresupuestoService
{
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Presupuesto::find()->with('proyecto')->orderBy(['id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }

    public function obtener(int $id): ?Presupuesto
    {
        return Presupuesto::findOne($id);
    }

    public function crear(Presupuesto $presupuesto, array $datosFormulario): bool
    {
        return $presupuesto->load($datosFormulario) && $presupuesto->save();
    }

    /**
     * Rechaza (devuelve false sin tocar nada) si el presupuesto no es editable.
     */
    public function actualizar(Presupuesto $presupuesto, array $datosFormulario): bool
    {
        if (!$this->puedeEditarse($presupuesto)) {
            return false;
        }

        return $presupuesto->load($datosFormulario) && $presupuesto->save();
    }

    public function eliminar(Presupuesto $presupuesto): bool
    {
        return $presupuesto->delete() !== false;
    }

    /**
     * REGLA DE NEGOCIO CRITICA: un presupuesto 'aprobado' o 'cancelado' no se
     * edita directamente; cualquier cambio requiere crearNuevaVersion().
     *
     * La condicion en si esta en Presupuesto::puedeEditarse() porque las
     * vistas (view.php, detalle.php) la consultan para mostrar u ocultar
     * botones. Los controladores la consultan SIEMPRE por aqui.
     */
    public function puedeEditarse(Presupuesto $presupuesto): bool
    {
        return $presupuesto->puedeEditarse();
    }

    /**
     * Valida la transicion contra Presupuesto::transicionesPermitidas() (la
     * unica fuente de verdad del ciclo de vida, tambien usada por la vista
     * para dibujar los botones de estado).
     */
    public function puedeTransicionarA(Presupuesto $presupuesto, string $nuevoEstado): bool
    {
        return in_array($nuevoEstado, Presupuesto::transicionesPermitidas()[$presupuesto->estado] ?? [], true);
    }

    /**
     * Cambia el estado solo si la transicion es valida. Si no lo es, devuelve
     * false sin tocar la base de datos.
     */
    public function cambiarEstado(Presupuesto $presupuesto, string $nuevoEstado): bool
    {
        if (!$this->puedeTransicionarA($presupuesto, $nuevoEstado)) {
            return false;
        }

        $presupuesto->estado = $nuevoEstado;

        return $presupuesto->save(false);
    }

    /**
     * Crea una nueva version (FASE) de un presupuesto aprobado/cancelado:
     * copia los datos de cabecera, incrementa numeroVersion, enlaza
     * idVersionAnterior al original y deja la nueva version en 'en_proceso'.
     *
     * DECISION DE DISENO: la nueva version NACE VACIA, sin copiar las lineas
     * de PresupuestoDetalle del original. Cada version representa solo el
     * incremento o cambio solicitado sobre un presupuesto ya aprobado, no un
     * snapshot completo. Ejemplo: un presupuesto aprobado con 5 camaras, al
     * que luego se le pide 1 camara adicional, genera una version 2 que
     * contiene UNICAMENTE esa linea nueva. La version anterior queda intacta
     * como historial.
     *
     * PENDIENTE: como consecuencia, el total real de un proyecto ya no esta
     * en una sola version -- falta un reporte consolidado que sume las
     * lineas de TODAS las versiones aprobadas de un mismo proyecto.
     *
     * Devuelve null si el original todavia es editable (no corresponde
     * versionarlo) o si no se pudo guardar.
     */
    public function crearNuevaVersion(Presupuesto $original): ?Presupuesto
    {
        if ($this->puedeEditarse($original)) {
            return null;
        }

        $nueva = new Presupuesto();
        $nueva->idProyecto = $original->idProyecto;
        $nueva->idVersionAnterior = $original->id;
        $nueva->numeroVersion = $original->numeroVersion + 1;
        $nueva->numeroPresupuesto = $original->numeroPresupuesto;
        $nueva->oficioDtic = $original->oficioDtic;
        $nueva->areaIntervencion = $original->areaIntervencion;
        $nueva->fecha = $original->fecha;
        $nueva->estado = Presupuesto::ESTADO_EN_PROCESO;

        // Sin copiar PresupuestoDetalle: la fase nueva empieza vacia.
        return $nueva->save() ? $nueva : null;
    }

    /**
     * Linea vacia, ya asociada al presupuesto, para el formulario de
     * "Agregar linea".
     */
    public function nuevaLinea(Presupuesto $presupuesto): PresupuestoDetalle
    {
        $linea = new PresupuestoDetalle();
        $linea->idPresupuesto = $presupuesto->id;

        return $linea;
    }

    /**
     * Agrega una linea al presupuesto, congelando su precio unitario. Devuelve
     * false si el presupuesto no es editable, si no llegaron datos, o si la
     * validacion fallo (los errores quedan en $linea->errors).
     */
    public function agregarLinea(Presupuesto $presupuesto, PresupuestoDetalle $linea, array $datosFormulario): bool
    {
        if (!$this->puedeEditarse($presupuesto) || !$linea->load($datosFormulario)) {
            return false;
        }

        // Se fuerza despues de load() para que el formulario no pueda
        // "mover" la linea a otro presupuesto.
        $linea->idPresupuesto = $presupuesto->id;

        if (!$linea->validate()) {
            return false;
        }

        $this->congelarPrecio($linea);

        return $linea->save(false);
    }

    public function obtenerLinea(int $idLinea): ?PresupuestoDetalle
    {
        return PresupuestoDetalle::findOne($idLinea);
    }

    /**
     * Rechaza (devuelve false sin borrar) si el presupuesto de la linea no
     * es editable.
     */
    public function eliminarLinea(PresupuestoDetalle $linea): bool
    {
        if (!$this->puedeEditarse($linea->presupuesto)) {
            return false;
        }

        return $linea->delete() !== false;
    }

    /**
     * Agrupa las lineas del presupuesto por el nombre de la categoria de su
     * item de subcatalogo. Devuelve un arreglo:
     *   ['Cable redes' => ['orden' => int, 'lineas' => PresupuestoDetalle[], 'subtotal' => float], ...]
     * ordenado segun el campo "orden" de Categoria.
     */
    public function agruparPorCategoria(Presupuesto $presupuesto): array
    {
        $lineas = $presupuesto->getPresupuestoDetalles()
            ->with(['subcatalogoItem.categoria'])
            ->all();

        $grupos = [];

        foreach ($lineas as $linea) {
            $categoria = $linea->subcatalogoItem->categoria ?? null;
            $nombreCategoria = $categoria->nombre ?? 'Sin categoría';
            $ordenCategoria = $categoria->orden ?? 999;

            if (!isset($grupos[$nombreCategoria])) {
                $grupos[$nombreCategoria] = [
                    'orden' => $ordenCategoria,
                    'lineas' => [],
                    'subtotal' => 0.0,
                ];
            }

            $grupos[$nombreCategoria]['lineas'][] = $linea;
            $grupos[$nombreCategoria]['subtotal'] += (float) $linea->precioTotal;
        }

        uasort($grupos, static fn ($a, $b) => $a['orden'] <=> $b['orden']);

        return $grupos;
    }

    /**
     * @param array $grupos resultado de agruparPorCategoria()
     */
    public function totalGeneral(array $grupos): float
    {
        return (float) array_sum(array_column($grupos, 'subtotal'));
    }

    /**
     * Convierte el HTML ya renderizado (la vista "pdf" la arma el
     * controlador, porque renderizar vistas es tarea de la capa de
     * Presentacion) en el PDF consolidado.
     *
     * @return array{contenido: string, nombreArchivo: string}
     */
    public function generarPdf(Presupuesto $presupuesto, string $html): array
    {
        $generador = new PresupuestoPdfGenerator();

        return [
            'contenido' => $generador->generarDesdeHtml($html),
            'nombreArchivo' => $generador->nombreArchivo($presupuesto),
        ];
    }

    /**
     * REGLA DE NEGOCIO CRITICA -- precio congelado: si la linea viene de una
     * cotizacion, su precioUnitario se copia desde CotizacionDetalle UNA SOLA
     * VEZ, aqui, al crear la linea. No existe (ni debe existir) ningun codigo
     * que vuelva a sincronizarlo despues: si el precio de la cotizacion
     * cambia, las lineas ya guardadas se quedan con el precio viejo a
     * proposito.
     */
    private function congelarPrecio(PresupuestoDetalle $linea): void
    {
        if (empty($linea->idCotizacionDetalle)) {
            return;
        }

        $cotizacionDetalle = CotizacionDetalle::findOne($linea->idCotizacionDetalle);

        if ($cotizacionDetalle === null) {
            return;
        }

        $linea->precioUnitario = $cotizacionDetalle->precioUnitario;

        if (empty($linea->unidadMedida) && !empty($cotizacionDetalle->subcatalogoItem->unidadDefecto)) {
            $linea->unidadMedida = $cotizacionDetalle->subcatalogoItem->unidadDefecto;
        }
    }
}
