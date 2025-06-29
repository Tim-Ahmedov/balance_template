<?php

namespace app\commands;

use Yii;
use yii\console\Controller;

class QueueTestController extends Controller
{
    public function actionSend($message = 'test message')
    {
        Yii::$app->amqpQueue->send($message);
        $this->stdout("[x] Sent: $message\n");
    }

    public function actionReceive()
    {
        $msg = Yii::$app->amqpQueue->receive(2000);
        if ($msg) {
            $this->stdout("[x] Received: $msg\n");
        } else {
            $this->stdout("[ ] No message received\n");
        }
    }

    public function actionSendDebit($userId, $amount, $operationId)
    {
        $msg = [
            'operation' => 'debit',
            'user_id' => $userId,
            'amount' => $amount,
            'operation_id' => $operationId,
        ];
        Yii::$app->amqpQueue->send(json_encode($msg));
        $this->stdout("[x] Sent debit: " . json_encode($msg) . "\n");
    }

    public function actionSendCredit($userId, $amount, $operationId)
    {
        $msg = [
            'operation' => 'credit',
            'user_id' => $userId,
            'amount' => $amount,
            'operation_id' => $operationId,
        ];
        Yii::$app->amqpQueue->send(json_encode($msg));
        $this->stdout("[x] Sent credit: " . json_encode($msg) . "\n");
    }

    public function actionSendLock($userId, $amount, $operationId)
    {
        $msg = [
            'operation' => 'lock',
            'user_id' => $userId,
            'amount' => $amount,
            'operation_id' => $operationId,
        ];
        Yii::$app->amqpQueue->send(json_encode($msg));
        $this->stdout("[x] Sent lock: " . json_encode($msg) . "\n");
    }

    public function actionSendUnlock($userId, $amount, $lockId, $operationId, $confirm = 0)
    {
        $msg = [
            'operation' => 'unlock',
            'user_id' => $userId,
            'amount' => $amount,
            'lock_id' => $lockId,
            'operation_id' => $operationId,
            'confirm' => (bool)$confirm,
        ];
        Yii::$app->amqpQueue->send(json_encode($msg));
        $this->stdout("[x] Sent unlock: " . json_encode($msg) . "\n");
    }

    public function actionSendTransfer($fromId, $toId, $amount, $operationId)
    {
        $msg = [
            'operation' => 'transfer',
            'user_id' => $fromId,
            'related_user_id' => $toId,
            'amount' => $amount,
            'operation_id' => $operationId,
        ];
        Yii::$app->amqpQueue->send(json_encode($msg));
        $this->stdout("[x] Sent transfer: " . json_encode($msg) . "\n");
    }
}
