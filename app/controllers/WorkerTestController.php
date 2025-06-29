<?php

namespace app\controllers;

use app\models\User;
use Yii;
use yii\base\DynamicModel;
use yii\data\ActiveDataProvider;
use yii\web\Controller;

class WorkerTestController extends Controller
{
    public function actionIndex(): string
    {
        $model = new DynamicModel([
            'operation', 'user_id', 'amount', 'operation_id', 'to_user_id', 'lock_id', 'confirm'
        ]);
        $model->addRule(['operation', 'user_id', 'amount', 'operation_id'], 'required');
        $model->addRule(['to_user_id', 'lock_id', 'confirm'], 'safe');

        $result = null;
        if ($model->load(Yii::$app->request->post())) {
            $msg = [
                'operation' => $model->operation,
                'user_id' => $model->user_id,
                'amount' => $model->amount,
                'operation_id' => $model->operation_id,
            ];
            if ($model->operation === 'transfer') {
                $msg['related_user_id'] = $model->to_user_id;
            }
            if ($model->operation === 'unlock') {
                $msg['lock_id'] = $model->lock_id;
                $msg['confirm'] = (bool)$model->confirm;
            }
            try {
                Yii::$app->amqpQueue->send(json_encode($msg));
            } catch (\Exception $e) {
                $result = 'Сообщение не отправлено: ' . $e->getMessage();
            }

            $result = 'Сообщение отправлено: ' . json_encode($msg);
        }
        return $this->render('index', [
            'model' => $model,
            'result' => $result,
        ]);
    }

    public function actionBalances()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => User::find(),
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);
        return $this->render('balances', [
            'dataProvider' => $dataProvider,
        ]);
    }
}
