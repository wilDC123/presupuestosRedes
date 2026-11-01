<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\CatalogoSigma;
use backend\models\Categoria;
use backend\models\ConfiguracionPresupuesto;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\SubcatalogoItem;
use backend\services\PresupuestoService;
use Codeception\Test\Unit;
use Yii;

/**
 * Lo que los PDF del presupuesto reciben ya armado del servicio (hallazgo 3
 * de la auditoria de code smells): donde va la mano de obra de la DTIC y los
 * conteos del reporte SIGMA.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class PresupuestoPdfTest extends Unit
{
    private PresupuestoService $service;

    protected function _before(): void
    {
        $this->service = new PresupuestoService();
    }

    public function testSinCategoriaManoDeObraVaEnUnaTablaAlFinal(): void
    {
        $grupos = $this->service->ubicarManoDeObra($this->presupuesto(true), [
            'Cable redes' => $this->grupo(1, 100.0),
        ]);

        $this->assertSame(['Cable redes', Categoria::NOMBRE_MANO_DE_OBRA], array_keys($grupos));
        $this->assertTrue($grupos[Categoria::NOMBRE_MANO_DE_OBRA]['manoDeObra']);
        $this->assertSame(0.0, $grupos[Categoria::NOMBRE_MANO_DE_OBRA]['subtotal']);
        $this->assertFalse($grupos['Cable redes']['manoDeObra']);
    }

    public function testConCategoriaManoDeObraVaDentroDeEsa(): void
    {
        // Sin importar mayusculas ni espacios.
        $grupos = $this->service->ubicarManoDeObra($this->presupuesto(true), [
            ' MANO de obra ' => $this->grupo(7, 500.0),
            'Cable redes' => $this->grupo(1, 100.0),
        ]);

        $this->assertSame([' MANO de obra ', 'Cable redes'], array_keys($grupos));
        $this->assertTrue($grupos[' MANO de obra ']['manoDeObra']);
        $this->assertSame(500.0, $grupos[' MANO de obra ']['subtotal']);
    }

    public function testSinManoDeObraNoSeAgregaNada(): void
    {
        $grupos = $this->service->ubicarManoDeObra($this->presupuesto(false), [
            'Mano de obra' => $this->grupo(7, 500.0),
        ]);

        $this->assertSame(['Mano de obra'], array_keys($grupos));
        $this->assertFalse($grupos['Mano de obra']['manoDeObra']);
    }

    public function testElPdfMuestraLaManoDeObraUnaSolaVez(): void
    {
        $presupuesto = $this->presupuesto(true);

        foreach ([[], ['Mano de obra' => $this->grupo(7, 0.0)]] as $antes) {
            $grupos = $this->service->ubicarManoDeObra($presupuesto, $antes);
            $html = Yii::$app->view->render('@backend/views/presupuesto/pdf.php', [
                'presupuesto' => $presupuesto,
                'grupos' => $grupos,
                'totalGeneral' => 0.0,
                'configuracion' => ConfiguracionPresupuesto::obtener(),
                'oficio' => '',
                'proceso' => 'Proceso test',
            ]);

            $this->assertSame(1, preg_match_all('/<td width="45%">\s*MANO DE OBRA\s*<br>/', $html), 'La fila de la DTIC sale una vez.');
            $this->assertSame(1, preg_match_all('/<div class="categoria-titulo[^"]*">Mano de obra<\/div>/', $html), 'Una sola tabla de mano de obra.');
        }
    }

    public function testResumenSigma(): void
    {
        $sigma = CatalogoSigma::find()->andWhere(['not', ['IdGasto' => null]])->one();
        if ($sigma === null) {
            $this->markTestSkipped('La base local no tiene ítems SIGMA con partida.');
        }

        $conSigma = new SubcatalogoItem(['descripcion' => 'X']);
        $conSigma->populateRelation('catalogoSigma', $sigma);
        $sinSigma = new SubcatalogoItem(['descripcion' => 'Y']);
        $sinSigma->populateRelation('catalogoSigma', null);

        $linea = static function (SubcatalogoItem $item, string $partida): PresupuestoDetalle {
            $detalle = new PresupuestoDetalle(['partida' => $partida]);
            $detalle->populateRelation('subcatalogoItem', $item);

            return $detalle;
        };

        $resumen = $this->service->resumenSigma([
            'A' => ['lineas' => [$linea($conSigma, (string) $sigma->IdGasto), $linea($conSigma, '99999')]],
            'B' => ['lineas' => [$linea($sinSigma, '39800')]],
        ]);

        $this->assertSame(['totalLineas' => 3, 'conSigma' => 2, 'partidasDistintas' => 1], $resumen);
    }

    private function presupuesto(bool $conManoDeObra): Presupuesto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test PDF', 'estadoLista' => Proyecto::LISTA_EN_COTIZACION]);
        $proyecto->save(false);
        $presupuesto = new Presupuesto([
            'idProyecto' => $proyecto->id,
            'estado' => Presupuesto::ESTADO_EN_PROCESO,
            'manoDeObra' => $conManoDeObra ? Presupuesto::MANO_DE_OBRA_DTIC : null,
        ]);
        $presupuesto->save(false);

        return Presupuesto::findOne($presupuesto->id);
    }

    private function grupo(int $orden, float $subtotal): array
    {
        return ['orden' => $orden, 'lineas' => [], 'subtotal' => $subtotal];
    }
}
