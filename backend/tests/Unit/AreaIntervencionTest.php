<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Proyecto;
use backend\models\ProyectoAreaIntervencion;
use backend\models\ProyectoBloque;
use backend\services\contracts\InfraestructuraProvider;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use backend\services\UsfxGeoInfraestructuraProvider;
use Codeception\Test\Unit;
use Throwable;
use Yii;

/**
 * Unidad solicitante (texto libre) y area de intervencion (edificio -> bloque
 * -> nivel -> ambiente, de la infraestructura de la universidad), revision
 * del 2026-10-27.
 *
 * Usa una fuente de infraestructura DE PRUEBA (un edificio con un bloque, un
 * piso y dos laboratorios), asi los tests no dependen de UsfxGEO. Solo el
 * ultimo test lee la copia local de UsfxGEO, y se salta si no esta.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class AreaIntervencionTest extends Unit
{
    private InfraestructuraProvider $fuente;
    private ProyectoService $service;

    /** Proceso PI: el proyecto lo exige al guardar. */
    private ?int $idProceso;

    protected function _before(): void
    {
        $this->idProceso = (new ProcesoService())->idPorDefecto();
        $this->fuente = new class implements InfraestructuraProvider {
            /** Si es false, el edificio "E1" deja de estar vigente. */
            public bool $vigente = true;

            public function edificios(): array
            {
                return $this->vigente ? ['E1' => 'Edificio Prueba'] : [];
            }

            public function bloques(string $idEdificio): array
            {
                return $idEdificio === 'E1' && $this->vigente ? [10 => 'Bloque A'] : [];
            }

            public function niveles(int $idBloqueArea): array
            {
                return $idBloqueArea === 10 ? ['1' => 'Piso 1'] : [];
            }

            public function ambientes(int $idBloqueArea, string $numeroNivel): array
            {
                return $idBloqueArea === 10 && $numeroNivel === '1' ? ['X1' => 'B101 Laboratorio', 'X2' => 'B102 Laboratorio'] : [];
            }

            public function buscarEdificios(string $texto, int $limite = 10): array
            {
                return [];
            }

            public function buscarAmbientes(string $texto, int $limite = 30): array
            {
                return [];
            }

            public function ruta(string $idEdificio, ?int $idBloqueArea, ?string $numeroNivel, ?string $idEspacio): ?array
            {
                $edificio = $this->edificios()[$idEdificio] ?? null;

                if ($edificio === null || ($idEspacio !== null && $numeroNivel === null) || ($numeroNivel !== null && $idBloqueArea === null)) {
                    return null;
                }

                $ruta = ['edificio' => $edificio, 'bloque' => null, 'nivel' => null, 'codigoAmbiente' => null, 'ambiente' => null];

                if ($idBloqueArea !== null) {
                    $ruta['bloque'] = $this->bloques($idEdificio)[$idBloqueArea] ?? null;
                }

                if ($numeroNivel !== null) {
                    $ruta['nivel'] = $this->niveles((int) $idBloqueArea)[$numeroNivel] ?? null;
                }

                if ($idEspacio !== null) {
                    $ruta['ambiente'] = $this->ambientes((int) $idBloqueArea, (string) $numeroNivel)[$idEspacio] ?? null;
                    $ruta['codigoAmbiente'] = $ruta['ambiente'] === null ? null : strtok($ruta['ambiente'], ' ');
                }

                foreach (['bloque' => $idBloqueArea, 'nivel' => $numeroNivel, 'ambiente' => $idEspacio] as $parte => $id) {
                    if ($id !== null && $ruta[$parte] === null) {
                        return null;
                    }
                }

                return $ruta;
            }
        };

        $this->service = new ProyectoService(null, $this->fuente);
    }

    public function testGuardaUnidadYAreasConNombresDeLaFuente(): void
    {
        $proyecto = new Proyecto();

        $guardado = $this->service->crear($proyecto, ['Proyecto' => [
            'nombre' => 'Proyecto test área',
            'idProceso' => $this->idProceso, 'unidadDependencia' => ' Canal Universitario  ',
            'unidadSolicitante' => '  Secretaría General de Vicerrectorado ',
            'areas' => [
                ['idEdificio' => 'E1', 'idBloqueArea' => '10', 'numeroNivel' => '1', 'idEspacio' => 'X1'],
                ['idEdificio' => 'E1', 'idBloqueArea' => '10', 'numeroNivel' => '1', 'idEspacio' => 'X2'],
                // Repetida: se guarda una vez.
                ['idEdificio' => 'E1', 'idBloqueArea' => '10', 'numeroNivel' => '1', 'idEspacio' => 'X1'],
                // Fila vacia del formulario: se ignora.
                ['idEdificio' => '', 'idBloqueArea' => '', 'numeroNivel' => '', 'idEspacio' => ''],
                // Los nombres que lleguen del navegador no cuentan: salen de la fuente.
                ['idEdificio' => 'E1', 'edificio' => 'Nombre inventado'],
            ],
        ]]);

        $this->assertTrue($guardado, json_encode($proyecto->errors));
        $proyecto->refresh();
        $this->assertSame('Secretaría General de Vicerrectorado', $proyecto->unidadSolicitante);
        $this->assertSame('Canal Universitario', $proyecto->unidadDependencia);

        $areas = $proyecto->areasIntervencion;
        $this->assertCount(3, $areas);
        $this->assertSame('Edificio Prueba · Bloque A · Piso 1: B101 Laboratorio', $areas[0]->texto());
        $this->assertSame('B101', $areas[0]->codigoAmbiente);
        $this->assertSame('Edificio Prueba', $areas[2]->texto());
        // Los laboratorios del mismo bloque salen en una sola linea.
        $this->assertSame('Edificio Prueba · Bloque A: B101 Laboratorio, B102 Laboratorio; Edificio Prueba', $proyecto->areaIntervencionTexto());
    }

    public function testRechazaUnAreaQueNoExisteOSeSaltaUnNivel(): void
    {
        foreach ([
            ['idEdificio' => 'NO'],
            ['idEdificio' => 'E1', 'idBloqueArea' => '99'],
            // Ambiente sin nivel: no se puede saltar un paso.
            ['idEdificio' => 'E1', 'idBloqueArea' => '10', 'idEspacio' => 'X1'],
        ] as $area) {
            $proyecto = new Proyecto();

            $this->assertFalse($this->service->crear($proyecto, ['Proyecto' => [
                'nombre' => 'Proyecto test área inválida',
                'idProceso' => $this->idProceso, 'unidadDependencia' => 'Carrera test',
                'unidadSolicitante' => 'Facultad test',
                'areas' => [$area],
            ]]), json_encode($area));
            $this->assertTrue($proyecto->hasErrors('areas'));
            $this->assertTrue($proyecto->isNewRecord);
        }
    }

    public function testLasDosUnidadesSonObligatoriasYElAreaNo(): void
    {
        $proyecto = new Proyecto();
        $this->assertFalse($this->service->crear($proyecto, ['Proyecto' => ['nombre' => 'Proyecto test sin unidad', 'idProceso' => $this->idProceso, 'unidadDependencia' => ' ', 'unidadSolicitante' => '  ']]));
        $this->assertTrue($proyecto->hasErrors('unidadDependencia'));
        $this->assertTrue($proyecto->hasErrors('unidadSolicitante'));

        $proyecto = new Proyecto();
        $this->assertTrue($this->service->crear($proyecto, ['Proyecto' => ['nombre' => 'Proyecto test sin área', 'idProceso' => $this->idProceso, 'unidadDependencia' => 'Laboratorio de Física', 'unidadSolicitante' => 'Ing. Pérez, Laboratorio de Física']]), json_encode($proyecto->errors));
        $this->assertSame([], $proyecto->areasIntervencion);
    }

    public function testUnAreaEscritaAManoSeGuardaSinConsultarUsfxGeo(): void
    {
        $proyecto = new Proyecto();
        $datos = ['Proyecto' => [
            'nombre' => 'Proyecto test área a mano',
            'idProceso' => $this->idProceso, 'unidadDependencia' => 'Carrera test',
            'unidadSolicitante' => 'Facultad test',
            'areas' => [
                // Edificio en construccion: no esta en la fuente.
                ['manual' => '1', 'edificio' => '  Bloque   Ingeniería Civil ', 'bloque' => '', 'nivel' => 'Planta baja', 'ambiente' => 'Sala de servidores'],
                // La misma con otras mayusculas: se guarda una vez.
                ['manual' => '1', 'edificio' => 'BLOQUE INGENIERÍA CIVIL', 'nivel' => 'PLANTA BAJA', 'ambiente' => 'SALA DE SERVIDORES'],
                // Sin edificio: fila vacia, se ignora.
                ['manual' => '1', 'edificio' => ' ', 'bloque' => 'Bloque Z'],
                // Junto a una de UsfxGEO.
                ['idEdificio' => 'E1'],
            ],
        ]];

        $this->assertTrue($this->service->crear($proyecto, $datos), json_encode($proyecto->errors));

        $areas = Proyecto::findOne($proyecto->id)->areasIntervencion;
        $this->assertCount(2, $areas);
        $this->assertTrue($areas[0]->esManual());
        $this->assertNull($areas[0]->idEdificio);
        $this->assertSame('Bloque Ingeniería Civil · Planta baja: Sala de servidores', $areas[0]->texto());
        $this->assertFalse($areas[1]->esManual());

        // Al editar se conserva y el formulario la vuelve a mostrar como escrita a mano.
        $proyecto = Proyecto::findOne($proyecto->id);
        $this->service->cargarSeleccion($proyecto);
        $formulario = $this->service->areasDelFormulario($proyecto);
        $this->assertTrue($formulario[0]['manual']);
        $this->assertSame('Bloque Ingeniería Civil', $formulario[0]['edificio']);

        $this->assertTrue($this->service->actualizar($proyecto, ['Proyecto' => ['areas' => $proyecto->areas]]), json_encode($proyecto->errors));
        // En los documentos sale igual que las demas (el resumen nombra el espacio sin el piso).
        $this->assertSame('Bloque Ingeniería Civil: Sala de servidores; Edificio Prueba', Proyecto::findOne($proyecto->id)->areaIntervencionTexto());

        // Un nombre demasiado largo no se guarda.
        $datos['Proyecto']['areas'] = [['manual' => '1', 'edificio' => str_repeat('x', 201)]];
        $this->assertFalse($this->service->actualizar($proyecto, $datos));
        $this->assertTrue($proyecto->hasErrors('areas'));
    }

    public function testUnAreaGuardadaSeConservaAunqueYaNoEsteVigente(): void
    {
        $proyecto = new Proyecto();
        $this->assertTrue($this->service->crear($proyecto, ['Proyecto' => [
            'nombre' => 'Proyecto test área dada de baja',
            'idProceso' => $this->idProceso, 'unidadDependencia' => 'Carrera test',
            'unidadSolicitante' => 'Facultad test',
            'areas' => [['idEdificio' => 'E1', 'idBloqueArea' => '10']],
        ]]));

        // Despues dan de baja el edificio en la fuente.
        $this->fuente->vigente = false;
        $proyecto = Proyecto::findOne($proyecto->id);
        $this->service->cargarSeleccion($proyecto);
        $this->assertSame('Edificio Prueba · Bloque A', $this->service->areasDelFormulario($proyecto)[0]['texto']);

        // Editar otro dato no la pierde ni da error: conserva su nombre de entonces.
        $datos = ['Proyecto' => ['nombre' => 'Proyecto test renombrado', 'idProceso' => $this->idProceso, 'unidadDependencia' => 'Carrera test', 'unidadSolicitante' => 'Facultad test', 'areas' => $proyecto->areas]];
        $this->assertTrue($this->service->actualizar($proyecto, $datos), json_encode($proyecto->errors));
        $this->assertSame('Edificio Prueba · Bloque A', Proyecto::findOne($proyecto->id)->areaIntervencionTexto());

        // Pero no se puede agregar una nueva de ese edificio.
        $datos['Proyecto']['areas'][] = ['idEdificio' => 'E1'];
        $this->assertFalse($this->service->actualizar($proyecto, $datos));
        $this->assertTrue($proyecto->hasErrors('areas'));
    }

    public function testUnProyectoAnteriorAlCambioMuestraSuUbicacionDeAntes(): void
    {
        $proyecto = new Proyecto(['nombre' => 'Proyecto test de antes', 'facultad' => 'Facultad de Medicina', 'edificio' => 'Anfiteatro de Anatomía', 'estadoLista' => Proyecto::LISTA_EDICION]);
        $proyecto->save(false);
        (new ProyectoBloque(['idProyecto' => $proyecto->id, 'codigo' => 'A', 'nombre' => 'Bloque A']))->save(false);

        $this->assertSame('Anfiteatro de Anatomía / Bloque A', $proyecto->areaIntervencionTexto());

        // Al elegirle areas, se muestran ellas; la ubicacion de antes queda guardada.
        $proyecto->idProceso = $this->idProceso;
        $proyecto->unidadDependencia = 'Facultad de Medicina';
        $proyecto->unidadSolicitante = 'Facultad de Medicina';
        $this->assertTrue($this->service->actualizar($proyecto, ['Proyecto' => ['areas' => [['idEdificio' => 'E1']]]]), json_encode($proyecto->errors));
        $proyecto = Proyecto::findOne($proyecto->id);
        $this->assertSame('Edificio Prueba', $proyecto->areaIntervencionTexto());
        $this->assertCount(1, $proyecto->bloques);
    }

    public function testSugiereUnidadesYaEscritasYFacultadesSinRepetir(): void
    {
        (new Proyecto(['nombre' => 'Proyecto test sugerencia', 'unidadSolicitante' => 'Centro Bibliográfico Test', 'estadoLista' => Proyecto::LISTA_EDICION]))->save(false);
        // La misma unidad escrita como dependencia en otro proyecto tampoco se repite.
        (new Proyecto(['nombre' => 'Proyecto test sugerencia 2', 'idProceso' => $this->idProceso, 'unidadDependencia' => 'CENTRO BIBLIOGRÁFICO TEST', 'estadoLista' => Proyecto::LISTA_EDICION]))->save(false);
        (new Proyecto(['nombre' => 'Proyecto test sugerencia 3', 'idProceso' => $this->idProceso, 'unidadDependencia' => 'Canal Universitario Test', 'estadoLista' => Proyecto::LISTA_EDICION]))->save(false);

        $sugerencias = $this->service->sugerenciasUnidad();

        $this->assertCount(1, array_filter($sugerencias, static fn ($texto) => mb_strtolower($texto, 'UTF-8') === 'centro bibliográfico test'));
        $this->assertContains('Canal Universitario Test', $sugerencias);
        $this->assertContains('Facultad de Medicina', $sugerencias);
    }

    public function testElCampoEdificioSabeCuantosProyectosUsanCadaUno(): void
    {
        $antes = $this->service->edificiosParaFormulario()[0]['usos'];

        foreach (['Proyecto test uso 1', 'Proyecto test uso 2'] as $nombre) {
            $this->assertTrue($this->service->crear(new Proyecto(), ['Proyecto' => [
                'nombre' => $nombre,
                'idProceso' => $this->idProceso, 'unidadDependencia' => 'Carrera test',
                'unidadSolicitante' => 'Facultad test',
                // Dos areas del mismo edificio cuentan como un proyecto.
                'areas' => [['idEdificio' => 'E1'], ['idEdificio' => 'E1', 'idBloqueArea' => '10']],
            ]]));
        }

        $this->assertSame([['id' => 'E1', 'nombre' => 'Edificio Prueba', 'usos' => $antes + 2]], $this->service->edificiosParaFormulario());
    }

    public function testResumenAgrupaPorEdificioYBloque(): void
    {
        $area = static fn (array $datos) => new ProyectoAreaIntervencion($datos);

        $this->assertSame('Edificio X · Bloque B: Piso 2; Edificio Y', ProyectoAreaIntervencion::resumen([
            $area(['edificio' => 'Edificio X', 'bloque' => 'Bloque B', 'nivel' => 'Piso 2']),
            $area(['edificio' => 'Edificio Y']),
            $area(['edificio' => 'Edificio X', 'bloque' => 'Bloque B', 'nivel' => 'Piso 2']),
        ]));
    }

    public function testElGrupoMuestraElNombreDeUsfxGeoSinRotulo(): void
    {
        $area = new ProyectoAreaIntervencion(['edificio' => 'Edificio Carrera de Pedagogia', 'bloque' => 'Bloque A de Aulas y Oficinas', 'nivel' => 'Piso 1', 'ambiente' => 'A100 Gradas']);
        $this->assertSame('Edificio Carrera de Pedagogia · Bloque A de Aulas y Oficinas', $area->grupo());
        $this->assertSame('Piso 1: A100 Gradas', $area->detalle());
    }

    /**
     * Contra la copia local de UsfxGEO: lo que encuentra el buscador se puede
     * guardar (ruta() lo reconoce con los mismos nombres).
     */
    public function testUsfxGeoReconoceLoQueEncuentraElBuscador(): void
    {
        $fuente = new UsfxGeoInfraestructuraProvider();

        try {
            if (!Yii::$app->has('dbGeo')) {
                $this->markTestSkipped('Sin conexión "dbGeo" configurada.');
            }
            $encontrados = $fuente->buscarAmbientes('laboratorio', 5);
        } catch (Throwable $e) {
            $this->markTestSkipped('UsfxGEO no disponible: ' . $e->getMessage());
        }

        $this->assertNotEmpty($encontrados);

        foreach ($encontrados as $fila) {
            $ruta = $fuente->ruta($fila['idEdificio'], $fila['idBloqueArea'], $fila['numeroNivel'], $fila['idEspacio']);
            $this->assertNotNull($ruta);
            $this->assertSame($fila['edificio'], $ruta['edificio']);
            $this->assertSame($fila['ambiente'], $ruta['ambiente']);
        }

        // El edificio sale aunque tenga muchos ambientes, y el guion no importa.
        $this->assertContains('Edificio Facultad de Ciencias y Tecnología', array_column($fuente->buscarEdificios('ciencias y tecnologia'), 'edificio'));
        $this->assertNotEmpty($fuente->buscarAmbientes('E303'));
        $buscador = (new ProyectoService(null, $fuente))->buscarAreas('ciencias y tecnologia');
        $this->assertTrue($buscador['hayMas']);
        $this->assertCount(ProyectoService::MAX_AMBIENTES_BUSCADOS, $buscador['ambientes']);

        $this->assertSame('B101 Laboratorio', UsfxGeoInfraestructuraProvider::nombreAmbiente('B101', ' Laboratorio '));
        $this->assertSame('Laboratorio de Informática E102', UsfxGeoInfraestructuraProvider::nombreAmbiente('E102', 'Laboratorio de Informática E102'));
    }
}
