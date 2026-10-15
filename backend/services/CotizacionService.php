<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Cotizacion;
use backend\models\CotizacionDetalle;
use backend\models\PresupuestoDetalle;
use backend\models\ProyectoItem;
use backend\models\SolicitudCotizacion;
use Throwable;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * Capa de Servicio para Cotizacion y sus lineas (CotizacionDetalle).
 *
 * Una cotizacion tiene varias lineas; al actualizarla, cabecera y lineas se
 * guardan dentro de UNA transaccion: o se guarda todo, o no se guarda nada.
 *
 * Reglas de negocio que viven aqui:
 *  - la cotizacion de un proveedor se registra DESDE la solicitud del
 *    proyecto a la que responde; una solicitud puede tener varias (una por
 *    proveedor),
 *  - en ese caso el item, la cantidad y la especificacion de cada linea se
 *    toman de la lista del proyecto, no del formulario; el usuario solo
 *    escribe marca/modelo y precio. Un item sin precio = el proveedor no lo
 *    cotizo (no se guarda esa linea),
 *  - una linea que ya se usa en un presupuesto no se borra ni cambia de
 *    precio (el presupuesto quedaria citando un precio que ya no existe).
 */
class CotizacionService
{
    private const UPLOAD_SUBPATH = 'uploads/cotizaciones';

    /**
     * Cotizacion nueva para registrar la respuesta de un proveedor a la
     * solicitud. La fecha por defecto es hoy (se puede cambiar).
     */
    public function nuevaDesdeSolicitud(SolicitudCotizacion $solicitud): Cotizacion
    {
        $cotizacion = new Cotizacion();
        $cotizacion->idSolicitudCotizacion = $solicitud->id;
        $cotizacion->fecha = date('Y-m-d');

        return $cotizacion;
    }

    /**
     * Filas del formulario "cotizacion recibida": una por cada item de la
     * solicitud, en su orden. Si la cotizacion ya existe, cada fila trae la
     * linea guardada de ese item; si no, una linea nueva sin precio.
     *
     * @return CotizacionDetalle[] indexadas por idProyectoItem, con la relacion proyectoItem cargada
     */
    public function filasDesdeSolicitud(SolicitudCotizacion $solicitud, Cotizacion $cotizacion): array
    {
        $guardadas = $cotizacion->isNewRecord
            ? []
            : ArrayHelper::index($cotizacion->getCotizacionDetalles()->andWhere(['not', ['idProyectoItem' => null]])->all(), 'idProyectoItem');

        $filas = [];

        foreach ($solicitud->getItems()->with(['proyectoItem.subcatalogoItem', 'proyectoItem.especificacion'])->all() as $itemSolicitud) {
            $item = $itemSolicitud->proyectoItem;

            if ($item === null) {
                continue;
            }

            $detalle = $guardadas[$item->id] ?? new CotizacionDetalle();
            $detalle->populateRelation('proyectoItem', $item);
            $filas[$item->id] = $detalle;
        }

        return $filas;
    }

    /**
     * Registra o corrige la cotizacion de un proveedor a la solicitud, todo
     * en UNA transaccion. Formulario:
     *   Cotizacion[idProveedor|numeroReferencia|fecha|validezDias] (+ PDF aparte)
     *   filas[idProyectoItem][marcaModelo]
     *   filas[idProyectoItem][precioUnitario]   '' = el proveedor no cotizo ese item
     *
     * Devuelve false si algo no es valido: los errores de la cabecera quedan
     * en $cotizacion y los de las filas en $errores. $filas vuelve con lo
     * enviado, para mostrarlo otra vez.
     *
     * @param CotizacionDetalle[] $filas (por referencia) ver filasDesdeSolicitud()
     * @param string[] $errores (por referencia)
     */
    public function guardarDesdeSolicitud(SolicitudCotizacion $solicitud, Cotizacion $cotizacion, array $datosFormulario, ?UploadedFile $archivo, array &$filas = [], array &$errores = []): bool
    {
        $errores = [];
        $filas = $this->filasDesdeSolicitud($solicitud, $cotizacion);

        if (!(new SolicitudCotizacionService())->puedeEditarse($solicitud->proyecto)) {
            $errores[] = 'Las cotizaciones de este proyecto ya no se modifican (la lista no está en cotización o el presupuesto ya no es editable).';

            return false;
        }

        if ((int) $cotizacion->idSolicitudCotizacion !== (int) $solicitud->id) {
            $errores[] = 'La cotización no pertenece a esta solicitud.';

            return false;
        }

        $cotizacion->load($datosFormulario);
        $cotizacion->documentoFile = $archivo;

        $enviadas = (array) ($datosFormulario['filas'] ?? []);
        $usadas = $this->idsUsadosEnPresupuesto(array_filter(ArrayHelper::getColumn($filas, 'id')));
        $aGuardar = [];
        $aBorrar = [];

        foreach ($filas as $idItem => $detalle) {
            $datos = is_array($enviadas[$idItem] ?? null) ? $enviadas[$idItem] : [];
            $item = $detalle->proyectoItem;
            $nombre = $item->subcatalogoItem->descripcion ?? "Ítem $idItem";
            $precio = $this->texto($datos['precioUnitario'] ?? '');
            $usada = !$detalle->isNewRecord && isset($usadas[$detalle->id]);

            $precioAnterior = $detalle->precioUnitario;
            $detalle->marcaModelo = $this->texto($datos['marcaModelo'] ?? '');
            $detalle->precioUnitario = $precio;

            if ($precio === '') {
                if ($usada) {
                    $errores[] = "$nombre: su precio ya se usa en el presupuesto, no se puede quitar.";
                } elseif (!$detalle->isNewRecord) {
                    $aBorrar[] = $detalle->id;
                }

                continue;
            }

            if ($usada && (float) $precio !== (float) $precioAnterior) {
                $errores[] = "$nombre: su precio ya se usa en el presupuesto, no se puede cambiar.";

                continue;
            }

            // Lo que identifica la linea sale de la lista, nunca del formulario.
            $detalle->idProyectoItem = $item->id;
            $detalle->idSubcatalogo = $item->idSubcatalogo;
            $detalle->cantidad = $item->cantidad;
            $detalle->idEspecificacion = $item->idEspecificacion;

            if (!$detalle->validate(['precioUnitario', 'marcaModelo', 'cantidad', 'idSubcatalogo'])) {
                $errores[] = "$nombre: " . implode(' ', $detalle->getFirstErrors());

                continue;
            }

            $aGuardar[] = $detalle;
        }

        if ($errores === [] && $aGuardar === []) {
            $errores[] = 'Escriba el precio de al menos un ítem (los que el proveedor no cotizó se dejan en blanco).';
        }

        $cabeceraValida = $cotizacion->validate();

        if (!$cabeceraValida || $errores !== []) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $this->guardarArchivoSubido($cotizacion);
            $cotizacion->save(false);

            if ($aBorrar !== []) {
                CotizacionDetalle::deleteAll(['id' => $aBorrar]);
            }

            foreach ($aGuardar as $detalle) {
                $detalle->idCotizacion = $cotizacion->id;
                $detalle->save(false);
            }

            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Cuadro comparativo de la solicitud: por cada item, el precio unitario
     * de cada proveedor y cual es el menor.
     *
     * @return array{cotizaciones: Cotizacion[], filas: array<int, array{item: ProyectoItem, precios: array<int, float>, menor: int|null}>}
     */
    public function comparativo(SolicitudCotizacion $solicitud): array
    {
        $cotizaciones = $solicitud->getCotizaciones()->with(['proveedor', 'cotizacionDetalles'])->all();
        $precios = [];

        foreach ($cotizaciones as $cotizacion) {
            foreach ($cotizacion->cotizacionDetalles as $detalle) {
                if ($detalle->idProyectoItem !== null) {
                    $precios[(int) $detalle->idProyectoItem][$cotizacion->id] = (float) $detalle->precioUnitario;
                }
            }
        }

        $filas = [];

        foreach ($solicitud->getItems()->with('proyectoItem.subcatalogoItem')->all() as $itemSolicitud) {
            $item = $itemSolicitud->proyectoItem;

            if ($item === null) {
                continue;
            }

            $preciosItem = $precios[$item->id] ?? [];
            $filas[] = [
                'item' => $item,
                'precios' => $preciosItem,
                'menor' => $preciosItem === [] ? null : array_search(min($preciosItem), $preciosItem, true),
            ];
        }

        return ['cotizaciones' => $cotizaciones, 'filas' => $filas];
    }

    /**
     * Si llega un proveedor, se listan solo sus cotizaciones (enlace "Ver
     * cotizaciones vinculadas" de la pantalla de Proveedores).
     */
    public function listar(?int $idProveedor = null): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Cotizacion::find()
                ->with(['proveedor', 'solicitud.proyecto'])
                ->andFilterWhere(['idProveedor' => $idProveedor])
                ->orderBy(['id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }

    public function obtener(int $id): ?Cotizacion
    {
        return Cotizacion::findOne($id);
    }

    /**
     * Crea solo la cabecera (las lineas se cargan despues, desde "Actualizar").
     * El PDF se recibe aparte porque los input type="file" no viajan en $_POST.
     */
    public function crear(Cotizacion $cotizacion, array $datosFormulario, ?UploadedFile $archivo): bool
    {
        if (!$cotizacion->load($datosFormulario)) {
            return false;
        }

        $cotizacion->documentoFile = $archivo;

        if (!$cotizacion->validate()) {
            return false;
        }

        $this->guardarArchivoSubido($cotizacion);

        return $cotizacion->save(false);
    }

    /**
     * Lineas a mostrar en el formulario de actualizacion. Si la cotizacion
     * todavia no tiene ninguna, se devuelve una fila vacia para que el
     * formulario tenga al menos una linea para llenar.
     *
     * @param CotizacionDetalle[]|null $detalles
     * @return CotizacionDetalle[]
     */
    public function detallesParaFormulario(Cotizacion $cotizacion, ?array $detalles = null): array
    {
        $detalles ??= $cotizacion->cotizacionDetalles;

        return empty($detalles) ? [new CotizacionDetalle()] : $detalles;
    }

    /**
     * Arma los modelos de linea a partir de lo enviado en el formulario:
     * reutiliza los existentes (filas que traen id) y crea nuevos para las
     * filas agregadas con "Anadir linea".
     *
     * @return CotizacionDetalle[]
     */
    public function cargarDetallesDesdeFormulario(Cotizacion $cotizacion, array $datosFormulario): array
    {
        $detalles = CotizacionDetalle::createMultiple(CotizacionDetalle::class, $cotizacion->cotizacionDetalles);
        Model::loadMultiple($detalles, $datosFormulario);

        return $detalles;
    }

    /**
     * Valida cabecera + lineas y, si todo es valido, guarda en una sola
     * transaccion: cabecera, borrado de lineas quitadas del formulario, y
     * alta/modificacion del resto de lineas.
     *
     * @param CotizacionDetalle[] $detalles resultado de cargarDetallesDesdeFormulario()
     */
    public function actualizar(Cotizacion $cotizacion, array $detalles, array $datosFormulario, ?UploadedFile $archivo): bool
    {
        // Las de una solicitud se corrigen desde la solicitud (ahi el item y
        // la cantidad salen de la lista y no se eligen a mano).
        if ($cotizacion->idSolicitudCotizacion !== null) {
            $cotizacion->addError('idProveedor', 'Esta cotización se registró desde una solicitud del proyecto: corríjala desde esa solicitud.');

            return false;
        }

        if (!$cotizacion->load($datosFormulario)) {
            return false;
        }

        $cotizacion->documentoFile = $archivo;

        // Lineas que existian antes pero ya no vinieron en el formulario:
        // el usuario las quito, hay que borrarlas.
        $idsAnteriores = ArrayHelper::map($cotizacion->cotizacionDetalles, 'id', 'id');
        $idsEnviados = array_filter(ArrayHelper::map($detalles, 'id', 'id'));
        $idsBorrados = array_diff($idsAnteriores, $idsEnviados);

        $valido = $cotizacion->validate();
        $valido = Model::validateMultiple($detalles) && $valido;

        $usadas = $this->idsUsadosEnPresupuesto($idsBorrados);
        if ($usadas !== []) {
            $cotizacion->addError('idProveedor', count($usadas) . ' línea(s) que quitó ya se usan en un presupuesto: no se pueden borrar.');
            $valido = false;
        }

        if (!$valido) {
            return false;
        }

        $this->guardarArchivoSubido($cotizacion);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $guardado = $cotizacion->save(false);

            if ($guardado && !empty($idsBorrados)) {
                CotizacionDetalle::deleteAll(['id' => $idsBorrados]);
            }

            if ($guardado) {
                foreach ($detalles as $detalle) {
                    $detalle->idCotizacion = $cotizacion->id;
                    if (!$detalle->save(false)) {
                        $guardado = false;
                        break;
                    }
                }
            }

            if ($guardado) {
                $transaction->commit();

                return true;
            }

            $transaction->rollBack();

            return false;
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Borra la cotizacion (sus lineas se borran en cascada). Devuelve null si
     * se elimino, o el motivo por el que no:
     *  - alguna linea ya se usa en un presupuesto,
     *  - es de una solicitud cuyo proyecto ya no admite cambios.
     */
    public function eliminar(Cotizacion $cotizacion): ?string
    {
        if ($cotizacion->solicitud !== null && !(new SolicitudCotizacionService())->puedeEditarse($cotizacion->solicitud->proyecto)) {
            return 'Las cotizaciones de este proyecto ya no se modifican.';
        }

        $ids = ArrayHelper::getColumn($cotizacion->cotizacionDetalles, 'id');

        if ($this->idsUsadosEnPresupuesto($ids) !== []) {
            return 'No se puede eliminar: sus precios ya se usan en un presupuesto.';
        }

        $cotizacion->delete();

        return null;
    }

    /**
     * De las lineas dadas, las que ya son el origen de una linea de
     * presupuesto: [idCotizacionDetalle => true].
     *
     * @param int[] $ids
     * @return array<int, true>
     */
    private function idsUsadosEnPresupuesto(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $usados = PresupuestoDetalle::find()
            ->select('idCotizacionDetalle')
            ->distinct()
            ->andWhere(['idCotizacionDetalle' => array_values($ids)])
            ->column();

        return array_fill_keys(array_map('intval', $usados), true);
    }

    /**
     * Garantiza un texto aunque en el formulario llegue un arreglo.
     *
     * @param mixed $valor
     */
    private function texto($valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    /**
     * Si el usuario adjuntó un PDF nuevo, lo guarda en backend/web/uploads/cotizaciones
     * y actualiza documentoRuta con la ruta relativa (la que se guarda en la BD).
     * Si no adjuntó nada, documentoRuta queda tal como estaba.
     */
    private function guardarArchivoSubido(Cotizacion $cotizacion): void
    {
        if ($cotizacion->documentoFile === null) {
            return;
        }

        $uploadDir = Yii::getAlias('@backend/web/' . self::UPLOAD_SUBPATH);
        FileHelper::createDirectory($uploadDir);

        $fileName = uniqid('cot_', true) . '.' . $cotizacion->documentoFile->extension;
        $cotizacion->documentoFile->saveAs($uploadDir . '/' . $fileName);

        $cotizacion->documentoRuta = self::UPLOAD_SUBPATH . '/' . $fileName;
    }
}
