<?php

/**
 * Configuracion LOCAL de produccion (no se sube a git: `php init --env=Production`
 * la copia a common/config/main-local.php). Completar con los datos del servidor
 * institucional; ver docs/guia-instalacion-dtic.md.
 */
return [
    'container' => [
        'singletons' => [
            \yii\mail\MailerInterface::class => [
                'class' => \yii\symfonymailer\Mailer::class,
                'viewPath' => '@common/mail',
            ],
        ],
    ],
    'components' => [
        'db' => [
            'class' => \yii\db\Connection::class,
            // Servidor institucional de SQL Server y base POAPresupuestos.
            // Encrypt / TrustServerCertificate: el driver ODBC 18 cifra la
            // conexion por defecto; con SQL Server 2012 puede hacer falta
            // ajustarlo (decision del equipo de seguridad de DTIC).
            'dsn' => 'sqlsrv:Server=172.16.1.250,1433;Database=POAPresupuestos;Encrypt=yes;TrustServerCertificate=yes',
            // Usuario de SQL Server propio de la aplicacion (desde Linux no hay
            // autenticacion de Windows).
            'username' => '',
            'password' => '',
            'charset' => 'UTF-8',
        ],
        'mailer' => \yii\mail\MailerInterface::class,
    ],
];
