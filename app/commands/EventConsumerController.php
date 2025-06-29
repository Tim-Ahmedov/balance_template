<?php
namespace app\commands;

use Yii;
use yii\console\Controller;

class EventConsumerController extends Controller
{
    public function actionListen($timeout = 2)
    {
        $queue = Yii::$app->amqpQueue;
        $context = (new \Enqueue\AmqpLib\AmqpConnectionFactory([
            'host' => $queue->host,
            'port' => $queue->port,
            'user' => $queue->user,
            'pass' => $queue->pass,
            'vhost' => $queue->vhost,
        ]))->createContext();
        $eventQueue = $context->createQueue($queue->eventQueueName);
        $context->declareQueue($eventQueue);
        $consumer = $context->createConsumer($eventQueue);
        $this->stdout("[EventConsumer] Listening for events...\n");
        while (true) {
            $message = $consumer->receive($timeout * 1000);
            if ($message) {
                $this->stdout("[EventConsumer] Received event: ".$message->getBody()."\n");
                $consumer->acknowledge($message);
            }
        }
    }
} 