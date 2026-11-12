<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\CatalogoSigma;
use Codeception\Test\Unit;
use yii\base\NotSupportedException;

/**
 * dbo.CatalogoSigma es de solo lectura tambien para los metodos estaticos
 * que cambian muchas filas.
 *
 * La condicion no coincide con ningun item (IdSigma es un GUID y el de
 * ceros no existe): aunque la barrera fallara, no se tocaria ningun item.
 * Ademas el modulo Yii2 deshace cada test.
 */
class CatalogoSigmaSoloLecturaTest extends Unit
{
    private const NINGUNO = ['IdSigma' => '00000000-0000-0000-0000-000000000000'];

    public function testUpdateAllEstaBloqueado(): void
    {
        $this->assertBloqueado(static fn () => CatalogoSigma::updateAll(['Clase' => 'X'], self::NINGUNO));
    }

    public function testDeleteAllEstaBloqueado(): void
    {
        $this->assertBloqueado(static fn () => CatalogoSigma::deleteAll(self::NINGUNO));
    }

    public function testUpdateAllCountersEstaBloqueado(): void
    {
        $this->assertBloqueado(static fn () => CatalogoSigma::updateAllCounters(['PrecioReferencial' => 1], self::NINGUNO));
    }

    private function assertBloqueado(callable $escritura): void
    {
        $antes = CatalogoSigma::find()->count();

        try {
            $escritura();
            $this->fail('Se permitió escribir en dbo.CatalogoSigma.');
        } catch (NotSupportedException $e) {
            $this->assertStringContainsString('solo lectura', $e->getMessage());
        }

        $this->assertSame($antes, CatalogoSigma::find()->count());
    }
}
