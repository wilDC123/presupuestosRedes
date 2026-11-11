<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\ConfiguracionSolicitudCotizacion;
use backend\models\CorrelativoDocumento;
use backend\models\Proceso;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SolicitudCotizacion;
use backend\models\SubcatalogoItem;
use backend\models\UnidadMedida;
use backend\services\CodificacionService;
use backend\services\SolicitudCotizacionService;
use Codeception\Test\Unit;
use Yii;
use yii\helpers\Html;

/**
 * Codigo de la solicitud de proforma de cotizacion (formatos corregidos v3,
 * 2026-11-09): RD-<proceso del proyecto>-SC-NNN, un solo correlativo para
 * todo el sistema, y la cabecera de todos los documentos.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class SolicitudCodigoTest extends Unit
{
    public function testUnaSolicitudNuevaTomaElSiguienteCodigoSc(): void
    {
        $item = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        $proceso = Proceso::findOne(['codigo' => 'GR']);
        if ($item === null || $proceso === null) {
            $this->markTestSkipped('La base local no tiene un ítem activo o el proceso GR.');
        }

        $proyecto = new Proyecto(['nombre' => 'Proyecto test código SC', 'idProceso' => $proceso->id, 'estadoLista' => Proyecto::LISTA_EN_COTIZACION]);
        $proyecto->save(false);
        $fila = new ProyectoItem(['idProyecto' => $proyecto->id, 'idSubcatalogo' => $item->id, 'idCategoria' => $item->idCategoria, 'cantidad' => 1, 'orden' => 1, 'unidadMedida' => 'm']);
        $fila->save(false);
        $siguiente = (int) (new CodificacionService())->correlativos()[CorrelativoDocumento::TIPO_SOLICITUD]->siguiente;

        $movidos = (new SolicitudCotizacionService())->mover($proyecto, [(string) $fila->id], null, 'Ferretería test', $error);
        $this->assertSame(1, $movidos, (string) $error);

        $solicitud = SolicitudCotizacion::findOne(['idProyecto' => $proyecto->id]);
        // Con el proceso del proyecto; el numero de pantalla (SC-01) sigue siendo del proyecto.
        $this->assertSame(sprintf('RD-GR-SC-%03d', $siguiente), $solicitud->codigoDocumento);
        $this->assertSame('SC-01', $solicitud->codigoCorto());
        $this->assertSame('GR', $solicitud->codigoProceso());
        $this->assertSame($siguiente, (new CodificacionService())->ultimoUsado(CorrelativoDocumento::TIPO_SOLICITUD));

        // La cabecera de todos los documentos: codigo, version 1.0 y el proceso.
        $html = Yii::$app->view->render('@backend/views/solicitud-cotizacion/pdf.php', [
            'solicitud' => $solicitud,
            'configuracion' => ConfiguracionSolicitudCotizacion::obtener(),
        ]);
        foreach ([
            'Código: ' . $solicitud->codigoDocumento, 'Versión: 1.0', 'PROCESO: ' . mb_strtoupper($proceso->nombre, 'UTF-8'),
            'Redes y Telecomunicaciones', 'logo-dtic-usfx.png', 'logo-redusfx.png', 'Fecha: ' . date('d-m-Y'),
        ] as $esperado) {
            $this->assertStringContainsString($esperado, $html);
        }
        $this->assertStringNotContainsString('N.° ', $html);
        // La unidad con su nombre, no la abreviatura (2026-11-10).
        $nombre = UnidadMedida::nombresPorAbreviatura()[(string) $fila->unidadMedida] ?? null;
        if ($nombre !== null) {
            $this->assertStringContainsString('<td>' . Html::encode($nombre) . '</td>', $html);
        }
        // Firma de la DTIC (2026-11-09, como el Word v3): solo "ELABORADO POR". Sin el espacio del
        // proveedor desde el 2026-11-10.
        $this->assertStringContainsString('ELABORADO POR', $html);
        $this->assertStringNotContainsString('APROBADO POR', $html);
        $this->assertStringNotContainsString('Firma y sello del proveedor', $html);
        // La version solo en la cabecera (2026-11-10): la tabla de firmas ya no la repite.
        $this->assertSame(1, substr_count($html, 'Versión:'));
    }
}
