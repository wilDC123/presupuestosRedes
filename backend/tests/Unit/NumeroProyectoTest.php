<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\CorrelativoDocumento;
use backend\models\Personal;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\CodificacionService;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use backend\services\RolProyectoService;
use Codeception\Test\Unit;

/**
 * Numero de proyecto (revision del Ing. de DTIC del 2026-11-05, puntos 1 y 3):
 * correlativo automatico que comparten TODAS las fases del proyecto; se
 * escribe "001". El codigo RD-PI-PT-NNN queda para la ficha tecnica.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class NumeroProyectoTest extends Unit
{
    private ProyectoService $proyectos;
    private array $equipo;

    protected function _before(): void
    {
        $this->proyectos = new ProyectoService();
        (new Personal(['nombre' => 'Ing. Test Número']))->save(false);
        $this->equipo = [['nombre' => 'Ing. Test Número', 'idRol' => (string) (new RolProyectoService())->idProyectista()]];
    }

    public function testUnProyectoNuevoRecibeElSiguienteNumero(): void
    {
        $siguiente = (int) (new CodificacionService())->correlativos()[CorrelativoDocumento::TIPO_NUMERO_PROYECTO]->siguiente;

        $primero = $this->crear('Proyecto test número 1');
        $segundo = $this->crear('Proyecto test número 2');

        $this->assertSame($siguiente, (int) $primero->numeroProyecto);
        $this->assertSame($siguiente + 1, (int) $segundo->numeroProyecto);
        $this->assertSame(sprintf('%03d', $siguiente), $primero->numeroTexto());
        $this->assertSame(sprintf('%03d — Proyecto test número 1', $siguiente), $primero->etiqueta());
        // Fase unica: sin "Fase".
        $this->assertFalse($primero->tieneVariasFases());
        $this->assertSame('Proyecto ' . sprintf('%03d', $siguiente), $primero->identificador());
        $this->assertSame($siguiente + 1, (new CodificacionService())->ultimoUsado(CorrelativoDocumento::TIPO_NUMERO_PROYECTO));
    }

    public function testLaFaseSiguienteLlevaElMismoNumero(): void
    {
        $fase1 = $this->crear('Proyecto test fases');
        // Solo un proyecto aprobado puede tener fase siguiente.
        $presupuesto = new Presupuesto(['idProyecto' => $fase1->id, 'estado' => Presupuesto::ESTADO_APROBADO]);
        $presupuesto->save(false);
        $siguiente = (int) (new CodificacionService())->correlativos()[CorrelativoDocumento::TIPO_NUMERO_PROYECTO]->siguiente;

        $fase2 = $this->crear('Proyecto test fases', $fase1->id);

        $this->assertSame((int) $fase1->numeroProyecto, (int) $fase2->numeroProyecto);
        $this->assertSame(2, (int) $fase2->numeroFase);
        // No gasto un numero.
        $this->assertSame($siguiente, (int) (new CodificacionService())->correlativos()[CorrelativoDocumento::TIPO_NUMERO_PROYECTO]->siguiente);
        $this->assertTrue(Proyecto::findOne($fase1->id)->tieneVariasFases());
        $this->assertStringEndsWith(' · Fase 2', $fase2->identificador());

        // Otra fase 2 del mismo proyecto no se permite.
        $repetida = new Proyecto();
        $this->assertFalse($this->proyectos->crear($repetida, $this->datos('Proyecto test fase repetida', $fase1->id)));
        $this->assertStringContainsString('ya tiene una fase 2', (string) $repetida->getFirstError('idProyectoAnterior'));
    }

    public function testLaFaseNuevaTraeLosDatosDelProyectoYNoLosCambia(): void
    {
        $fase1 = $this->crear('Proyecto test datos comunes', null, ['descripcion' => 'Alcance test', 'areas' => [['manual' => '1', 'edificio' => 'Edificio test fase 1']]]);
        $this->aprobar($fase1);

        // "Agregar fase": el formulario llega con los datos del proyecto, sin area.
        $nueva = $this->proyectos->nuevo($fase1->id);
        $this->assertSame([(int) $fase1->id, 2, (int) $fase1->numeroProyecto], [(int) $nueva->idProyectoAnterior, (int) $nueva->numeroFase, (int) $nueva->numeroProyecto]);
        $this->assertSame(['Proyecto test datos comunes', 'Alcance test'], [$nueva->nombre, $nueva->descripcion]);
        $this->assertEmpty($nueva->areas);

        // Lo que llegue del formulario para los datos del proyecto no cuenta.
        $fase2 = $this->crear('Otro nombre que no debe quedar', $fase1->id, ['unidadSolicitante' => 'Otra unidad test']);
        $this->assertSame('Proyecto test datos comunes', $fase2->nombre);
        $this->assertSame('Facultad test', $fase2->unidadSolicitante);

        // Al editar la fase 2 tampoco: ni los datos del proyecto ni su fase anterior.
        $this->assertTrue($this->proyectos->actualizar($fase2, $this->datos('Nombre editado test', null)), json_encode($fase2->errors));
        $fase2 = Proyecto::findOne($fase2->id);
        $this->assertSame('Proyecto test datos comunes', $fase2->nombre);
        $this->assertSame((int) $fase1->id, (int) $fase2->idProyectoAnterior);
        $this->assertSame(2, (int) $fase2->numeroFase);
    }

    public function testSoloSeAgregaFaseALaUltimaFaseAprobada(): void
    {
        $fase1 = $this->crear('Proyecto test última fase');
        // Sin aprobar: no.
        $this->assertFalse($this->proyectos->puedeAgregarFase($fase1));
        $this->aprobar($fase1);
        $this->assertTrue($this->proyectos->puedeAgregarFase(Proyecto::findOne($fase1->id)));

        $this->crear('Proyecto test última fase', $fase1->id);
        // Ya tiene fase 2: la fase 1 deja de ser la ultima.
        $this->assertFalse($this->proyectos->puedeAgregarFase(Proyecto::findOne($fase1->id)));
        $this->assertNull($this->proyectos->nuevo($fase1->id)->idProyectoAnterior);
    }

    private function aprobar(Proyecto $proyecto): void
    {
        (new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => Presupuesto::ESTADO_APROBADO]))->save(false);
    }

    private function crear(string $nombre, ?int $idAnterior = null, array $mas = []): Proyecto
    {
        $proyecto = new Proyecto();
        $datos = $this->datos($nombre, $idAnterior);
        $datos['Proyecto'] = $mas + $datos['Proyecto'];
        $this->assertTrue($this->proyectos->crear($proyecto, $datos), json_encode($proyecto->errors));

        return Proyecto::findOne($proyecto->id);
    }

    private function datos(string $nombre, ?int $idAnterior): array
    {
        return ['Proyecto' => [
            'nombre' => $nombre,
            'idProceso' => (string) (new ProcesoService())->idPorDefecto(),
            'idProyectoAnterior' => $idAnterior === null ? '' : (string) $idAnterior,
            'equipo' => $this->equipo,
            'equipoResponsable' => '0',
            'unidadDependencia' => 'Carrera test',
            'unidadSolicitante' => 'Facultad test',
        ]];
    }
}
