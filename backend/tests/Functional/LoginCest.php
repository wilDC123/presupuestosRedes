<?php

declare(strict_types=1);

namespace backend\tests\Functional;

use backend\models\Usuario;
use backend\services\UsuarioService;
use backend\tests\Support\FunctionalTester;

/**
 * Ingreso al backend con los usuarios temporales de redes.Usuario (desde el
 * 2026-10-25; antes esta prueba era la de la plantilla de Yii, con dbo.user).
 */
final class LoginCest
{
    private const USUARIO = 'prueba.login';
    private const CLAVE = 'clave-de-prueba';

    public function _before(FunctionalTester $I): void
    {
        Usuario::deleteAll(['usuario' => self::USUARIO]);
        $error = (new UsuarioService())->crear(self::USUARIO, self::CLAVE);
        $I->assertNull($error);
    }

    public function _after(FunctionalTester $I): void
    {
        Usuario::deleteAll(['usuario' => self::USUARIO]);
    }

    public function ingresaConLaClaveCorrecta(FunctionalTester $I): void
    {
        $this->ingresar($I, self::CLAVE);

        $I->see(self::USUARIO, '.rd-usuario-nombre');
        $I->seeElement('button.rd-salir');
    }

    public function rechazaUnaClaveIncorrecta(FunctionalTester $I): void
    {
        $this->ingresar($I, 'otra-clave-cualquiera');

        $I->dontSeeElement('button.rd-salir');
        $I->seeElement('#login-form');
    }

    public function sinSesionLlevaAlIngreso(FunctionalTester $I): void
    {
        $I->amOnRoute('/proyecto/index');

        $I->seeElement('#login-form');
    }

    private function ingresar(FunctionalTester $I, string $clave): void
    {
        $I->amOnRoute('/site/login');
        $I->fillField('Your Username', self::USUARIO);
        $I->fillField('Your Password', $clave);
        $I->click('login-button');
    }
}
