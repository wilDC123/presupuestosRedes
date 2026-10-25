<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Presupuesto;
use backend\models\PresupuestoComplemento;
use backend\models\Proyecto;
use backend\models\SubcatalogoItem;
use backend\services\ElaborarPresupuestoService;
use Codeception\Test\Unit;

/**
 * Secciones sin precio del final del PDF (PresupuestoComplemento): se
 * guardan todas las filas de una seccion de una vez, solo en borrador, las
 * filas en blanco se ignoran y una fila invalida no deja guardar ninguna.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class ComplementosPresupuestoTest extends Unit
{
    private ElaborarPresupuestoService $service;

    protected function _before(): void
    {
        $this->service = new ElaborarPresupuestoService();
    }

    public function testReemplazaLasFilasDeLaSeccionEnSuOrdenEIgnoraLasVacias(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);

        $this->assertTrue($this->service->guardarComplementos($presupuesto, PresupuestoComplemento::TIPO_TRABAJO, [
            ['grupo' => 'Instalaciones eléctricas', 'descripcion' => 'Tomacorrientes en el rack', 'unidadEncargada' => 'Mantenimiento eléctrico'],
        ]));

        $errores = [];
        $guardado = $this->service->guardarComplementos($presupuesto, PresupuestoComplemento::TIPO_MATERIAL, [
            ['grupo' => 'Cableado y materiales de red', 'descripcion' => 'Patch cord', 'cantidad' => '4', 'unidadMedida' => 'pza', 'unidadEncargada' => 'se ignora'],
            ['grupo' => 'Cableado y materiales de red', 'descripcion' => '', 'cantidad' => '', 'observacion' => ''],
            ['grupo' => 'Cableado y materiales de red', 'descripcion' => 'Canaleta', 'cantidad' => '10.5', 'unidadMedida' => 'm'],
        ], $errores);

        $this->assertTrue($guardado, implode(' ', $errores));
        $materiales = $this->filas($presupuesto, PresupuestoComplemento::TIPO_MATERIAL);
        $this->assertSame(['Patch cord', 'Canaleta'], array_column($materiales, 'descripcion'));
        $this->assertNull($materiales[0]['unidadEncargada']);

        // Guardar de nuevo reemplaza (no agrega) y no toca la otra seccion.
        $this->assertTrue($this->service->guardarComplementos($presupuesto, PresupuestoComplemento::TIPO_MATERIAL, [
            ['grupo' => 'Equipos', 'descripcion' => 'Switch de 8 puertos', 'cantidad' => '1'],
        ]));
        $this->assertSame(['Switch de 8 puertos'], array_column($this->filas($presupuesto, PresupuestoComplemento::TIPO_MATERIAL), 'descripcion'));
        $this->assertCount(1, $this->filas($presupuesto, PresupuestoComplemento::TIPO_TRABAJO));

        // Sin filas, la seccion queda vacia (no sale en el PDF).
        $this->assertTrue($this->service->guardarComplementos($presupuesto, PresupuestoComplemento::TIPO_MATERIAL, []));
        $this->assertSame([], $this->filas($presupuesto, PresupuestoComplemento::TIPO_MATERIAL));
    }

    public function testUnaFilaInvalidaNoDejaGuardarNinguna(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);
        $errores = [];

        $this->assertFalse($this->service->guardarComplementos($presupuesto, PresupuestoComplemento::TIPO_MATERIAL, [
            ['grupo' => 'Cableado y materiales de red', 'descripcion' => 'Patch cord', 'cantidad' => '2'],
            ['grupo' => 'Cableado y materiales de red', 'descripcion' => 'Sin cantidad', 'cantidad' => '0'],
        ], $errores));
        $this->assertCount(1, $errores);
        $this->assertStringStartsWith('Fila 2:', $errores[0]);

        // Un trabajo solo va en uno de los subtitulos fijos.
        $this->assertFalse($this->service->guardarComplementos($presupuesto, PresupuestoComplemento::TIPO_TRABAJO, [
            ['grupo' => 'Otro subtítulo', 'descripcion' => 'Pintura'],
        ]));

        $this->assertSame([], $this->filas($presupuesto, PresupuestoComplemento::TIPO_MATERIAL));
        $this->assertSame([], $this->filas($presupuesto, PresupuestoComplemento::TIPO_TRABAJO));
    }

    public function testDelSubcatalogoTomaNombreYUnidadSiEstanVacios(): void
    {
        $item = SubcatalogoItem::find()->andWhere(['not', ['idUnidadMedida' => null]])->one();
        if ($item === null) {
            $this->markTestSkipped('La base local no tiene ítems con unidad.');
        }

        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);

        $this->assertTrue($this->service->guardarComplementos($presupuesto, PresupuestoComplemento::TIPO_MATERIAL, [
            ['grupo' => 'Equipos', 'idSubcatalogo' => (string) $item->id, 'descripcion' => '', 'cantidad' => '1', 'unidadMedida' => ''],
        ]));

        $fila = $this->filas($presupuesto, PresupuestoComplemento::TIPO_MATERIAL)[0];
        $this->assertSame($item->descripcion, $fila['descripcion']);
        $this->assertSame($item->abreviaturaUnidad(), $fila['unidadMedida']);
    }

    public function testNoSeCambiaFueraDeBorrador(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_REVISION);
        $errores = [];

        $this->assertFalse($this->service->guardarComplementos($presupuesto, PresupuestoComplemento::TIPO_MATERIAL, [
            ['grupo' => 'Equipos', 'descripcion' => 'Switch', 'cantidad' => '1'],
        ], $errores));
        $this->assertNotEmpty($errores);
        $this->assertSame([], $this->filas($presupuesto, PresupuestoComplemento::TIPO_MATERIAL));
    }

    private function filas(Presupuesto $presupuesto, string $tipo): array
    {
        return PresupuestoComplemento::find()
            ->andWhere(['idPresupuesto' => $presupuesto->id, 'tipo' => $tipo])
            ->orderBy(['orden' => SORT_ASC])
            ->asArray()
            ->all();
    }

    private function crearPresupuesto(string $estado): Presupuesto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test complementos', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);

        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => $estado]);
        $presupuesto->save(false);

        return $presupuesto;
    }
}
