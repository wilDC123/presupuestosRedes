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
    // TODA la aplicacion exige sesion iniciada, salvo el login y la pagina de
    // error. Esta definido aqui (y no en cada controlador) para que un
    // controlador nuevo quede protegido sin tener que acordarse. El filtro
    // AccessControl redirige a site/login a quien no inicio sesion.
    'as access' => [
        'class' => \yii\filters\AccessControl::class,
        'except' => ['site/login', 'site/error', 'debug/*'],
        'rules' => [
            ['allow' => true, 'roles' => ['@']],
        ],
    ],
    // Contenedor de dependencias: aqui se decide que clase concreta se usa
    // cuando alguien pide una interface. Para que las facultades vengan de un
    // servicio web en vez del JSON, basta con cambiar la clase de esta
    // definicion.
    'container' => [
        'definitions' => [
            \backend\services\contracts\ParametrosProyectoProvider::class => [
                'class' => \backend\services\JsonParametrosProyectoProvider::class,
                '__construct()' => ['@backend/config/parametros-proyecto.json'],
            ],
            // Edificios, bloques, niveles y ambientes del area de intervencion:
            // la base UsfxGEO (conexion "dbGeo"). Si Desarrollo entrega vistas
            // o un servicio web, se cambia solo esta clase.
            \backend\services\contracts\InfraestructuraProvider::class => [
                'class' => \backend\services\UsfxGeoInfraestructuraProvider::class,
                '__construct()' => ['dbGeo'],
            ],
        ],
    ],
    'components' => [
        // Agrega "?v=<fecha del archivo>" a cada CSS y JS: cuando uno cambia,
        // el navegador baja la version nueva en vez de usar la guardada.
        'assetManager' => [
            'appendTimestamp' => true,
        ],
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
            // Usuarios temporales en redes.Usuario (php yii usuario/crear). Al
            // integrar la autenticacion institucional, aqui va su clase.
            'identityClass' => \backend\models\Usuario::class,
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
