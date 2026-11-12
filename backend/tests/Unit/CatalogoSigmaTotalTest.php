<?php

declare(strict_types=1);

namespace backend\tests\Unit;

use backend\models\CatalogoSigma;
use Codeception\Test\Unit;
use Yii;

/**
 * El total de items vigentes de SIGMA (inicio y subcatalogo) sale de un solo
 * metodo.
 */
class CatalogoSigmaTotalTest extends Unit
{
    public function testCuentaSoloLosVigentes(): void
    {
        $esperado = (int) Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM dbo.CatalogoSigma WHERE CodigoEstado = :estado',
            [':estado' => CatalogoSigma::ESTADO_VIGENTE]
        )->queryScalar();

        $this->assertSame($esperado, CatalogoSigma::totalVigentes());
    }
}
