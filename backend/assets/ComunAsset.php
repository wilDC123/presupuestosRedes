<?php

declare(strict_types=1);

namespace backend\assets;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;

/**
 * js/comun.js: funciones de JS que comparten varios formularios
 * (window.Redes). La vista que registra un JS que las usa lo declara asi:
 *
 *     $this->registerJsFile('@web/js/lista-items.js', ['depends' => [ComunAsset::class]]);
 *
 * Con eso Yii incluye jQuery, despues comun.js y despues el JS de la pagina,
 * en ese orden. Solo se carga en las paginas que lo piden.
 */
class ComunAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $js = [
        'js/comun.js',
    ];
    public $depends = [
        JqueryAsset::class,
    ];
}
