<?php

namespace app\services;

use app\models\LockedFunds;
use app\models\Transaction;
use app\models\User;
use Yii;

class LockOperation
{
    public function process(OperationData $data)
    {
        \Yii::info([
            'msg' => 'Start lock',
            'data' => (array)$data,
        ], 'balance.operations');
        if ($data->amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        if (Transaction::find()->where(['operation_id' => $data->operation_id, 'type' => OperationType::LOCK->value])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate lock',
                'operation_id' => $data->operation_id,
                'user_id' => $data->user_id,
            ], 'balance.operations');
            return ['status' => 'duplicate'];
        }
        $lockId = $data->lock_id;
        if ($lockId && LockedFunds::find()->where(['lock_id' => $lockId])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate lock by lock_id',
                'lock_id' => $lockId,
                'user_id' => $data->user_id,
            ], 'balance.operations');
            return ['status' => 'duplicate'];
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = User::findForUpdateOrCreate($data->user_id);
            if ($user->balance < $data->amount) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Insufficient funds'];
            }
            $user->balance -= $data->amount;
            if (!$user->save(false)) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Failed to update balance'];
            }
            $lock = new LockedFunds([
                'user_id' => $data->user_id,
                'amount' => $data->amount,
                'status' => 'locked',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'lock_id' => $lockId,
            ]);
            if (!$lock->save(false)) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Failed to save locked funds'];
            }
            $tr = new Transaction([
                'user_id' => $data->user_id,
                'type' => OperationType::LOCK->value,
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
                'msg' => 'Lock success',
                'operation_id' => $data->operation_id,
                'user_id' => $data->user_id,
                'amount' => $data->amount,
            ], 'balance.operations');
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'funds_locked',
                'user_id' => $data->user_id,
                'amount' => $data->amount,
                'operation' => OperationType::LOCK->value,
                'operation_id' => $data->operation_id,
                'lock_id' => $lockId,
                'status' => 'locked',
                'timestamp' => date('c'),
            ], JSON_THROW_ON_ERROR));
            return ['status' => 'success'];
        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error([
                'msg' => 'Lock error',
                'operation_id' => $data->operation_id,
                'user_id' => $data->user_id,
                'error' => $e->getMessage(),
            ], 'balance.operations');
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
