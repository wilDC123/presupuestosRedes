<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Cotizacion;
use backend\models\CotizacionDetalle;
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
 */
class CotizacionService
{
    private const UPLOAD_SUBPATH = 'uploads/cotizaciones';

    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Cotizacion::find()->with('proveedor')->orderBy(['id' => SORT_DESC]),
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

    public function eliminar(Cotizacion $cotizacion): bool
    {
        return $cotizacion->delete() !== false;
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
