<?php

namespace app\services;

use app\models\Transaction;
use app\models\User;
use Yii;

class TransferOperation
{
    public function process(OperationData $data)
    {
        \Yii::info([
            'msg' => 'Start transfer',
            'data' => (array)$data,
        ], 'balance.operations');
        if ($data->amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        if ($data->user_id === $data->related_user_id) {
            throw new \InvalidArgumentException('Cannot transfer to self');
        }
        if (Transaction::find()->where(['operation_id' => $data->operation_id, 'type' => OperationType::TRANSFER->value])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate transfer',
                'operation_id' => $data->operation_id,
                'user_id' => $data->user_id,
                'related_user_id' => $data->related_user_id,
            ], 'balance.operations');
            return ['status' => 'duplicate'];
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $from = User::findForUpdateOrCreate($data->user_id);
            $to = User::findForUpdateOrCreate($data->related_user_id);
            if ($from->balance < $data->amount) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Insufficient funds'];
            }
            $from->balance -= $data->amount;
            $to->balance += $data->amount;
            if (!$from->save(false) || !$to->save(false)) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Failed to update balances'];
            }
            $tr = new Transaction([
                'user_id' => $data->user_id,
                'related_user_id' => $data->related_user_id,
                'type' => OperationType::TRANSFER->value,
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
                'msg' => 'Transfer success',
                'operation_id' => $data->operation_id,
                'user_id' => $data->user_id,
                'related_user_id' => $data->related_user_id,
                'amount' => $data->amount,
            ], 'balance.operations');
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'funds_transferred',
                'user_id' => $data->user_id,
                'related_user_id' => $data->related_user_id,
                'amount' => $data->amount,
                'operation' => OperationType::TRANSFER->value,
                'operation_id' => $data->operation_id,
                'status' => 'transferred',
                'timestamp' => date('c'),
            ], JSON_THROW_ON_ERROR));
            return ['status' => 'success'];
        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error([
                'msg' => 'Transfer error',
                'operation_id' => $data->operation_id,
                'user_id' => $data->user_id,
                'related_user_id' => $data->related_user_id,
                'error' => $e->getMessage(),
            ], 'balance.operations');
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
