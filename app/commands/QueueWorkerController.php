<?php
namespace app\commands;

use Yii;
use yii\console\Controller;
use app\services\OperationProcessor;

class QueueWorkerController extends Controller
{
    public function actionListen($timeout = 2)
    {
        $processor = new OperationProcessor();
        $this->stdout("[Worker] Listening for messages...\n");
        while (true) {
            $msg = Yii::$app->amqpQueue->receive($timeout * 1000);
            if ($msg) {
                $this->stdout("[Worker] Received: $msg\n");
                try {
                    $data = json_decode($msg, true);
                    $result = $processor->process($data);
                    $this->stdout("[Worker] Processed: ".json_encode($result)."\n");
                } catch (\Throwable $e) {
                    $this->stderr("[Worker] Error: ".$e->getMessage()."\n");
                }
            }
        }
    }
} 