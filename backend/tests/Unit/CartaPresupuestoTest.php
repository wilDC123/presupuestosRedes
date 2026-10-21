<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\CartaPresupuesto;
use backend\models\ConfiguracionCarta;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\services\CartaService;
use Codeception\Test\Unit;

/**
 * Carta de entrega del presupuesto (CartaService): se llena sola, se guarda
 * en cualquier estado, y para imprimir solo se exigen la carta guardada y el
 * oficio y numero del presupuesto, que se ponen al imprimir.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class CartaPresupuestoTest extends Unit
{
    private CartaService $service;

    protected function _before(): void
    {
        $this->service = new CartaService();
    }

    public function testLaCartaNuevaSeLlenaConElProyectoYLaConfiguracion(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);
        $carta = $this->service->nueva($presupuesto);

        $this->assertTrue($carta->isNewRecord);
        $this->assertSame(date('Y-m-d'), $carta->fecha);
        $this->assertSame('DECANO DE LA FACULTAD DE MEDICINA', $carta->destinatarioCargo);
        $this->assertStringContainsString('INSTALACIÓN DE PUNTOS DE RED', $carta->referencia);
        // En medio de la frase, el nombre del proyecto va con minuscula.
        $this->assertStringContainsString('requerimiento de instalación de puntos de red', $carta->cuerpo);
        $this->assertStringContainsString('Facultad de Medicina', $carta->cuerpo);
        // {numeroPresupuesto} queda para reemplazarse al imprimir.
        $this->assertStringContainsString('{numeroPresupuesto}', (string) $carta->adjunto);
        $this->assertSame(ConfiguracionCarta::obtener()->firma3Nombre, $carta->firma3Nombre);
    }

    public function testElCargoSugeridoSigueAlTratamiento(): void
    {
        $this->assertSame('DECANA DE LA FACULTAD DE MEDICINA', $this->service->cargoSugerido('Señora', 'Facultad de Medicina'));
        $this->assertSame('DECANO DE LA FACULTAD DE MEDICINA', $this->service->cargoSugerido('Señor', 'Facultad de Medicina'));
        $this->assertSame('', $this->service->cargoSugerido('Señora', ''));
    }

    public function testSeGuardaEnBorradorPeroNoSeImprimeSinOficioNiNumero(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);
        $carta = $this->service->nueva($presupuesto);

        $this->assertSame(['Guarde la carta.'], array_slice($this->service->faltaParaImprimir($presupuesto), 0, 1));

        $this->assertTrue($this->service->guardar($carta, ['CartaPresupuesto' => [
            'fecha' => '16/10/2026',
            'tratamiento' => 'Señora',
            'destinatarioNombre' => 'Msc. María Esther Santos Parraga',
            'destinatarioCargo' => 'decana de la facultad de enfermería',
        ]]), implode(' ', $carta->getFirstErrors()));
        $this->assertSame('2026-10-16', CartaPresupuesto::findOne($carta->id)->fecha);
        $this->assertSame('DECANA DE LA FACULTAD DE ENFERMERÍA', $carta->destinatarioCargo);

        $presupuesto = Presupuesto::findOne($presupuesto->id);
        $this->assertCount(2, $this->service->faltaParaImprimir($presupuesto), 'faltan oficio y numero');

        $presupuesto->oficioDtic = '598';
        $presupuesto->numeroPresupuesto = '632';
        $presupuesto->save(false);
        $presupuesto = Presupuesto::findOne($presupuesto->id);

        $this->assertSame([], $this->service->faltaParaImprimir($presupuesto), 'no hace falta que este aprobado');
        $this->assertSame('Nro: 632', $this->service->textoParaImprimir('Nro: {numeroPresupuesto}', $presupuesto));
        $this->assertSame('DTIC Of. Nº 598/2026', $this->service->numeroOficio($presupuesto->carta, $presupuesto, ConfiguracionCarta::obtener()));
        $this->assertSame('Sucre, 16 de octubre de 2026', $this->service->lugarYFecha($presupuesto->carta, ConfiguracionCarta::obtener()));
    }

    public function testUnOficioConAnioNoRepiteElAnio(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_APROBADO);
        $presupuesto->oficioDtic = '598/2026';
        $carta = $this->service->nueva($presupuesto);

        $this->assertSame('DTIC Of. Nº 598/2026', $this->service->numeroOficio($carta, $presupuesto, ConfiguracionCarta::obtener()));
    }

    public function testSinFormulariosNetElMarcadorDesapareceConSuEspacio(): void
    {
        $presupuesto = $this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO);

        $this->assertSame('', $this->service->codigosNet($presupuesto));
        $this->assertSame(
            'especificaciones técnicas y cotización.',
            $this->service->textoParaImprimir('especificaciones técnicas {especificaciones} y cotización.', $presupuesto)
        );
        $this->assertStringContainsString('{especificaciones}', (string) $this->service->nueva($presupuesto)->adjunto);
    }

    public function testLasMayusculasNoRompenLosMarcadores(): void
    {
        $this->assertSame('PARA {proyecto} NRO {numeroPresupuesto}', CartaPresupuesto::mayusculas('para {proyecto} nro {numeroPresupuesto}'));
        // Una plantilla ya guardada con {PROYECTO} tambien se reemplaza.
        $this->assertSame('{proyecto} y {especificaciones}', CartaPresupuesto::normalizarMarcadores('{PROYECTO} y {Especificaciones}'));

        $configuracion = ConfiguracionCarta::obtener();
        $configuracion->plantillaReferencia = 'Entrega para {proyecto}, presupuesto {numeroPresupuesto}';
        $this->assertTrue($configuracion->save());
        $this->assertSame('ENTREGA PARA {proyecto}, PRESUPUESTO {numeroPresupuesto}', ConfiguracionCarta::obtener()->plantillaReferencia);

        $carta = $this->service->nueva($this->crearPresupuesto(Presupuesto::ESTADO_EN_PROCESO));
        $this->assertSame('ENTREGA PARA INSTALACIÓN DE PUNTOS DE RED, PRESUPUESTO {numeroPresupuesto}', $carta->referencia);
    }

    public function testParrafosSeSeparanPorLineaEnBlanco(): void
    {
        $this->assertSame(['Uno', "Dos\nsigue", 'Tres'], CartaPresupuesto::parrafos("Uno\n\nDos\nsigue\r\n\r\n\n  Tres  \n"));
    }

    private function crearPresupuesto(string $estado): Presupuesto
    {
        $proyecto = new Proyecto([
            'nombre' => 'Instalación de puntos de red',
            'facultad' => 'Facultad de Medicina',
            'estadoLista' => Proyecto::LISTA_EDICION,
        ]);
        $proyecto->save(false);

        $presupuesto = new Presupuesto(['idProyecto' => $proyecto->id, 'estado' => $estado]);
        $presupuesto->save(false);

        return $presupuesto;
    }
}
