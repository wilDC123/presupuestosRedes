<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\components\UsuarioActual;
use backend\models\Usuario;
use Codeception\Test\Unit;
use Yii;

/**
 * El nombre del usuario con sesion sale de un solo lugar.
 *
 * Usa la base local; el modulo Yii2 deshace cada test al terminar.
 */
class UsuarioActualTest extends Unit
{
    public function testSinSesionDevuelveNull(): void
    {
        $this->assertNull(UsuarioActual::nombre());
    }

    public function testConSesionDevuelveElNombreDeUsuario(): void
    {
        $usuario = Usuario::find()->andWhere(['activo' => true])->one();
        if ($usuario === null || !Yii::$app->has('user')) {
            $this->markTestSkipped('Sin usuarios activos o sin el componente user.');
        }

        Yii::$app->user->setIdentity($usuario);

        try {
            $this->assertSame((string) $usuario->username, UsuarioActual::nombre());
        } finally {
            Yii::$app->user->setIdentity(null);
        }
    }
}
