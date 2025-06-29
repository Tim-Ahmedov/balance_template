<?php

use app\components\AmqpComponent;
use yii\redis\Connection;
use yii\redis\Mutex;
use yii\symfonymailer\Message;
use yii\symfonymailer\Mailer;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/test_db.php';

/**
 * Application configuration shared by all test types
 */
return [
    'id' => 'basic-tests',
    'basePath' => dirname(__DIR__),
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'language' => 'en-US',
    'components' => [
        'db' => $db,
        'mailer' => [
            'class' => Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
            'messageClass' => Message::class
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'urlManager' => [
            'showScriptName' => true,
        ],
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
            // but if you absolutely need it set cookie domain to localhost
            /*
            'csrfCookie' => [
                'domain' => 'localhost',
            ],
            */
        ],
        'redis' => [
            'class' => Connection::class,
            'hostname' => 'redis',
            'port' => 6379,
            'database' => 0,
        ],
        'mutex' => [
            'class' => Mutex::class,
            'redis' => 'redis',
        ],
        'amqp' => [
            'class' => AmqpComponent::class,
            'host' => 'rabbitmq',
            'port' => 5672,
            'user' => 'user',
            'password' => 'password',
            'vhost' => '/',
        ],
    ],
    'params' => $params,
];
