<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Categoria;
use backend\models\ConfiguracionEspecificacion;
use backend\models\CorrelativoDocumento;
use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\Proceso;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use backend\services\EspecificacionEmisionService;
use backend\services\EspecificacionService;
use backend\services\ListaItemsService;
use backend\services\ProcesoService;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * Emisiones de especificaciones (RD-PI-ET): enlazar = emitir, correlativo
 * sin repetir, anular, reemitir, avisos y registro.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar (tambien
 * el correlativo).
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

        $this->proyecto = new Proyecto(['nombre' => 'Proyecto test NET', 'unidadDependencia' => 'Carrera de Ingeniería de Sistemas', 'unidadSolicitante' => 'Facultad de Tecnología', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $this->proyecto->save(false);
        $this->proyecto->refresh();

        // Correlativo conocido para el test, por encima de cualquier emision de la base local.
        // Con el año de hoy: si no, el primer numero que se tome volveria a 1.
        CorrelativoDocumento::updateAll(
            ['siguiente' => (int) EspecificacionEmision::find()->max('numeroNet') + 1000, 'anio' => (int) date('y')],
            ['tipo' => CorrelativoDocumento::TIPO_ESPECIFICACION]
        );
    }

    public function testEnlazarEnLaListaEmiteConElSiguienteCodigoEt(): void
    {
        $siguiente = $this->siguienteEt();
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']], ['garantia' => '']);
        ConfiguracionEspecificacion::updateAll(['lugarEntregaPorDefecto' => 'Almacenes test USFX'], ['id' => ConfiguracionEspecificacion::obtener()->id]);

        $this->guardarLista([$this->fila(null, 5, $ficha)], 'encargado');

        $item = $this->unicoItem();
        $emision = $item->emisionVigente;
        $this->assertNotNull($emision);
        $this->assertSame(EspecificacionEmision::SERIE_ET, $emision->serie);
        $this->assertSame($siguiente, (int) $emision->numeroNet);
        $this->assertSame((int) date('y'), (int) $emision->anio);
        $this->assertSame($siguiente + 1, $this->siguienteEt());
        // Copias congeladas. El titulo es el nombre DTIC del item de la
        // lista, no el de la ficha.
        $this->assertSame($this->itemSubcatalogo->descripcion, $emision->titulo);
        $this->assertSame(5.0, (float) $emision->cantidad);
        // Dependencia = unidad / dependencia (la beneficiaria, no la solicitante); lugar = el de la configuracion.
        $this->assertSame('Carrera de Ingeniería de Sistemas', $emision->dependencia);
        $this->assertSame('Almacenes test USFX', $emision->lugarEntrega);
        $this->assertSame('encargado', $emision->elaboradoPor);
        // Sin garantia propia en la ficha: se congela la de la configuracion.
        $this->assertSame(ConfiguracionEspecificacion::obtener()->garantiaPorDefecto, $emision->garantia);
        $this->assertSame(sprintf('RD-PI-ET-%03d/%s', $siguiente, date('y')), $item->referenciaEspecificacion());
    }

    public function testElLugarDeEntregaEsElDelProyectoYSinoElDeLaConfiguracion(): void
    {
        $ficha = $this->ficha('Cable test lugar', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        ConfiguracionEspecificacion::updateAll(['lugarEntregaPorDefecto' => 'Almacenes test USFX'], ['id' => ConfiguracionEspecificacion::obtener()->id]);
        Proyecto::updateAll(['lugarEntrega' => 'Administración de la Facultad test de Agrarias'], ['id' => $this->proyecto->id]);

        $this->guardarLista([$this->fila(null, 2, $ficha)], 'encargado');
        $this->assertSame('Administración de la Facultad test de Agrarias', $this->unicoItem()->emisionVigente->lugarEntrega);

        // Al reemitir, lo que se escribe manda; vacio = el del proyecto (aunque haya cambiado).
        $escrito = $this->emisiones->reemitir($this->unicoItem(), ['motivo' => 'otro lugar', 'lugarEntrega' => 'Bloque test B'], 'encargado');
        $this->assertSame('Bloque test B', $escrito['emision']->lugarEntrega);

        Proyecto::updateAll(['lugarEntrega' => null], ['id' => $this->proyecto->id]);
        $vacio = $this->emisiones->reemitir($this->unicoItem(), ['motivo' => 'sin lugar', 'lugarEntrega' => ' '], 'encargado');
        $this->assertSame('Almacenes test USFX', $vacio['emision']->lugarEntrega);
    }

    public function testElNumeroEtPuedeCoincidirConElDeUnNetViejo(): void
    {
        // La serie ET empezo en 1 despues de los NET: el mismo numero en las
        // dos series son dos documentos distintos (indice unico por serie).
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $nueva = $this->unicoItem()->emisionVigente;

        $vieja = new EspecificacionEmision($nueva->getAttributes(null, ['id', 'serie', 'idProyectoItem', 'estado', 'fechaRegistro']));
        $vieja->serie = EspecificacionEmision::SERIE_NET;
        $vieja->estado = EspecificacionEmision::ESTADO_ANULADA;
        $this->assertTrue($vieja->save(false));

        $this->assertSame(sprintf('NET:%03d/%s', $nueva->numeroNet, date('y')), $vieja->codigo());
        $this->assertSame(sprintf('RD-PI-ET-%03d/%s', $nueva->numeroNet, date('y')), $nueva->codigo());
    }

    public function testFichaSinContenidoNoSeEnlaza(): void
    {
        $ficha = new Especificacion(['titulo' => 'Ficha vacía test', 'activo' => true]);
        $ficha->save(false);

        $errores = $this->guardarLista([$this->fila(null, 1, $ficha)], null, false);

        $this->assertStringContainsString('no tiene contenido técnico', implode(' ', $errores));
    }

    public function testRecordarComoSugeridaPoneElItemDeReferenciaAlGuardar(): void
    {
        $recordada = $this->ficha('Cable test recordar', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $sinMarcar = $this->ficha('Cable test sin marcar', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);

        $this->guardarLista([
            $this->fila(null, 1, $recordada) + ['recordarReferencia' => '1'],
            $this->fila(null, 1, $sinMarcar),
        ]);

        $recordada->refresh();
        $sinMarcar->refresh();
        $this->assertSame((int) $this->itemSubcatalogo->id, (int) $recordada->idSubcatalogo);
        $this->assertNull($sinMarcar->idSubcatalogo);
    }

    public function testVolverAGuardarSinCambiosNoEmiteOtra(): void
    {
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $item = $this->unicoItem();
        $siguiente = $this->siguienteEt();

        $this->guardarLista([$this->fila($item, 8, $ficha)]);

        $this->assertSame($siguiente, $this->siguienteEt());
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
        $this->assertSame((int) $fichaB->id, (int) $nueva->version->idEspecificacion);
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
        $this->assertSame($this->itemSubcatalogo->descripcion, $emision->titulo);
    }

    public function testReemitirConservaElCodigoYSubeLaVersion(): void
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
        // Lineamiento RD-GD-LI-001 (4.2): el mismo documento, version 02; no
        // se gasta un numero del correlativo.
        $siguiente = $this->siguienteEt();
        $this->assertSame($anterior->codigo(), $nueva->codigo());
        $this->assertSame(1, (int) $anterior->versionDocumento);
        $this->assertSame(2, (int) $nueva->versionDocumento);
        $this->assertSame($anterior->codigo() . ' v02', $nueva->codigoConVersion());
        $this->assertSame($siguiente, $this->siguienteEt());
        $this->assertSame(7.0, (float) $nueva->cantidad);
        $this->assertSame('Suministros USFX', $nueva->lugarEntrega);
        $this->assertSame(15, (int) $nueva->tiempoEntregaDias);
        $anterior->refresh();
        $this->assertSame(EspecificacionEmision::ESTADO_REEMPLAZADA, $anterior->estado);
        $this->assertSame((int) $nueva->id, (int) $anterior->idReemplazadaPor);
        $this->assertSame('cambió la cantidad', $anterior->motivo);
        $this->assertSame([], $this->emisiones->estadoPorItem([$this->unicoItem()])[$item->id]['avisos']);

        // Una tercera vez: v03, y la v02 queda reemplazada por ella.
        $tercera = $this->emisiones->reemitir($this->unicoItem(), ['motivo' => 'venció'], 'encargado')['emision'];
        $this->assertSame($anterior->codigo(), $tercera->codigo());
        $this->assertSame(3, (int) $tercera->versionDocumento);
        $nueva->refresh();
        $this->assertSame((int) $tercera->id, (int) $nueva->idReemplazadaPor);
        $this->assertSame($siguiente, $this->siguienteEt());
    }

    public function testLlevaElProcesoDelProyectoYAlReemitirConservaProcesoYAnio(): void
    {
        $gr = Proceso::findOne(['codigo' => 'GR']);
        if ($gr === null) {
            $this->markTestSkipped('La base local no tiene el proceso GR.');
        }
        Proyecto::updateAll(['idProceso' => $gr->id], ['id' => $this->proyecto->id]);

        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $emision = $this->unicoItem()->emisionVigente;
        $this->assertSame('GR', $emision->proceso);
        $this->assertSame(sprintf('RD-GR-ET-%03d/%s', $emision->numeroNet, date('y')), $emision->codigo());

        // Emitida "el año pasado" y despues el proyecto paso a PI: la
        // reemision es el mismo documento, con su proceso y su año.
        EspecificacionEmision::updateAll(['anio' => (int) date('y') - 1], ['id' => $emision->id]);
        Proyecto::updateAll(['idProceso' => (new ProcesoService())->idPorDefecto()], ['id' => $this->proyecto->id]);
        $siguiente = $this->siguienteEt();

        $nueva = $this->emisiones->reemitir($this->unicoItem(), ['motivo' => 'venció'], 'encargado')['emision'];

        $this->assertSame(sprintf('RD-GR-ET-%03d/%02d v02', $emision->numeroNet, (int) date('y') - 1), $nueva->codigoConVersion());
        $this->assertSame($siguiente, $this->siguienteEt());
    }

    public function testCambiarLaFichaDespuesDeReemitirDaOtroCodigo(): void
    {
        // Otra ficha es otro documento: codigo nuevo y v01.
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $otra = $this->ficha('Cable test otra', [['nombre' => 'Calibre', 'valor' => '24 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $reemitida = $this->emisiones->reemitir($this->unicoItem(), ['motivo' => 'prueba'], 'encargado')['emision'];

        $this->guardarLista([$this->fila($this->unicoItem(), 5, $otra)]);

        $nueva = $this->unicoItem()->emisionVigente;
        $this->assertNotSame($reemitida->codigo(), $nueva->codigo());
        $this->assertSame(1, (int) $nueva->versionDocumento);
    }

    public function testElFormularioReemitidoImprimeLaVersionDelDocumento(): void
    {
        // La cabecera y el control de elaboracion llevan la version del
        // documento, no la de la ficha (que aqui es v2).
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        (new EspecificacionService())->guardarVersion($ficha, [], [['nombre' => 'Calibre', 'valor' => '24 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $item = $this->unicoItem();
        $this->emisiones->reemitir($item, ['motivo' => 'prueba'], 'encargado');
        $this->emisiones->reemitir($this->unicoItem(), ['motivo' => 'prueba'], 'encargado');
        $emision = $this->emisiones->obtener((int) $this->unicoItem()->emisionVigente->id);

        $html = (new \yii\web\View())->renderFile(
            \Yii::getAlias('@backend/views/especificacion/formulario-pdf.php'),
            ['emision' => $emision, 'proceso' => \backend\models\Proceso::nombreDe($emision->codigoProceso())]
        );

        $this->assertStringContainsString('<strong>Versión:</strong> 03', $html);
        $this->assertStringContainsString('Versión: 03', $html);
        // "RD-PI-ET-015/26" -> "RD-PI-ET-015_26_v03.pdf": la barra no va en un nombre de archivo.
        $this->assertSame(str_replace('/', '_', $emision->codigo()) . '_v03.pdf', $this->emisiones->generarPdf($emision, $html)['nombreArchivo']);
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

    public function testFormularioLlevaElControlDeElaboracionCopiadoAlEmitir(): void
    {
        ConfiguracionEspecificacion::updateAll([
            'firma1Prefijo' => null, 'firma1Nombre' => 'Ing. Quien Elabora', 'firma1Cargo' => 'PROYECTOS DE REDES',
            'firma2Prefijo' => 'V°B°', 'firma2Nombre' => 'Ing. Quien Aprueba', 'firma2Cargo' => 'RESPONSABLE DE REDES',
        ]);
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 2, $ficha)]);
        $emision = $this->emisiones->obtener((int) $this->unicoItem()->emisionVigente->id);

        // Cambiar la configuracion despues de emitir no cambia el formulario.
        ConfiguracionEspecificacion::updateAll(['firma1Nombre' => 'Ing. Otra Persona']);

        $html = (new \yii\web\View())->renderFile(
            \Yii::getAlias('@backend/views/especificacion/formulario-pdf.php'),
            ['emision' => $emision, 'proceso' => \backend\models\Proceso::nombreDe($emision->codigoProceso())]
        );

        $this->assertStringContainsString('Versión: 01', $html);
        $this->assertStringContainsString('Ing. Quien Elabora', $html);
        $this->assertStringContainsString('V°B° Ing. Quien Aprueba', $html);
        $this->assertStringContainsString('RESPONSABLE DE REDES', $html);
        $this->assertStringContainsString('Fecha de aprobación: ' .(new DateTimeImmutable($emision->fechaEmision))->format('d/m/Y'), $html);
        $this->assertStringNotContainsString('Ing. Otra Persona', $html);
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
            ['emision' => $emision, 'proceso' => \backend\models\Proceso::nombreDe($emision->codigoProceso())]
        );

        $this->assertStringContainsString($emision->codigo(), $html);
        $this->assertStringContainsString('<strong>Calibre del conductor:</strong> 23 AWG', $html);
        $this->assertStringContainsString('Cumple ANSI/TIA', $html);
        $this->assertStringContainsString('Se sugiere: Furukawa o similar/superior.', $html);
        $this->assertStringContainsString('Dos años', $html);
        $this->assertStringContainsString('Carrera de Ingeniería de Sistemas', $html);
        $this->assertStringNotContainsString('24 AWG', $html);
        $this->assertStringNotContainsString('Tres años', $html);
        $this->assertStringNotContainsString('NOTA NUEVA', $html);

        $pdf = $this->emisiones->generarPdf($emision, $html);
        $this->assertStringStartsWith('%PDF', $pdf['contenido']);
        $this->assertSame(str_replace([':', '/'], '_', $emision->codigo()) . '_v01.pdf', $pdf['nombreArchivo']);
    }

    public function testRegistroFiltraPorEstadoYBuscaPorCodigo(): void
    {
        $ficha = $this->ficha('Cable registro test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $item = $this->unicoItem();
        $primera = $item->emisionVigente;
        $segunda = $this->emisiones->reemitir($item, ['motivo' => 'prueba'], 'encargado')['emision'];
        // Las dos versiones tienen el mismo numero: se distinguen por id.
        $numero = (int) $primera->numeroNet;
        $idPrimera = (int) $primera->id;
        $idSegunda = (int) $segunda->id;
        $deEsteProyecto = fn (array $filtros) => array_values(array_filter(
            array_map(static fn (EspecificacionEmision $e) => (int) $e->id, $this->emisiones->registro($filtros, false)->getModels()),
            static fn (int $id) => in_array($id, [$idPrimera, $idSegunda], true)
        ));

        $this->assertSame([$idSegunda, $idPrimera], $deEsteProyecto([]));
        $this->assertSame([$idSegunda], $deEsteProyecto(['estado' => 'vigente']));
        $this->assertSame([$idPrimera], $deEsteProyecto(['estado' => 'reemplazada']));
        $this->assertSame([], $deEsteProyecto(['estado' => 'vencida']));
        // El codigo trae todas sus versiones; con la version, solo esa.
        $this->assertSame([$idSegunda, $idPrimera], $deEsteProyecto(['buscar' => (string) $numero]));
        // Sin el año: el de todos los años; con otro año, no es esta.
        $this->assertSame([$idSegunda, $idPrimera], $deEsteProyecto(['buscar' => sprintf('RD-PI-ET-%03d', $numero)]));
        $this->assertSame([$idSegunda, $idPrimera], $deEsteProyecto(['buscar' => sprintf('RD-PI-ET-%03d/%s', $numero, date('y'))]));
        $this->assertSame([$idSegunda, $idPrimera], $deEsteProyecto(['buscar' => sprintf('%d/%s', $numero, date('y'))]));
        $this->assertSame([], $deEsteProyecto(['buscar' => sprintf('RD-PI-ET-%03d/%02d', $numero, (int) date('y') - 1)]));
        $this->assertSame([$idSegunda, $idPrimera], $deEsteProyecto(['buscar' => sprintf('rd-pi-et-%03d', $numero)]));
        $this->assertSame([$idPrimera], $deEsteProyecto(['buscar' => sprintf('RD-PI-ET-%03d/%s v01', $numero, date('y'))]));
        $this->assertSame([$idSegunda], $deEsteProyecto(['buscar' => sprintf('rd-pi-et-%03d V02', $numero)]));
        // Es de la serie ET: buscarla como NET no la encuentra.
        $this->assertSame([], $deEsteProyecto(['buscar' => sprintf('NET:%03d/%s', $numero, date('y'))]));
        // El titulo de la emision es el nombre DTIC del item.
        $this->assertSame([$idSegunda, $idPrimera], $deEsteProyecto(['buscar' => $this->itemSubcatalogo->descripcion, 'dependencia' => 'Carrera de Ingeniería de Sistemas']));

        // Pasada la vigencia, la vigente aparece como vencida.
        EspecificacionEmision::updateAll(['fechaEmision' => '2020-01-01'], ['id' => $segunda->id]);
        $this->assertSame([$idSegunda], $deEsteProyecto(['estado' => 'vencida']));
        $this->assertSame([], $deEsteProyecto(['estado' => 'vigente']));

        $csv = $this->emisiones->exportarCsv(['buscar' => sprintf('RD-PI-ET-%03d v01', $numero)]);
        $this->assertStringStartsWith("\xEF\xBB\xBF" . 'Código;Versión;"Código anterior";"Fecha de emisión"', $csv);
        $this->assertStringContainsString(sprintf('RD-PI-ET-%03d/%s;01;', $numero, date('y')), $csv);
        $this->assertStringContainsString(sprintf('Reemplazada;prueba;"RD-PI-ET-%03d/%s v02"', $numero, date('y')), $csv);
    }

    public function testUnaPasadaDeNetAEtSeBuscaPorSuCodigoAnterior(): void
    {
        $ficha = $this->ficha('Cable test', [['nombre' => 'Calibre', 'valor' => '23 AWG']]);
        $this->guardarLista([$this->fila(null, 5, $ficha)]);
        $emision = $this->unicoItem()->emisionVigente;
        // Como la deja la migracion m261022_144239 (un NET que no exista en la base local).
        EspecificacionEmision::updateAll(['codigoAnterior' => 'NET:7777/26'], ['id' => $emision->id]);
        $ids = fn (string $buscar) => array_map(static fn (EspecificacionEmision $e) => (int) $e->id, $this->emisiones->registro(['buscar' => $buscar], false)->getModels());

        $this->assertSame([(int) $emision->id], $ids('NET:7777/26'));
        $this->assertSame([(int) $emision->id], $ids('NET:7777'));
        $this->assertSame([(int) $emision->id], $ids('7777'));
        $this->assertSame([], $ids('NET:7777/25'));
        $this->assertStringContainsString('NET:7777/26', $this->emisiones->exportarCsv(['buscar' => 'NET:7777/26']));
    }

    public function testCodigoProcesoUsaPiSiLaEmisionNoTieneProceso(): void
    {
        $this->assertSame('GD', (new EspecificacionEmision(['proceso' => 'GD']))->codigoProceso());
        $this->assertSame(CorrelativoDocumento::PROCESO, (new EspecificacionEmision(['proceso' => null]))->codigoProceso());
        $this->assertSame(CorrelativoDocumento::PROCESO, (new EspecificacionEmision(['proceso' => '']))->codigoProceso());
    }

    public function testLasVigentesDelProyectoSalenEnElOrdenDeLaLista(): void
    {
        // Dos items de categorias distintas, cargados al reves del orden de las categorias.
        $porCategoria = [];
        foreach (SubcatalogoItem::find()->alias('s')->innerJoinWith('categoria c')->andWhere(['s.activo' => true])->orderBy(['c.orden' => SORT_DESC])->all() as $item) {
            $porCategoria[(int) $item->idCategoria] ??= $item;
        }
        if (count($porCategoria) < 2) {
            $this->markTestSkipped('La base local no tiene ítems activos de 2 categorías.');
        }
        [$deCategoriaPosterior, $deCategoriaAnterior] = array_slice(array_values($porCategoria), 0, 2);

        $fila = fn (SubcatalogoItem $item, Especificacion $ficha): array => [
            'id' => '', 'idSubcatalogo' => (string) $item->id, 'cantidad' => '1', 'observacion' => '', 'idEspecificacion' => (string) $ficha->id,
        ];
        $this->guardarLista([
            $fila($deCategoriaPosterior, $this->ficha('Ficha test posterior', [['nombre' => 'A', 'valor' => '1']])),
            $fila($deCategoriaAnterior, $this->ficha('Ficha test anterior', [['nombre' => 'B', 'valor' => '2']])),
        ]);

        $vigentes = $this->emisiones->vigentesDelProyecto(Proyecto::findOne($this->proyecto->id));

        $this->assertSame(
            [$deCategoriaAnterior->descripcion, $deCategoriaPosterior->descripcion],
            array_map(static fn (EspecificacionEmision $e) => $e->titulo, $vigentes)
        );
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

    private function siguienteEt(): int
    {
        return (int) CorrelativoDocumento::findOne(CorrelativoDocumento::TIPO_ESPECIFICACION)->siguiente;
    }
}
