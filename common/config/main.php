<?php

declare(strict_types=1);

return [
    'bootstrap' => [
        \common\bootstrap\MailerBootstrap::class,
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    // Hora de Bolivia para todo PHP (date(), fechas de emision, nombres de
    // archivo). Sin esto se usa la del servidor, que en Linux suele ser UTC:
    // un formulario emitido despues de las 20:00 saldria con fecha del dia
    // siguiente.
    'timeZone' => 'America/La_Paz',
    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'formatter' => [
            // Las fechas con hora de la base las pone GETDATE() del SQL Server
            // de la universidad, que ya esta en hora de Bolivia: se muestran
            // tal cual, sin convertirlas desde UTC.
            'defaultTimeZone' => 'America/La_Paz',
        ],
    ],
];
