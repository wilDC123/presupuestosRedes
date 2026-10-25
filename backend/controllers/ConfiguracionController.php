<?php

declare(strict_types=1);

namespace backend\controllers;

use yii\web\Controller;

/**
 * Pagina "Configuracion" (capa de Presentacion): reune en tarjetas los
 * enlaces a las pantallas que se tocan poco (catalogos base, codificacion y
 * textos de los impresos), para que no ocupen la barra lateral. No guarda
 * nada: cada tarjeta lleva a la pantalla que ya existia.
 */
class ConfiguracionController extends Controller
{
    public function actionIndex(): string
    {
        return $this->render('index');
    }
}
