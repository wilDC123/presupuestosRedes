<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Proyecto;
use backend\models\ProyectoCondicionPrevia;
use backend\models\ProyectoMaterialDtic;
use backend\models\SubcatalogoItem;
use backend\models\TipoCondicionPrevia;
use backend\services\ListaItemsService;
use backend\services\TipoCondicionPreviaService;
use Codeception\Test\Unit;
use Yii;

/**
 * Secciones sin precio debajo de la lista (revision del 2026-10-29, paso 8a;
 * antes en el presupuesto): "Otros materiales y equipamiento que proveerá la
 * D.T.I.C." y los trabajos complementarios (condiciones previas). Se guardan
 * con la lista; una fila en blanco se ignora; null = no se tocan, '' = se
 * borran.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class ListaMaterialesTest extends Unit
{
    private ListaItemsService $lista;
    private SubcatalogoItem $itemSubcatalogo;

    protected function _before(): void
    {
        $item = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        if ($item === null) {
            $this->markTestSkipped('La base local no tiene ítems activos en el subcatálogo.');
        }
        $this->itemSubcatalogo = $item;
        $this->lista = new ListaItemsService();
    }

    public function testLosMaterialesDeLaDticSeGuardanConLaLista(): void
    {
        $proyecto = $this->proyecto();
        $errores = [];
        $enviados = [];

        $this->assertTrue($this->lista->guardar($proyecto, [$this->fila()], $errores, $enviados, null, null, [
            // Elegido del subcatalogo, sin nombre ni unidad: se toman del item.
            ['grupo' => 'Cableado y materiales de red', 'idSubcatalogo' => (string) $this->itemSubcatalogo->id, 'descripcion' => '', 'cantidad' => '20', 'unidadMedida' => ''],
            // Escrito a mano.
            ['grupo' => 'Equipos', 'idSubcatalogo' => '', 'descripcion' => 'Rack 42U usado', 'cantidad' => '1', 'unidadMedida' => 'pza'],
            // Solo con el subtitulo: es la fila vacia del formulario.
            ['grupo' => 'Equipos', 'idSubcatalogo' => '', 'descripcion' => '', 'cantidad' => '', 'unidadMedida' => ''],
        ]), implode(' ', $errores));

        $materiales = Proyecto::findOne($proyecto->id)->materialesDtic;
        $this->assertCount(2, $materiales);
        $this->assertSame($this->itemSubcatalogo->descripcion, $materiales[0]->descripcion);
        $this->assertSame($this->itemSubcatalogo->abreviaturaUnidad(), $materiales[0]->unidadMedida);
        $this->assertSame('Rack 42U usado', $materiales[1]->descripcion);
        $this->assertNull($materiales[1]->idSubcatalogo);

        // Una fila sin cantidad no deja guardar nada.
        $this->assertFalse($this->lista->guardar(Proyecto::findOne($proyecto->id), [$this->fila(Proyecto::findOne($proyecto->id)->items[0]->id)], $errores, $enviados, null, null, [
            ['grupo' => 'Equipos', 'descripcion' => 'Patch cord', 'cantidad' => ''],
        ]));
        $this->assertStringContainsString('Otros materiales de la DTIC, fila 1', implode(' ', $errores));
        $this->assertCount(2, Proyecto::findOne($proyecto->id)->materialesDtic);

        // '' (sin filas) borra todos.
        $this->assertTrue($this->lista->guardar(Proyecto::findOne($proyecto->id), [$this->fila(Proyecto::findOne($proyecto->id)->items[0]->id)], $errores, $enviados, null, null, ''));
        $this->assertSame([], Proyecto::findOne($proyecto->id)->materialesDtic);
    }

    public function testElSubtituloEsLaCategoriaDelItemOOtros(): void
    {
        $proyecto = $this->proyecto();
        $errores = [];
        $enviados = [];

        $this->assertTrue($this->lista->guardar($proyecto, [$this->fila()], $errores, $enviados, null, null, [
            // Del subcatalogo sin subtitulo: el nombre de su categoria.
            ['grupo' => '', 'idSubcatalogo' => (string) $this->itemSubcatalogo->id, 'descripcion' => '', 'cantidad' => '2', 'unidadMedida' => ''],
            // A mano sin subtitulo: "Otros".
            ['grupo' => '', 'idSubcatalogo' => '', 'descripcion' => 'Rack 42U usado', 'cantidad' => '1', 'unidadMedida' => ''],
        ]), implode(' ', $errores));

        $materiales = Proyecto::findOne($proyecto->id)->materialesDtic;
        $this->assertSame($this->itemSubcatalogo->categoria->nombre, $materiales[0]->grupo);
        $this->assertSame(ProyectoMaterialDtic::GRUPO_OTROS, $materiales[1]->grupo);
    }

    public function testLosTrabajosComplementariosSeGuardanConLaLista(): void
    {
        $proyecto = $this->proyecto();
        $errores = [];
        $enviados = [];
        $tipo = TipoCondicionPrevia::findOne(['nombre' => 'Apertura de ductos']);
        $this->assertNotNull($tipo);

        $this->assertTrue($this->lista->guardar($proyecto, [$this->fila()], $errores, $enviados, null, [
            ['idTipo' => (string) $tipo->id, 'descripcion' => ' Ducto entre bloque A y B ', 'unidadEncargada' => ''],
            // Solo con el tipo elegido: es la fila vacia del formulario.
            ['idTipo' => (string) $tipo->id, 'descripcion' => '', 'unidadEncargada' => ''],
        ]), implode(' ', $errores));

        $condiciones = Proyecto::findOne($proyecto->id)->condicionesPrevias;
        $this->assertCount(1, $condiciones);
        $this->assertSame('Ducto entre bloque A y B', $condiciones[0]->descripcion);
        $this->assertNull($condiciones[0]->unidadEncargada);

        // Sin la seccion en el envio (null) no se toca; con algo escrito, el tipo es obligatorio.
        $filas = [$this->fila(Proyecto::findOne($proyecto->id)->items[0]->id)];
        $this->assertTrue($this->lista->guardar(Proyecto::findOne($proyecto->id), $filas, $errores));
        $this->assertCount(1, Proyecto::findOne($proyecto->id)->condicionesPrevias);
        $this->assertFalse($this->lista->guardar(Proyecto::findOne($proyecto->id), $filas, $errores, $enviados, null, [
            ['idTipo' => '999999', 'descripcion' => 'Algo'],
        ]));
        $this->assertStringContainsString('elija un tipo', implode(' ', $errores));
    }

    public function testUnTipoEnUsoNoSeElimina(): void
    {
        $proyecto = $this->proyecto();
        $tipo = new TipoCondicionPrevia(['nombre' => 'Tipo test']);
        $this->assertTrue($tipo->save());
        (new ProyectoCondicionPrevia(['idProyecto' => $proyecto->id, 'idTipo' => $tipo->id, 'descripcion' => 'x']))->save(false);

        $tipos = new TipoCondicionPreviaService();
        $this->assertNotNull($tipos->eliminar($tipo));
        $this->assertTrue($tipos->actualizar($tipo, ['TipoCondicionPrevia' => ['activo' => '0']]));
        $this->assertArrayNotHasKey($tipo->id, $tipos->opciones());
        $this->assertArrayHasKey($tipo->id, $tipos->opciones([$tipo->id]));
    }

    public function testElImpresoDeLaListaLlevaLasSeccionesSoloSiTienenFilas(): void
    {
        $proyecto = $this->proyecto();
        $errores = [];
        $enviados = [];
        $this->assertTrue($this->lista->guardar($proyecto, [$this->fila()], $errores));
        $html = fn () => Yii::$app->view->render('@backend/views/lista-items/pdf.php', [
            'proyecto' => Proyecto::findOne($proyecto->id),
            'grupos' => $this->lista->agrupar(Proyecto::findOne($proyecto->id)),
        ]);

        $this->assertStringNotContainsString(ProyectoMaterialDtic::TITULO, $html());
        $this->assertStringNotContainsString(ProyectoCondicionPrevia::TITULO, $html());

        $tipo = TipoCondicionPrevia::findOne(['nombre' => 'Instalaciones eléctricas']);
        $this->assertTrue($this->lista->guardar(Proyecto::findOne($proyecto->id), [$this->fila(Proyecto::findOne($proyecto->id)->items[0]->id)], $errores, $enviados, null,
            [['idTipo' => (string) $tipo->id, 'descripcion' => 'Tomacorriente junto al rack', 'unidadEncargada' => 'Mantenimiento eléctrico']],
            [['grupo' => 'Equipos', 'descripcion' => 'Rack 42U usado', 'cantidad' => '1']]
        ), implode(' ', $errores));

        $impreso = $html();
        foreach ([ProyectoMaterialDtic::TITULO, 'Rack 42U usado', ProyectoCondicionPrevia::TITULO, 'Instalaciones eléctricas', 'Tomacorriente junto al rack'] as $esperado) {
            $this->assertStringContainsString($esperado, $impreso);
        }
    }

    public function testElPresupuestoYaNoTieneSeccionesSinPrecio(): void
    {
        $this->assertFalse(class_exists('backend\models\PresupuestoComplemento'));
        $this->assertSame('Otros materiales y equipamiento que proveerá la D.T.I.C.', ProyectoMaterialDtic::TITULO);
    }

    private function proyecto(): Proyecto
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test secciones sin precio', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);

        return $proyecto;
    }

    /**
     * Una fila de la lista como la envia el formulario.
     */
    private function fila(?int $idItem = null): array
    {
        return [
            'id' => $idItem === null ? '' : (string) $idItem,
            'idSubcatalogo' => (string) $this->itemSubcatalogo->id,
            'cantidad' => '1',
            'observacion' => '',
            'idEspecificacion' => '',
        ];
    }
}
