<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\HistorialProyecto;
use backend\models\Personal;
use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use backend\services\EspecificacionEmisionService;
use backend\services\EspecificacionService;
use backend\services\FichaTecnicaService;
use backend\services\ProcesoService;
use backend\services\ProyectoService;
use backend\services\RolProyectoService;
use backend\services\VersionProyectoService;
use Codeception\Test\Unit;

/**
 * Version de una fase aprobada (2026-11-23/24): un presupuesto aprobado cuyas
 * cotizaciones quedaron vencidas (o que hay que corregir) se actualiza
 * abriendo una version nueva, guardada como una fila mas de redes.Proyecto
 * (mismo numero y fase, idVersionAnterior + numeroVersionMayor/Menor), con los
 * precios copiados tal cual.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class VersionProyectoTest extends Unit
{
    private ProyectoService $proyectos;
    private VersionProyectoService $versiones;

    protected function _before(): void
    {
        $this->proyectos = new ProyectoService();
        $this->versiones = new VersionProyectoService();
        (new Personal(['nombre' => 'Ing. Test Versión']))->save(false);
    }

    public function testSoloSobreUnaFaseAprobadaEnSuUltimaVersion(): void
    {
        $fase = $this->crearFase();

        // Sin aprobar: no.
        $this->assertFalse($this->versiones->puedeAbrir($fase));
        $version = $this->versiones->nueva($fase);
        $this->assertFalse($this->versiones->crear($fase, $version, ['Proyecto' => ['motivoVersion' => 'Prueba']], 'menor'));

        $fase = $this->aprobarConUnItem($fase)[0];
        $this->assertTrue($this->versiones->puedeAbrir($fase));

        $version = $this->abrirVersion($fase, 'menor', 'Cotizaciones vencidas por demora en el financiamiento');
        // Misma fase (mismo numero y numeroFase), subversion 1.1.
        $this->assertSame([(int) $fase->id, (int) $fase->numeroProyecto, (int) $fase->numeroFase, 1, 1], [
            (int) $version->idVersionAnterior, (int) $version->numeroProyecto, (int) $version->numeroFase,
            (int) $version->numeroVersionMayor, (int) $version->numeroVersionMenor,
        ]);
        $this->assertSame('1.1', $version->numeroVersionTexto());

        // Sobre esa misma fase ya no (no es su ultima version).
        $this->assertFalse($this->versiones->puedeAbrir(Proyecto::findOne($fase->id)));
    }

    public function testCopiaLaListaYElPresupuestoConPreciosTalCual(): void
    {
        [$fase, $item] = $this->aprobarConUnItem($this->crearFase());
        $lineaVieja = PresupuestoDetalle::findOne(['idProyectoItem' => $item->id]);

        $version = $this->abrirVersion($fase, 'mayor', 'Se agrega y quita ítems');

        $itemNuevo = ProyectoItem::find()->andWhere(['idProyecto' => $version->id])->one();
        $this->assertNotNull($itemNuevo);
        $this->assertNotSame($item->id, $itemNuevo->id);
        // Sin ficha enlazada porque el item original tampoco tenia una (ver testReemiteElEtDeLosItemsSinCambios).
        $this->assertNull($itemNuevo->idEspecificacion);

        $lineaNueva = PresupuestoDetalle::findOne(['idProyectoItem' => $itemNuevo->id]);
        $this->assertNotNull($lineaNueva);
        $this->assertSame([(float) $lineaVieja->precioUnitario, $lineaVieja->partida, (float) $lineaVieja->cantidad], [
            (float) $lineaNueva->precioUnitario, $lineaNueva->partida, (float) $lineaNueva->cantidad,
        ]);
        // Precio directo (no la cotizacion vieja, que es de otro item) con su origen anotado.
        $this->assertNull($lineaNueva->idCotizacionDetalle);
        $this->assertStringContainsString('versión 1.0', (string) $lineaNueva->fuentePrecio);

        $presupuestoNuevo = Proyecto::findOne($version->id)->presupuesto;
        $this->assertSame(Presupuesto::ESTADO_EN_PROCESO, $presupuestoNuevo->estado);
        $this->assertNotSame($fase->presupuesto->numeroPresupuesto, $presupuestoNuevo->numeroPresupuesto);
        $this->assertNotSame($fase->codigoLista, $version->codigoLista);
        $this->assertNull($version->codigo);
    }

    public function testLaFilaViejaQuedaDeSoloLecturaPeroNoSeBorra(): void
    {
        [$fase] = $this->aprobarConUnItem($this->crearFase());
        $version = $this->abrirVersion($fase, 'menor', 'Prueba de solo lectura');

        $fase = Proyecto::findOne($fase->id);
        $this->assertFalse($fase->esVersionVigente);
        $this->assertFalse($fase->esEditable());
        $this->assertTrue($fase->esUltimaVersion() === false);
        // Sigue existiendo, con su presupuesto aprobado intacto.
        $this->assertSame(Presupuesto::ESTADO_APROBADO, $fase->presupuesto->estado);

        $version = Proyecto::findOne($version->id);
        $this->assertTrue($version->esVersionVigente);
        $this->assertTrue($version->esEditable());

        // getFases() solo trae la vigente; getVersiones() trae las dos.
        $this->assertSame([(int) $version->id], array_map(static fn (Proyecto $f) => (int) $f->id, $version->fases));
        $this->assertSame([(int) $fase->id, (int) $version->id], array_map(static fn (Proyecto $v) => (int) $v->id, $version->versiones));

        // Anotado en las dos.
        foreach ([$fase->id, $version->id] as $id) {
            $this->assertTrue(HistorialProyecto::find()->andWhere(['idProyecto' => $id, 'accion' => HistorialProyecto::ACCION_VERSION_CREADA])->exists());
        }
    }

    public function testElCodigoDeLaFichaYElCostoTotalSiguenCorrectos(): void
    {
        [$fase] = $this->aprobarConUnItem($this->crearFase());
        $codigoFicha = $fase->codigoFicha();
        $this->assertNotNull($codigoFicha);
        $montoAprobado = (new FichaTecnicaService())->costoTotal($fase)['total'];
        $this->assertGreaterThan(0.0, $montoAprobado);

        $version = $this->abrirVersion($fase, 'menor', 'Prueba PT y costo total');
        $version = Proyecto::findOne($version->id);

        // El PT se queda en la fila vieja (NULL en la nueva), pero codigoFicha() lo sigue encontrando.
        $this->assertNull($version->codigo);
        $this->assertSame($codigoFicha, $version->codigoFicha());

        // El costo total ya no cuenta la fila vieja (su presupuesto aprobado sigue ahi, pero no es la vigente):
        // el presupuesto de la version nueva esta "en proceso", sin aprobar, asi que el costo total baja a 0.
        $this->assertSame(0, (new FichaTecnicaService())->costoTotal($version)['total']);
    }

    /**
     * Al copiar, el item todavia no cambio nada: si ya tenia una ficha con ET
     * vigente, se lleva la ficha y se reemite el ET (mismo codigo, version +
     * 1), para no darle trabajo extra al encargado cuando solo abre la
     * version para actualizar precios.
     */
    public function testReemiteElEtDeLosItemsSinCambios(): void
    {
        $fase = $this->crearFase();

        $itemSubcatalogo = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        if ($itemSubcatalogo === null) {
            $this->markTestSkipped('La base local no tiene ítems activos en el subcatálogo.');
        }

        $ficha = new Especificacion(['titulo' => 'Ficha test versión', 'activo' => true]);
        $ficha->save(false);
        (new EspecificacionService())->guardarVersion($ficha, [], [['nombre' => 'Calibre', 'valor' => '23 AWG']]);

        $item = new ProyectoItem([
            'idProyecto' => $fase->id,
            'idSubcatalogo' => $itemSubcatalogo->id,
            'idCategoria' => $itemSubcatalogo->idCategoria,
            'idEspecificacion' => $ficha->id,
            'cantidad' => 3,
            'orden' => 1,
        ]);
        $item->save(false);
        $emisionVieja = (new EspecificacionEmisionService())->emitir($item, 'encargado test');

        (new PresupuestoDetalle([
            'idPresupuesto' => $this->crearPresupuestoAprobado($fase)->id,
            'idProyectoItem' => $item->id,
            'idSubcatalogo' => $item->idSubcatalogo,
            'cantidad' => 3,
            'precioUnitario' => 125.50,
            'partida' => '34500',
        ]))->save(false);

        $version = $this->abrirVersion(Proyecto::findOne($fase->id), 'menor', 'Prueba de reemisión de ET');

        $itemNuevo = ProyectoItem::find()->andWhere(['idProyecto' => $version->id])->one();
        $this->assertNotNull($itemNuevo);
        $this->assertSame((int) $ficha->id, (int) $itemNuevo->idEspecificacion);

        $emisionNueva = $itemNuevo->emisionVigente;
        $this->assertNotNull($emisionNueva);
        $this->assertSame([$emisionVieja->numeroNet, $emisionVieja->proceso, (int) $emisionVieja->versionDocumento + 1], [
            $emisionNueva->numeroNet, $emisionNueva->proceso, (int) $emisionNueva->versionDocumento,
        ]);

        $emisionVieja = EspecificacionEmision::findOne($emisionVieja->id);
        $this->assertSame(EspecificacionEmision::ESTADO_REEMPLAZADA, $emisionVieja->estado);
        $this->assertSame((int) $emisionNueva->id, (int) $emisionVieja->idReemplazadaPor);
        $this->assertStringContainsString('sin cambios', (string) $emisionVieja->motivo);
    }

    /**
     * @return array{0: Proyecto, 1: ProyectoItem}
     */
    private function aprobarConUnItem(Proyecto $fase): array
    {
        $itemSubcatalogo = SubcatalogoItem::find()->andWhere(['activo' => true])->one();
        if ($itemSubcatalogo === null) {
            $this->markTestSkipped('La base local no tiene ítems activos en el subcatálogo.');
        }

        $item = new ProyectoItem([
            'idProyecto' => $fase->id,
            'idSubcatalogo' => $itemSubcatalogo->id,
            'idCategoria' => $itemSubcatalogo->idCategoria,
            'cantidad' => 3,
            'orden' => 1,
        ]);
        $item->save(false);

        (new PresupuestoDetalle([
            'idPresupuesto' => $this->crearPresupuestoAprobado($fase)->id,
            'idProyectoItem' => $item->id,
            'idSubcatalogo' => $item->idSubcatalogo,
            'cantidad' => 3,
            'precioUnitario' => 125.50,
            'partida' => '34500',
        ]))->save(false);

        return [Proyecto::findOne($fase->id), $item];
    }

    private function crearPresupuestoAprobado(Proyecto $fase): Presupuesto
    {
        $presupuesto = new Presupuesto([
            'idProyecto' => $fase->id,
            'estado' => Presupuesto::ESTADO_APROBADO,
            'numeroPresupuesto' => 'RD-PI-PR-TEST-VER',
            'fechaAprobacion' => '2026-11-20',
        ]);
        $presupuesto->save(false);

        return $presupuesto;
    }

    private function abrirVersion(Proyecto $fase, string $tipoVersion, string $motivo): Proyecto
    {
        $version = $this->versiones->nueva($fase);
        $this->assertTrue(
            $this->versiones->crear($fase, $version, ['Proyecto' => ['motivoVersion' => $motivo]], $tipoVersion),
            json_encode($version->errors)
        );

        return Proyecto::findOne($version->id);
    }

    private function crearFase(): Proyecto
    {
        $proyecto = new Proyecto();
        $this->assertTrue($this->proyectos->crear($proyecto, ['Proyecto' => [
            'nombre' => 'Proyecto test versión',
            'idProceso' => (string) (new ProcesoService())->idPorDefecto(),
            'idProyectoAnterior' => '',
            'unidadDependencia' => 'Carrera test versión',
            'unidadSolicitante' => 'Facultad test versión',
            'lugarEntrega' => 'Almacén test',
            'areas' => [['manual' => '1', 'edificio' => 'Edificio test versión']],
            'equipo' => [['nombre' => 'Ing. Test Versión', 'idRol' => (string) (new RolProyectoService())->idProyectista()]],
            'equipoResponsable' => '0',
        ]]), json_encode($proyecto->errors));

        return Proyecto::findOne($proyecto->id);
    }
}
