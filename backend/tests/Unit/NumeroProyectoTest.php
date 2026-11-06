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

    private function crear(string $nombre, ?int $idAnterior = null): Proyecto
    {
        $proyecto = new Proyecto();
        $this->assertTrue($this->proyectos->crear($proyecto, $this->datos($nombre, $idAnterior)), json_encode($proyecto->errors));

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
