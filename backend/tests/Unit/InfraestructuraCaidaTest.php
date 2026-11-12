<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\services\contracts\InfraestructuraProvider;
use backend\services\ProyectoService;
use Codeception\Test\Unit;
use yii\db\Exception as DbException;

/**
 * UsfxGEO (la base de infraestructura de Desarrollo) no responde: el
 * formulario del proyecto y sus busquedas siguen funcionando, con el aviso, en
 * vez de un error del servidor.
 */
class InfraestructuraCaidaTest extends Unit
{
    private ProyectoService $service;

    protected function _before(): void
    {
        // Como la conexion "dbGeo" cuando el servidor no contesta: cada consulta lanza yii\db\Exception.
        $caida = new class implements InfraestructuraProvider {
            public function edificios(): array { throw new DbException('UsfxGEO no responde'); }
            public function bloques(string $idEdificio): array { throw new DbException('UsfxGEO no responde'); }
            public function niveles(int $idBloqueArea): array { throw new DbException('UsfxGEO no responde'); }
            public function ambientes(int $idBloqueArea, string $numeroNivel): array { throw new DbException('UsfxGEO no responde'); }
            public function buscarEdificios(string $texto, int $limite = 10): array { throw new DbException('UsfxGEO no responde'); }
            public function buscarAmbientes(string $texto, int $limite = 30): array { throw new DbException('UsfxGEO no responde'); }
            public function ruta(string $idEdificio, ?int $idBloqueArea, ?string $numeroNivel, ?string $idEspacio): ?array { throw new DbException('UsfxGEO no responde'); }
        };
        $this->service = new ProyectoService(null, $caida);
    }

    public function testElFormularioAbreSinEdificios(): void
    {
        $this->assertNull($this->service->edificiosParaFormulario());
    }

    public function testElBuscadorDevuelveElAviso(): void
    {
        $resultado = $this->service->buscarAreas('ciencias y tecnologia');

        $this->assertSame([], $resultado['edificios']);
        $this->assertSame([], $resultado['ambientes']);
        $this->assertSame(ProyectoService::AVISO_INFRAESTRUCTURA, $resultado['error']);
    }

    public function testElPasoAPasoDevuelveNull(): void
    {
        $this->assertNull($this->service->consultarInfraestructura(static fn (InfraestructuraProvider $geo): array => $geo->bloques('001')));
    }
}
