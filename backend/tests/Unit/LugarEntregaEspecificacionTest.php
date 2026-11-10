<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Categoria;
use backend\models\CorrelativoDocumento;
use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\HistorialProyecto;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use backend\services\EspecificacionService;
use backend\services\ListaItemsService;
use Codeception\Test\Unit;

/**
 * Revision del Ing. de DTIC del 2026-11-05: el lugar de entrega es de CADA
 * especificacion (puede ser distinto), no del proyecto. Se escribe en la fila
 * de la lista: el formulario nuevo lo toma (vacio = el del proyecto) y
 * cambiarlo en uno ya emitido lo reemite con el mismo codigo.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class LugarEntregaEspecificacionTest extends Unit
{
    private ListaItemsService $lista;
    private Proyecto $proyecto;
    private SubcatalogoItem $item;
    private SubcatalogoItem $otroItem;
    private Especificacion $ficha;

    protected function _before(): void
    {
        $this->lista = new ListaItemsService();

        $items = SubcatalogoItem::find()->andWhere(['activo' => true])->orderBy(['id' => SORT_ASC])->limit(2)->all();
        if (count($items) < 2 || Categoria::find()->count() == 0) {
            $this->markTestSkipped('La base local necesita dos ítems activos en el subcatálogo.');
        }
        [$this->item, $this->otroItem] = $items;

        $this->proyecto = new Proyecto(['nombre' => 'Proyecto test lugar por especificación', 'lugarEntrega' => 'Almacén test del proyecto', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $this->proyecto->save(false);
        $this->proyecto->refresh();

        CorrelativoDocumento::updateAll(
            ['siguiente' => (int) EspecificacionEmision::find()->max('numeroNet') + 1000, 'anio' => (int) date('y')],
            ['tipo' => CorrelativoDocumento::TIPO_ESPECIFICACION]
        );

        $this->ficha = new Especificacion(['titulo' => 'Ficha test lugar', 'activo' => true]);
        $this->ficha->save(false);
        (new EspecificacionService())->guardarVersion($this->ficha, [], [['nombre' => 'Puertos', 'valor' => '8']]);
    }

    public function testCadaEspecificacionTomaElLugarDeSuFila(): void
    {
        $this->guardar([
            $this->fila(null, $this->item, 'Bloque B, sala de servidores'),
            // Vacio: el del proyecto.
            $this->fila(null, $this->otroItem, ''),
        ]);

        [$primero, $segundo] = $this->items();
        $this->assertSame('Bloque B, sala de servidores', $primero->emisionVigente->lugarEntrega);
        $this->assertSame('Almacén test del proyecto', $segundo->emisionVigente->lugarEntrega);
    }

    public function testCambiarElLugarReemiteElMismoCodigo(): void
    {
        $this->guardar([$this->fila(null, $this->item, '')]);
        [$item] = $this->items();
        $anterior = $item->emisionVigente;

        // Guardar con el mismo lugar (o vacio) no reemite.
        $this->guardar([$this->fila($item, $this->item, 'Almacén test del proyecto')]);
        $this->guardar([$this->fila($item, $this->item, '')]);
        [$item] = $this->items();
        $this->assertSame((int) $anterior->id, (int) $item->emisionVigente->id);

        $this->guardar([$this->fila($item, $this->item, 'Rectorado, almacén central')]);
        [$item] = $this->items();
        $nueva = $item->emisionVigente;
        $this->assertSame($anterior->codigo(), $nueva->codigo());
        $this->assertSame(2, (int) $nueva->versionDocumento);
        $this->assertSame('Rectorado, almacén central', $nueva->lugarEntrega);

        $anterior->refresh();
        $this->assertSame(EspecificacionEmision::ESTADO_REEMPLAZADA, $anterior->estado);
        $this->assertSame('Se cambió el lugar de entrega: Almacén test del proyecto → Rectorado, almacén central.', $anterior->motivo);
    }

    public function testCambiarItemYLugarALaVezEsUnaSolaReemision(): void
    {
        $this->guardar([$this->fila(null, $this->item, '')]);
        [$item] = $this->items();
        $anterior = $item->emisionVigente;

        $this->guardar([$this->fila($item, $this->otroItem, 'Bloque C')]);
        [$item] = $this->items();
        $this->assertSame(2, (int) $item->emisionVigente->versionDocumento);
        $this->assertSame('Bloque C', $item->emisionVigente->lugarEntrega);
        $anterior->refresh();
        $this->assertStringContainsString('Se cambió el ítem', $anterior->motivo);
        $this->assertStringContainsString('Se cambió el lugar de entrega', $anterior->motivo);
    }

    /**
     * 2026-11-10: el lugar por defecto se escribe ARRIBA DE LA LISTA (ya no al
     * crear el proyecto). Se guarda antes de emitir: las filas vacias toman el
     * nuevo en el mismo guardado, y queda en el historial.
     */
    public function testElLugarDeArribaDeLaListaLoTomanLasFilasVacias(): void
    {
        $errores = [];
        $enviados = [];
        $guardado = $this->lista->guardar(
            Proyecto::findOne($this->proyecto->id),
            [$this->fila(null, $this->item, ''), $this->fila(null, $this->otroItem, 'Bloque B')],
            $errores,
            $enviados,
            'encargado',
            null,
            null,
            '  Almacén test nuevo  '
        );
        $this->assertTrue($guardado, implode(' ', $errores));

        $this->assertSame('Almacén test nuevo', Proyecto::findOne($this->proyecto->id)->lugarEntrega);
        [$primero, $segundo] = $this->items();
        $this->assertSame('Almacén test nuevo', $primero->emisionVigente->lugarEntrega);
        $this->assertSame('Bloque B', $segundo->emisionVigente->lugarEntrega);
        $detalle = (string) HistorialProyecto::find()->select('detalle')
            ->andWhere(['idProyecto' => $this->proyecto->id, 'accion' => HistorialProyecto::ACCION_PROYECTO_MODIFICADO])->scalar();
        $this->assertSame('Lugar de entrega por defecto: Almacén test del proyecto → Almacén test nuevo', $detalle);

        // null = no se toca; '' = se borra (las filas vacias toman el de la configuracion).
        $this->assertTrue($this->lista->guardar(Proyecto::findOne($this->proyecto->id), [], $errores, $enviados, 'encargado'));
        $this->assertSame('Almacén test nuevo', Proyecto::findOne($this->proyecto->id)->lugarEntrega);
        $this->assertTrue($this->lista->guardar(Proyecto::findOne($this->proyecto->id), [], $errores, $enviados, 'encargado', null, null, ''));
        $this->assertNull(Proyecto::findOne($this->proyecto->id)->lugarEntrega);
    }

    /** El formulario del proyecto ya no lo cambia (sin regla en Proyecto). */
    public function testElFormularioDelProyectoYaNoLoCambia(): void
    {
        $proyecto = Proyecto::findOne($this->proyecto->id);
        $proyecto->load(['Proyecto' => ['lugarEntrega' => 'No debe quedar']]);
        $this->assertSame('Almacén test del proyecto', $proyecto->lugarEntrega);
    }

    public function testUnLugarDemasiadoLargoNoGuardaNada(): void
    {
        $errores = $this->guardar([$this->fila(null, $this->item, str_repeat('x', 301))], false);
        $this->assertStringContainsString('lugar de entrega', implode(' ', $errores));
        $this->assertSame([], $this->items());
    }

    private function fila(?ProyectoItem $item, SubcatalogoItem $subcatalogo, string $lugar): array
    {
        return [
            'id' => $item === null ? '' : (string) $item->id,
            'idSubcatalogo' => (string) $subcatalogo->id,
            'cantidad' => '3',
            'observacion' => '',
            'idEspecificacion' => (string) $this->ficha->id,
            'lugarEntrega' => $lugar,
        ];
    }

    /**
     * @return string[] errores (vacio si se guardo)
     */
    private function guardar(array $filas, bool $debeGuardar = true): array
    {
        $errores = [];
        $enviados = [];
        $guardado = $this->lista->guardar(Proyecto::findOne($this->proyecto->id), $filas, $errores, $enviados, 'encargado');
        $this->assertSame($debeGuardar, $guardado, implode(' ', $errores));

        return $errores;
    }

    /** @return ProyectoItem[] */
    private function items(): array
    {
        /** @var ProyectoItem[] $items */
        $items = $this->proyecto->getItems()->orderBy(['orden' => SORT_ASC])->all();

        return $items;
    }
}
