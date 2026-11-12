<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\LoginForm;
use backend\models\Presupuesto;
use backend\services\UsuarioService;
use Codeception\Test\Unit;

/**
 * Seguridad del ingreso y del presupuesto:
 *  - el ingreso limita los intentos fallidos (por usuario + IP y por IP) y
 *    "Recordarme" viene desmarcado,
 *  - los atributos del presupuesto no se asignan desde un formulario.
 *
 * El conteo de intentos va en el cache (FileCache), que NO se deshace al
 * terminar y es el mismo del sistema local: por eso cada test usa una IP
 * propia, inventada y al azar (sus conteos no se cruzan con nada y vencen
 * solos), en vez de vaciar el cache.
 */
class SeguridadIngresoTest extends Unit
{
    private string $ip;

    protected function _before(): void
    {
        $this->ip = self::ipDePrueba();
        (new UsuarioService())->crear('prueba.limite', 'clave-segura');
    }

    /** Una IPv6 del rango de documentacion (RFC 3849): nunca es una IP real. */
    private static function ipDePrueba(): string
    {
        return '2001:db8::' . implode(':', str_split(bin2hex(random_bytes(6)), 4));
    }

    public function testRecordarmeVieneDesmarcado(): void
    {
        $this->assertFalse((new LoginForm())->rememberMe);
    }

    public function testTrasCincoFallosSeBloqueaAunConLaClaveCorrecta(): void
    {
        for ($i = 0; $i < LoginForm::MAX_INTENTOS; $i++) {
            $this->assertFalse($this->formulario('prueba.limite', 'mal-clave')->validate());
        }

        $correcta = $this->formulario('prueba.limite', 'clave-segura');
        $this->assertFalse($correcta->validate(), 'Bloqueado: ni con la clave correcta.');
        $this->assertStringContainsString('Demasiados intentos', $correcta->getFirstError('password'));
    }

    public function testElBloqueoEsDeEsaIpNoDelUsuarioEnTodasPartes(): void
    {
        for ($i = 0; $i < LoginForm::MAX_INTENTOS; $i++) {
            $this->formulario('prueba.limite', 'mal-clave')->validate();
        }

        // El encargado, desde su propia PC, sigue entrando.
        $otraPc = $this->formulario('prueba.limite', 'clave-segura', self::ipDePrueba());
        $this->assertTrue($otraPc->validate(), json_encode($otraPc->errors));
    }

    public function testUnIngresoCorrectoBorraElConteo(): void
    {
        for ($i = 0; $i < LoginForm::MAX_INTENTOS - 1; $i++) {
            $this->formulario('prueba.limite', 'mal-clave')->validate();
        }
        $this->assertTrue($this->formulario('prueba.limite', 'clave-segura')->validate());

        // Empieza de cero: un fallo mas no bloquea.
        $this->formulario('prueba.limite', 'mal-clave')->validate();
        $this->assertTrue($this->formulario('prueba.limite', 'clave-segura')->validate());
    }

    public function testMuchosUsuariosDesdeLaMismaIpTambienSeBloquean(): void
    {
        for ($i = 0; $i < LoginForm::MAX_INTENTOS_IP; $i++) {
            $this->formulario('no.existe.' . $i, 'mal-clave')->validate();
        }

        $this->assertFalse($this->formulario('prueba.limite', 'clave-segura')->validate());
    }

    public function testLosAtributosDelPresupuestoNoLleganDeUnFormulario(): void
    {
        $presupuesto = new Presupuesto(['estado' => Presupuesto::ESTADO_EN_PROCESO]);

        $presupuesto->load(['Presupuesto' => [
            'estado' => Presupuesto::ESTADO_APROBADO,
            'numeroPresupuesto' => 'RD-PI-PR-999',
            'idProyecto' => '1',
            'oficioDtic' => '1/2026',
        ]]);

        $this->assertSame([], $presupuesto->safeAttributes());
        $this->assertSame(Presupuesto::ESTADO_EN_PROCESO, $presupuesto->estado);
        $this->assertNull($presupuesto->numeroPresupuesto);
        // Pero se siguen validando.
        $presupuesto->estado = 'inventado';
        $this->assertFalse($presupuesto->validate(['estado']));
    }

    private function formulario(string $usuario, string $clave, ?string $ip = null): LoginForm
    {
        $formulario = new LoginForm(['username' => $usuario, 'password' => $clave]);
        $formulario->ip = $ip ?? $this->ip;

        return $formulario;
    }
}
