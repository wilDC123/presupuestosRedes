<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Personal;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\models\ProyectoParticipante;
use backend\models\RolProyecto;
use backend\services\PersonalService;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use backend\services\RolProyectoService;
use Codeception\Test\Unit;

/**
 * Equipo del proyecto (revision del 2026-10-29, paso 5): personas de
 * redes.Personal con un rol de redes.RolProyecto y una responsable. Solo se
 * eligen personas y roles activos, pero el proyecto conserva los que ya
 * tenia; uno en uso no se elimina (se desactiva). La fase siguiente hereda
 * el equipo.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class EquipoProyectoTest extends Unit
{
    private PersonalService $personal;
    private RolProyectoService $roles;
    private ProyectoService $proyectos;
    private int $idProyectista;
    private int $idCoordinador;

    protected function _before(): void
    {
        $this->personal = new PersonalService();
        $this->roles = new RolProyectoService();
        $this->proyectos = new ProyectoService();
        $this->idProyectista = (int) $this->roles->idProyectista();
        $this->idCoordinador = (int) RolProyecto::find()->select('id')->where(['nombre' => 'Coordinador de infraestructura'])->scalar();

        foreach (['Ing. Test Uno', 'Ing. Test Dos'] as $nombre) {
            $this->assertTrue((new Personal(['nombre' => $nombre]))->save());
        }
    }

    public function testNoAdmiteNombreRepetido(): void
    {
        $otra = new Personal();
        $this->assertFalse($this->personal->crear($otra, ['Personal' => ['nombre' => '  Ing. Test Uno ']]));
        $this->assertTrue($otra->hasErrors('nombre'));

        $rol = new RolProyecto();
        $this->assertFalse($this->roles->crear($rol, ['RolProyecto' => ['nombre' => 'Proyectista']]));
        $this->assertTrue($rol->hasErrors('nombre'));
    }

    public function testGuardaElEquipoConRolesYUnResponsable(): void
    {
        $proyecto = new Proyecto();
        $this->assertTrue($this->proyectos->crear($proyecto, $this->datos([
            'a' => $this->fila('Ing. Test Uno', $this->idProyectista),
            // La misma persona con otro rol: se permite.
            'b' => $this->fila('Ing. Test Uno', $this->idCoordinador),
            // Fila en blanco del formulario: se ignora.
            'c' => $this->fila('', null),
            'd' => $this->fila('Ing. Test Dos', $this->idProyectista),
        ], 'd')), json_encode($proyecto->errors));

        $equipo = Proyecto::findOne($proyecto->id)->participantes;
        $this->assertSame(
            [['Ing. Test Uno', $this->idProyectista, false], ['Ing. Test Uno', $this->idCoordinador, false], ['Ing. Test Dos', $this->idProyectista, true]],
            array_map(static fn (ProyectoParticipante $p) => [$p->nombre, (int) $p->idRol, (bool) $p->esResponsable], $equipo)
        );
        $this->assertSame('Ing. Test Dos', Proyecto::findOne($proyecto->id)->responsable()->nombre);

        // Al editar, el formulario lo muestra con su responsable.
        $guardado = Proyecto::findOne($proyecto->id);
        $this->proyectos->cargarSeleccion($guardado);
        $this->assertCount(3, $guardado->equipo);
        $this->assertSame('Ing. Test Dos', $guardado->equipo[$guardado->equipoResponsable]['nombre']);
    }

    public function testReglasDelEquipo(): void
    {
        $casos = [
            'sin nadie' => [[], null],
            'sin responsable' => [[0 => $this->fila('Ing. Test Uno', $this->idProyectista)], null],
            'persona sin rol' => [[0 => $this->fila('Ing. Test Uno', null)], 0],
            'misma persona y rol dos veces' => [[0 => $this->fila('Ing. Test Uno', $this->idProyectista), 1 => $this->fila('Ing. Test Uno', $this->idProyectista)], 0],
            'persona que no esta en la lista' => [[0 => $this->fila('Ing. Inventado', $this->idProyectista)], 0],
            'rol que no existe' => [[0 => $this->fila('Ing. Test Uno', 999999)], 0],
        ];

        foreach ($casos as $caso => [$filas, $responsable]) {
            $proyecto = new Proyecto();
            $this->assertFalse($this->proyectos->crear($proyecto, $this->datos($filas, $responsable)), $caso);
            $this->assertTrue($proyecto->hasErrors('equipo'), $caso);
        }
    }

    public function testInactivosNoSeEligenPeroElProyectoLosConserva(): void
    {
        $proyecto = new Proyecto();
        $datos = $this->datos([0 => $this->fila('Ing. Test Uno', $this->idCoordinador)], 0);
        $this->assertTrue($this->proyectos->crear($proyecto, $datos), json_encode($proyecto->errors));

        // Se desactivan la persona y el rol: no se eliminan, ya no se ofrecen...
        $persona = Personal::findOne(['nombre' => 'Ing. Test Uno']);
        $rol = RolProyecto::findOne($this->idCoordinador);
        $this->assertTrue($this->personal->actualizar($persona, ['Personal' => ['activo' => '0']]));
        $this->assertTrue($this->roles->actualizar($rol, ['RolProyecto' => ['activo' => '0']]));
        $this->assertNotNull($this->personal->eliminar($persona));
        $this->assertNotNull($this->roles->eliminar($rol));
        $this->assertNotContains('Ing. Test Uno', $this->personal->opciones());
        $this->assertArrayNotHasKey($this->idCoordinador, $this->roles->opciones());
        $this->assertArrayHasKey($this->idCoordinador, $this->roles->opciones([$this->idCoordinador]));

        // ...pero el proyecto que los tenia se edita igual,
        $this->assertTrue($this->proyectos->actualizar(Proyecto::findOne($proyecto->id), $datos));

        // y un proyecto nuevo ya no puede elegirlos.
        $nuevo = new Proyecto();
        $this->assertFalse($this->proyectos->crear($nuevo, $datos));
        $this->assertTrue($nuevo->hasErrors('equipo'));

        $fila = array_values(array_filter($this->personal->listarConConteo(), static fn ($f) => $f['persona']->id === $persona->id))[0];
        $this->assertSame(1, $fila['proyectos']);
    }

    public function testUnoSinProyectosSeElimina(): void
    {
        $persona = Personal::findOne(['nombre' => 'Ing. Test Dos']);
        $this->assertNull($this->personal->eliminar($persona));
        $this->assertNull(Personal::findOne($persona->id));

        $rol = new RolProyecto(['nombre' => 'Rol test sin uso']);
        $this->assertTrue($rol->save());
        $this->assertNull($this->roles->eliminar($rol));
        $this->assertNull(RolProyecto::findOne($rol->id));
    }

    public function testUnProyectoNuevoTraeUnaFilaDeProyectistaResponsable(): void
    {
        $nuevo = $this->proyectos->nuevo();

        $this->assertSame([0 => ['nombre' => '', 'idRol' => (string) $this->idProyectista]], $nuevo->equipo);
        $this->assertSame(0, $nuevo->equipoResponsable);
    }

    public function testLaFaseSiguienteHeredaElEquipoYSePuedeCambiar(): void
    {
        $anterior = new Proyecto();
        $this->assertTrue($this->proyectos->crear($anterior, $this->datos([
            0 => $this->fila('Ing. Test Uno', $this->idProyectista),
            1 => $this->fila('Ing. Test Dos', $this->idCoordinador),
        ], 1)), json_encode($anterior->errors));
        (new Presupuesto(['idProyecto' => $anterior->id, 'estado' => Presupuesto::ESTADO_APROBADO]))->save(false);

        // Despues "Ing. Test Dos" deja la DTIC: igual se hereda.
        Personal::updateAll(['activo' => false], ['nombre' => 'Ing. Test Dos']);

        $fase2 = $this->proyectos->nuevo($anterior->id);
        $this->assertSame(
            [0 => ['nombre' => 'Ing. Test Uno', 'idRol' => (string) $this->idProyectista], 1 => ['nombre' => 'Ing. Test Dos', 'idRol' => (string) $this->idCoordinador]],
            $fase2->equipo
        );
        $this->assertSame(1, $fase2->equipoResponsable);

        // Se guarda tal cual (el heredado inactivo se acepta) o cambiado.
        $datos = $this->datos($fase2->equipo, $fase2->equipoResponsable);
        $datos['Proyecto']['idProyectoAnterior'] = (string) $anterior->id;
        $datos['Proyecto']['equipoResponsable'] = '0';
        $this->assertTrue($this->proyectos->crear($fase2, $datos), json_encode($fase2->errors));
        $this->assertSame('Ing. Test Uno', Proyecto::findOne($fase2->id)->responsable()->nombre);
        $this->assertCount(2, Proyecto::findOne($fase2->id)->participantes);
    }

    /**
     * @return array{nombre: string, idRol: string}
     */
    private function fila(string $nombre, ?int $idRol): array
    {
        return ['nombre' => $nombre, 'idRol' => $idRol === null ? '' : (string) $idRol];
    }

    /**
     * Lo que manda el formulario: datos minimos del proyecto y su equipo.
     *
     * @param array<int|string, array{nombre: string, idRol: string}> $equipo
     * @param int|string|null $responsable clave de la fila responsable
     */
    private function datos(array $equipo, $responsable): array
    {
        $proyecto = [
            'nombre' => 'Proyecto test equipo',
            'idProceso' => (new ProcesoService())->idPorDefecto(),
            'unidadDependencia' => 'Carrera test',
            'unidadSolicitante' => 'Facultad test',
            'equipo' => $equipo === [] ? '' : $equipo,
        ];
        if ($responsable !== null) {
            $proyecto['equipoResponsable'] = (string) $responsable;
        }

        return ['Proyecto' => $proyecto];
    }
}
