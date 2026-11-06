<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\ConfiguracionPresupuesto;
use backend\models\CorrelativoDocumento;
use backend\models\Personal;
use backend\models\Proceso;
use backend\models\Proyecto;
use backend\models\SubcatalogoItem;
use backend\services\CodificacionService;
use backend\services\CondicionPreviaService;
use backend\services\ListaItemsService;
use backend\services\MaterialDticService;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use backend\services\RolProyectoService;
use Codeception\Test\Unit;
use Yii;

/**
 * Revision del Ing. de DTIC del 2026-11-05, punto 7: la lista de materiales
 * tiene codigo propio (RD-<proceso>-LM-NNN, del correlativo LM) y NO se
 * aprueba: su impreso lleva solo "ELABORADO POR" y la version.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class ListaCodigoTest extends Unit
{
    private ProyectoService $proyectos;
    private ListaItemsService $lista;
    private array $equipo;

    protected function _before(): void
    {
        $this->proyectos = new ProyectoService();
        $this->lista = new ListaItemsService();
        (new Personal(['nombre' => 'Ing. Test Lista']))->save(false);
        $this->equipo = [['nombre' => 'Ing. Test Lista', 'idRol' => (string) (new RolProyectoService())->idProyectista()]];
    }

    public function testAlCrearElProyectoSuListaRecibeElSiguienteCodigoLm(): void
    {
        $siguiente = (int) (new CodificacionService())->correlativos()[CorrelativoDocumento::TIPO_LISTA]->siguiente;

        $proyecto = $this->crear((new ProcesoService())->idPorDefecto());

        $this->assertSame(CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_LISTA, $siguiente), $proyecto->codigoLista);
        $this->assertSame($siguiente, (new CodificacionService())->ultimoUsado(CorrelativoDocumento::TIPO_LISTA));
        $this->assertSame($siguiente + 1, (int) (new CodificacionService())->correlativos()[CorrelativoDocumento::TIPO_LISTA]->siguiente);
    }

    public function testElCodigoLlevaElProcesoDelProyectoMientrasLaListaNoSeEnvie(): void
    {
        $gr = $this->proceso('ZR');
        $proyecto = $this->crear($gr->id);
        $numero = CorrelativoDocumento::partesDe(CorrelativoDocumento::TIPO_LISTA, $proyecto->codigoLista)['numero'];
        $this->assertSame(CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_LISTA, $numero, 'ZR'), $proyecto->codigoLista);

        // Se cambia el proceso antes de enviar la lista: mismo numero, otro proceso.
        $this->assertTrue($this->proyectos->actualizar($proyecto, ['Proyecto' => ['idProceso' => (string) (new ProcesoService())->idPorDefecto(), 'equipo' => $this->equipo, 'equipoResponsable' => '0']]), json_encode($proyecto->errors));
        $this->assertSame(CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_LISTA, $numero, 'PI'), Proyecto::findOne($proyecto->id)->codigoLista);

        // Ya enviada, conserva su codigo aunque cambie el proceso.
        $errores = [];
        $this->assertTrue($this->lista->guardar(Proyecto::findOne($proyecto->id), [$this->fila()], $errores), implode(' ', $errores));
        $this->assertTrue($this->lista->enviarACotizacion(Proyecto::findOne($proyecto->id)));
        $proyecto = Proyecto::findOne($proyecto->id);
        $this->assertTrue($this->proyectos->actualizar($proyecto, ['Proyecto' => ['idProceso' => (string) $gr->id, 'equipo' => $this->equipo, 'equipoResponsable' => '0']]), json_encode($proyecto->errors));
        $this->assertSame(CorrelativoDocumento::codigo(CorrelativoDocumento::TIPO_LISTA, $numero, 'PI'), Proyecto::findOne($proyecto->id)->codigoLista);

        // Y el proceso queda "en uso" por ese codigo.
        $this->assertNotNull((new ProcesoService())->eliminar(Proceso::findOne($gr->id)));
    }

    public function testElImpresoLlevaSuCodigoYSoloElaboradoPor(): void
    {
        $proyecto = $this->crear((new ProcesoService())->idPorDefecto());
        $errores = [];
        $this->assertTrue($this->lista->guardar($proyecto, [$this->fila()], $errores), implode(' ', $errores));

        foreach ([false, true] as $enviada) {
            if ($enviada) {
                $this->assertTrue($this->lista->enviarACotizacion(Proyecto::findOne($proyecto->id)));
            }
            $proyecto = Proyecto::findOne($proyecto->id);
            $html = Yii::$app->view->render('@backend/views/lista-items/pdf.php', [
                'proyecto' => $proyecto,
                'grupos' => $this->lista->agrupar($proyecto),
                'documento' => $this->lista->documento($proyecto),
                'configuracion' => ConfiguracionPresupuesto::obtener(),
                'materiales' => (new MaterialDticService())->agrupar($proyecto),
                'trabajos' => (new CondicionPreviaService())->agrupar($proyecto),
                'proceso' => 'Proceso test',
            ]);

            $this->assertStringContainsString((string) $proyecto->codigoLista, $html);
            $this->assertStringContainsString('ELABORADO POR', $html);
            $this->assertStringNotContainsString('APROBADO POR', $html);
            $this->assertStringNotContainsString('Fecha de aprobación', $html);
            $this->assertStringContainsString('Versión: 1.0', $html);
        }
    }

    private function crear(int $idProceso): Proyecto
    {
        $proyecto = new Proyecto();
        $this->assertTrue($this->proyectos->crear($proyecto, ['Proyecto' => [
            'nombre' => 'Proyecto test código de lista',
            'idProceso' => (string) $idProceso,
            'equipo' => $this->equipo,
            'equipoResponsable' => '0',
            'unidadDependencia' => 'Carrera test',
            'unidadSolicitante' => 'Facultad test',
        ]]), json_encode($proyecto->errors));

        return Proyecto::findOne($proyecto->id);
    }

    private function proceso(string $codigo): Proceso
    {
        $proceso = new Proceso(['codigo' => $codigo, 'nombre' => 'Proceso test ' . $codigo, 'activo' => true]);
        $this->assertTrue($proceso->save(), json_encode($proceso->errors));

        return $proceso;
    }

    private function fila(): array
    {
        $item = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        if ($item === null) {
            $this->markTestSkipped('La base local no tiene ítems activos en el subcatálogo.');
        }

        return ['id' => '', 'idSubcatalogo' => (string) $item->id, 'cantidad' => '2', 'observacion' => '', 'idEspecificacion' => ''];
    }
}
