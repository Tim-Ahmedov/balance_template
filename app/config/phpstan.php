<?php

declare(strict_types=1);

use app\components\AmqpComponent;
use yii\redis\Mutex;
use yii\redis\Connection;

return [
    'components' => [
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn' => 'sqlite::memory:',
        ],
        'user' => [
            'class' => yii\web\User::class,
            'identityClass' => app\models\User::class,
        ],
        'redis' => [
            'class' => Connection::class,
        ],
        'mutex' => [
            'class' => Mutex::class,
            'redis' => 'redis',
        ],
        'amqp' => [
            'class' => AmqpComponent::class,
        ],
    ],
];
