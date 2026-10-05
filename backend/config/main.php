<?php

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-backend',
    'name' => 'DTIC Redes',
    // Idioma de los mensajes automaticos de Yii (validaciones, paginacion, etc.).
    'language' => 'es',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'backend\controllers',
    'bootstrap' => ['log'],
    'modules' => [],
    // Contenedor de dependencias: aqui se decide que clase concreta se usa
    // cuando alguien pide una interface. Para que facultades, edificios,
    // bloques, tipos y proyectistas vengan de un servicio web en vez del
    // JSON, basta con cambiar la clase de esta definicion.
    'container' => [
        'definitions' => [
            \backend\services\contracts\ParametrosProyectoProvider::class => [
                'class' => \backend\services\JsonParametrosProyectoProvider::class,
                '__construct()' => ['@backend/config/parametros-proyecto.json'],
            ],
        ],
    ],
    'components' => [
        // Con 'language' => 'es' Yii formatearia los numeros como 1.234,56. Se
        // fija el formato 1,234.56 que usan los presupuestos de la DTIC.
        'formatter' => [
            'thousandSeparator' => ',',
            'decimalSeparator' => '.',
        ],
        'request' => [
            'csrfParam' => '_csrf-backend',
        ],
        'user' => [
            'identityClass' => \common\models\User::class,
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-backend', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the backend
            'name' => 'advanced-backend',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        /*
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
        */
    ],
    'params' => $params,
];
