<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\LoginForm;
use backend\models\Usuario;
use backend\services\UsuarioService;
use Codeception\Test\Unit;
use Yii;

/**
 * Usuarios temporales (redes.Usuario): se crean con contraseña de al menos
 * 8 caracteres, sin nombres repetidos, y uno inactivo no puede ingresar.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class UsuarioTest extends Unit
{
    private UsuarioService $service;

    protected function _before(): void
    {
        $this->service = new UsuarioService();
    }

    public function testCreaElUsuarioConLaClaveCifrada(): void
    {
        $this->assertNull($this->service->crear('prueba.test', 'clave-segura'));

        $usuario = Usuario::porUsuario('prueba.test');
        $this->assertNotNull($usuario);
        $this->assertTrue((bool) $usuario->activo);
        $this->assertNotSame('clave-segura', $usuario->claveHash);
        $this->assertTrue($usuario->claveCorrecta('clave-segura'));
        $this->assertFalse($usuario->claveCorrecta('otra-clave'));
        $this->assertSame('prueba.test', $usuario->username);
    }

    public function testRechazaClaveCortaNombreRepetidoYEspacios(): void
    {
        $this->assertNotNull($this->service->crear('prueba.corta', '1234567'));
        $this->assertNull(Usuario::porUsuario('prueba.corta'));

        $this->assertNull($this->service->crear('prueba.repetido', 'clave-segura'));
        $this->assertNotNull($this->service->crear('prueba.repetido', 'otra-clave-segura'));

        $this->assertNotNull($this->service->crear('con espacio', 'clave-segura'));
    }

    public function testIngresaConLaClaveCorrectaYSoloSiEstaActivo(): void
    {
        $this->service->crear('prueba.login', 'clave-segura');

        $this->assertFalse($this->formulario('prueba.login', 'mal-clave')->validate());
        $this->assertFalse($this->formulario('no.existe', 'clave-segura')->validate());
        $this->assertTrue($this->formulario('prueba.login', 'clave-segura')->validate());

        $this->assertNull($this->service->cambiarActivo('prueba.login', false));
        $this->assertFalse($this->formulario('prueba.login', 'clave-segura')->validate());
        $this->assertNull(Usuario::findIdentity(Usuario::porUsuario('prueba.login')->id));

        $this->service->cambiarActivo('prueba.login', true);
        $this->assertTrue($this->formulario('prueba.login', 'clave-segura')->validate());
    }

    public function testCambiarLaClaveInvalidaLaAnteriorYLasSesionesRecordadas(): void
    {
        $this->service->crear('prueba.clave', 'clave-vieja-1');
        $authKeyVieja = Usuario::porUsuario('prueba.clave')->authKey;

        $this->assertNull($this->service->cambiarClave('prueba.clave', 'clave-nueva-1'));

        $usuario = Usuario::porUsuario('prueba.clave');
        $this->assertFalse($usuario->claveCorrecta('clave-vieja-1'));
        $this->assertTrue($usuario->claveCorrecta('clave-nueva-1'));
        $this->assertFalse($usuario->validateAuthKey($authKeyVieja));

        $this->assertNotNull($this->service->cambiarClave('no.existe', 'clave-segura'));
    }

    private function formulario(string $usuario, string $clave): LoginForm
    {
        return new LoginForm(['username' => $usuario, 'password' => $clave]);
    }
}
