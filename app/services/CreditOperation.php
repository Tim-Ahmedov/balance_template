<?php

namespace app\services;

use app\models\Transaction;
use app\models\User;
use Yii;

class CreditOperation
{
    public function process(OperationData $data)
    {
        \Yii::info([
            'msg' => 'Start credit',
            'data' => (array)$data,
        ], 'balance.operations');
        if ($data->amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        if (Transaction::find()->where(['operation_id' => $data->operation_id, 'type' => OperationType::CREDIT->value])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate credit',
                'operation_id' => $data->operation_id,
                'user_id' => $data->user_id,
            ], 'balance.operations');
            return ['status' => 'duplicate'];
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = User::findForUpdateOrCreate($data->user_id);
            $user->balance += $data->amount;
            if (!$user->save(false)) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Failed to update balance'];
            }
            $tr = new Transaction([
                'user_id' => $data->user_id,
                'type' => OperationType::CREDIT->value,
                'amount' => $data->amount,
                'status' => 'confirmed',
                'operation_id' => $data->operation_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$tr->save(false)) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Failed to save transaction'];
            }
            $transaction->commit();
            \Yii::info([
                'msg' => 'Credit success',
                'operation_id' => $data->operation_id,
                'user_id' => $data->user_id,
                'amount' => $data->amount,
            ], 'balance.operations');
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'funds_credited',
                'user_id' => $data->user_id,
                'amount' => $data->amount,
                'operation' => OperationType::CREDIT->value,
                'operation_id' => $data->operation_id,
                'status' => 'credited',
                'timestamp' => date('c'),
            ], JSON_THROW_ON_ERROR));
            return ['status' => 'success'];
        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error([
                'msg' => 'Credit error',
                'operation_id' => $data->operation_id,
                'user_id' => $data->user_id,
                'error' => $e->getMessage(),
            ], 'balance.operations');
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
