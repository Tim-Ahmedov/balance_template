<?php

use app\controllers\WorkerTestController;
use yii\debug\Module;
use yii\caching\FileCache;
use yii\log\FileTarget;
use yii\symfonymailer\Mailer;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'defaultRoute' => 'transactions/index',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'kybRkYWM03PHMnO0ek5PBFf0BcoByZnh',
        ],
        'cache' => [
            'class' => FileCache::class,
        ],
        'mailer' => [
            'class' => Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
                [
                    'class' => FileTarget::class,
                    'levels' => ['info', 'warning', 'error'],
                    'categories' => ['balance.operations'],
                    'logFile' => '@app/runtime/logs/operations.log',
                    'logVars' => [],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
        'amqpQueue' => [
            'class' => app\components\AmqpQueue::class,
            'host' => 'rabbitmq',
            'port' => 5672,
            'user' => 'user',
            'pass' => 'password',
            'vhost' => '/',
            'queueName' => 'balance',
        ],
    ],
    'controllerMap' => [
        'worker-test' => WorkerTestController::class,
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['127.0.0.1', '*'],
    ];
}

return $config;
