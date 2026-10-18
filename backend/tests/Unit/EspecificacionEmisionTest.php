<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Categoria;
use backend\models\ConfiguracionEspecificacion;
use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use backend\services\EspecificacionEmisionService;
use backend\services\EspecificacionService;
use backend\services\ListaItemsService;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * Emisiones NET: enlazar = emitir, correlativo sin repetir, anular,
 * reemitir, avisos y configuracion.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar (tambien
 * el contador de la configuracion).
 */
class EspecificacionEmisionTest extends Unit
{
    private EspecificacionEmisionService $emisiones;
    private ListaItemsService $lista;
    private Proyecto $proyecto;
    private SubcatalogoItem $itemSubcatalogo;

    protected function _before(): void
    {
        $this->emisiones = new EspecificacionEmisionService();
        $this->lista = new ListaItemsService();

        $itemSubcatalogo = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        if ($itemSubcatalogo === null || Categoria::find()->count() == 0) {
            $this->markTestSkipped('La base local no tiene ítems activos en el subcatálogo.');
        }
        $this->itemSubcatalogo = $itemSubcatalogo;

        $this->proyecto = new Proyecto(['nombre' => 'Proyecto test NET', 'facultad' => 'Facultad de Tecnología', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $this->proyecto->save(false);
        $this->proyecto->refresh();

        // Correlativo conocido para el test, por encima de cualquier NET de la base local.
        ConfiguracionEspecificacion::updateAll(['siguienteNet' => (int) EspecificacionEmision::find()->max('numeroNet') + 1000]);
    }

    public function testEnlazarEnLaListaEmiteConElSiguienteNet(): void
    {
        $siguiente = $this->siguienteNet();
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']], ['garantia' => '']);

        $this->guardarLista([$this->fila(null, 5, $ficha)], 'encargado');

        $item = $this->unicoItem();
        $emision = $item->emisionVigente;
        $this->assertNotNull($emision);
        $this->assertSame($siguiente, (int) $emision->numeroNet);
        $this->assertSame((int) date('y'), (int) $emision->anio);
        $this->assertSame($siguiente + 1, $this->siguienteNet());
        // Copias congeladas.
        $this->assertSame('Cable test', $emision->titulo);
        $this->assertSame(5.0, (float) $emision->cantidad);
        $this->assertSame('Facultad de Tecnología', $emision->dependencia);
        $this->assertSame('Facultad de Tecnología', $emision->lugarEntrega);
        $this->assertSame('encargado', $emision->elaboradoPor);
        // Sin garantia propia en la ficha: se congela la de la configuracion.
        $this->assertSame(ConfiguracionEspecificacion::obtener()->garantiaPorDefecto, $emision->garantia);
        $this->assertSame(sprintf('NET:%03d/%s', $siguiente, date('y')), $item->referenciaEspecificacion());
    }

    public function testSinNetInicialNoSeEnlazaYNoSeGuardaNada(): void
    {
        ConfiguracionEspecificacion::updateAll(['siguienteNet' => null]);
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);

        $errores = $this->guardarLista([$this->fila(null, 5, $ficha)], 'encargado', false);

        $this->assertStringContainsString('código NET inicial', implode(' ', $errores));
        $this->assertSame(0, (int) $this->proyecto->getItems()->count());
    }

    public function testFichaSinContenidoNoSeEnlaza(): void
    {
        $ficha = new Especificacion(['titulo' => 'Ficha vacía test', 'activo' => true]);
        $ficha->save(false);

        $errores = $this->guardarLista([$this->fila(null, 1, $ficha)], null, false);

        $this->assertStringContainsString('no tiene contenido técnico', implode(' ', $errores));
    }

    public function testVolverAGuardarSinCambiosNoEmiteOtra(): void
    {
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $item = $this->unicoItem();
        $siguiente = $this->siguienteNet();

        $this->guardarLista([$this->fila($item, 8, $ficha)]);

        $this->assertSame($siguiente, $this->siguienteNet());
        $this->assertSame(1, (int) EspecificacionEmision::find()->andWhere(['idProyecto' => $this->proyecto->id])->count());
    }

    public function testCambiarLaFichaAnulaYEmiteOtra(): void
    {
        $fichaA = $this->ficha('Cable A test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $fichaB = $this->ficha('Cable B test', [['nombre' => 'Calibre', 'valor' => '24 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $fichaA)]);
        $item = $this->unicoItem();
        $primera = $item->emisionVigente;

        $this->guardarLista([$this->fila($item, 5, $fichaB)]);

        $primera->refresh();
        $this->assertSame(EspecificacionEmision::ESTADO_ANULADA, $primera->estado);
        $this->assertSame('Se cambió la especificación del ítem.', $primera->motivo);
        $nueva = $this->unicoItem()->emisionVigente;
        $this->assertSame('Cable B test', $nueva->titulo);
        $this->assertSame((int) $primera->numeroNet + 1, (int) $nueva->numeroNet);
    }

    public function testQuitarElItemAnulaPeroElDocumentoSeConserva(): void
    {
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $emision = $this->unicoItem()->emisionVigente;

        $this->guardarLista([]);

        $emision->refresh();
        $this->assertSame(EspecificacionEmision::ESTADO_ANULADA, $emision->estado);
        $this->assertNull($emision->idProyectoItem);
        $this->assertSame('Cable test', $emision->titulo);
    }

    public function testReemitirReemplazaConNetNuevoYLaCantidadDeLaLista(): void
    {
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $item = $this->unicoItem();
        $anterior = $item->emisionVigente;

        // Sin motivo no se reemite.
        $this->assertNotNull($this->emisiones->reemitir($item, ['motivo' => ' '], 'encargado')['error']);

        // La cantidad cambia en la lista: la emision avisa y al reemitir la toma.
        $this->guardarLista([$this->fila($item, 7, $ficha)]);
        $item = $this->unicoItem();
        $avisos = $this->emisiones->estadoPorItem([$item])[$item->id]['avisos'];
        $this->assertStringContainsString('ya no coincide', implode(' ', $avisos));

        $resultado = $this->emisiones->reemitir($item, ['motivo' => 'cambió la cantidad', 'lugarEntrega' => 'Suministros USFX', 'tiempoEntregaDias' => '15'], 'encargado');

        $this->assertNull($resultado['error']);
        $nueva = $resultado['emision'];
        $this->assertSame(7.0, (float) $nueva->cantidad);
        $this->assertSame('Suministros USFX', $nueva->lugarEntrega);
        $this->assertSame(15, (int) $nueva->tiempoEntregaDias);
        $anterior->refresh();
        $this->assertSame(EspecificacionEmision::ESTADO_REEMPLAZADA, $anterior->estado);
        $this->assertSame((int) $nueva->id, (int) $anterior->idReemplazadaPor);
        $this->assertSame('cambió la cantidad', $anterior->motivo);
        $this->assertSame([], $this->emisiones->estadoPorItem([$this->unicoItem()])[$item->id]['avisos']);
    }

    public function testAvisaVencimientoYVersionNueva(): void
    {
        $servicio = new EspecificacionService();
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $servicio->guardarVersion($ficha, [], [['nombre' => 'Calibre', 'valor' => '23 AWG'], ['nombre' => 'Cubierta', 'valor' => 'LSZH']]);
        $item = $this->unicoItem();

        $avisos = implode(' ', $this->emisiones->estadoPorItem([$item], new DateTimeImmutable('+40 days'))[$item->id]['avisos']);

        $this->assertStringContainsString('versión más nueva de la ficha (v2)', $avisos);
        $this->assertStringContainsString('venció', $avisos);
    }

    public function testFormularioPdfImprimeLasCopiasCongeladas(): void
    {
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre del conductor', 'valor' => '23 AWG'], ['valor' => 'Cumple ANSI/TIA']], ['modeloSugerido' => 'Furukawa', 'garantia' => 'Dos años']);
        $this->guardarLista([$this->fila(null, 2, $ficha)], 'encargado');
        $emision = $this->emisiones->obtener((int) $this->unicoItem()->emisionVigente->id);

        // Cambios DESPUES de emitir: no deben aparecer en el formulario.
        ConfiguracionEspecificacion::updateAll(['textoNotaFinal' => 'NOTA NUEVA']);
        (new EspecificacionService())->guardarVersion($ficha, ['garantia' => 'Tres años'], [['nombre' => 'Calibre del conductor', 'valor' => '24 AWG']]);

        $html = (new \yii\web\View())->renderFile(
            \Yii::getAlias('@backend/views/especificacion/formulario-pdf.php'),
            ['emision' => $emision, 'logo' => null]
        );

        $this->assertStringContainsString($emision->codigoNet(), $html);
        $this->assertStringContainsString('<strong>Calibre del conductor:</strong> 23 AWG', $html);
        $this->assertStringContainsString('Cumple ANSI/TIA', $html);
        $this->assertStringContainsString('Se sugiere: Furukawa o similar/superior.', $html);
        $this->assertStringContainsString('Dos años', $html);
        $this->assertStringContainsString('Facultad de Tecnología', $html);
        $this->assertStringNotContainsString('24 AWG', $html);
        $this->assertStringNotContainsString('Tres años', $html);
        $this->assertStringNotContainsString('NOTA NUEVA', $html);

        $pdf = $this->emisiones->generarPdf($emision, $html);
        $this->assertStringStartsWith('%PDF', $pdf['contenido']);
        $this->assertSame(str_replace([':', '/'], '_', $emision->codigoNet()) . '.pdf', $pdf['nombreArchivo']);
    }

    public function testElSiguienteNetNoPuedeBajarDelUltimoEmitido(): void
    {
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $ultimo = (int) $this->unicoItem()->emisionVigente->numeroNet;
        $configuracion = ConfiguracionEspecificacion::obtener();

        $this->assertFalse($this->emisiones->guardarConfiguracion($configuracion, ['ConfiguracionEspecificacion' => ['siguienteNet' => (string) $ultimo]]));
        $this->assertTrue($configuracion->hasErrors('siguienteNet'));

        $configuracion = ConfiguracionEspecificacion::obtener();
        $this->assertFalse($this->emisiones->guardarConfiguracion($configuracion, ['ConfiguracionEspecificacion' => ['siguienteNet' => '']]));

        $configuracion = ConfiguracionEspecificacion::obtener();
        $this->assertTrue($this->emisiones->guardarConfiguracion($configuracion, ['ConfiguracionEspecificacion' => ['siguienteNet' => (string) ($ultimo + 10)]]));
        $this->assertSame($ultimo + 10, $this->siguienteNet());
    }

    /**
     * @param array<int, array{nombre: string, valor: string}> $filas
     */
    private function ficha(string $titulo, array $filas, array $version = []): Especificacion
    {
        $ficha = new Especificacion(['titulo' => $titulo, 'activo' => true]);
        $ficha->save(false);
        (new EspecificacionService())->guardarVersion($ficha, $version, $filas);

        return $ficha;
    }

    /**
     * Una fila del formulario de la lista.
     */
    private function fila(?ProyectoItem $item, float $cantidad, Especificacion $ficha): array
    {
        return [
            'id' => $item?->id ?? '',
            'idSubcatalogo' => (string) $this->itemSubcatalogo->id,
            'cantidad' => (string) $cantidad,
            'observacion' => '',
            'idEspecificacion' => (string) $ficha->id,
        ];
    }

    /**
     * @return string[] errores (vacio si se guardo)
     */
    private function guardarLista(array $filas, ?string $usuario = 'encargado', bool $debeGuardar = true): array
    {
        $errores = [];
        $enviados = [];
        $guardado =$this->lista->guardar($this->proyecto, $filas, $errores, $enviados, $usuario);
        $this->assertSame($debeGuardar, $guardado, implode(' ', $errores));

        return $errores;
    }

    private function unicoItem(): ProyectoItem
    {
        $items = $this->proyecto->getItems()->all();
        $this->assertCount(1, $items);

        return $items[0];
    }

    private function siguienteNet(): int
    {
        return (int) ConfiguracionEspecificacion::obtener()->siguienteNet;
    }
}
