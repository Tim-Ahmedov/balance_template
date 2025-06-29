<?php
namespace app\services;

use app\models\User;
use app\models\Transaction;
use app\models\LockedFunds;
use Yii;
use app\services\OperationType;

class LockOperation
{
    public function process(array $data)
    {
        \Yii::info([
            'msg' => 'Start lock',
            'data' => $data,
        ], 'balance.operations');
        if (empty($data['user_id']) || empty($data['amount']) || empty($data['operation_id'])) {
            throw new \InvalidArgumentException('user_id, amount, operation_id required');
        }
        $userId = (int)$data['user_id'];
        $amount = (float)$data['amount'];
        $operationId = $data['operation_id'];
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        if (Transaction::find()->where(['operation_id' => $operationId, 'type' => OperationType::LOCK->value])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate lock',
                'operation_id' => $operationId,
                'user_id' => $userId,
            ], 'balance.operations');
            return ['status' => 'duplicate'];
        }
        $lockId = $data['lock_id'] ?? null;
        if ($lockId && LockedFunds::find()->where(['lock_id' => $lockId])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate lock by lock_id',
                'lock_id' => $lockId,
                'user_id' => $userId,
            ], 'balance.operations');
            return ['status' => 'duplicate'];
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = User::findForUpdateOrCreate($userId);
            if (!$user) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'User not found'];
            }
            if ($user->balance < $amount) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Insufficient funds'];
            }
            $user->balance -= $amount;
            if (!$user->save(false)) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Failed to update balance'];
            }
            $lock = new LockedFunds([
                'user_id' => $userId,
                'amount' => $amount,
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
                'user_id' => $userId,
                'type' => OperationType::LOCK->value,
                'amount' => $amount,
                'status' => 'confirmed',
                'operation_id' => $operationId,
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
                'operation_id' => $operationId,
                'user_id' => $userId,
                'amount' => $amount,
            ], 'balance.operations');
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'funds_locked',
                'user_id' => $userId,
                'amount' => $amount,
                'operation' => OperationType::LOCK->value,
                'operation_id' => $operationId,
                'lock_id' => $lockId,
                'status' => 'locked',
                'timestamp' => date('c'),
            ]));
            return ['status' => 'success'];
        } catch (\Throwable $e) {
            if (isset($transaction) && $transaction->isActive) {
                $transaction->rollBack();
            }
            \Yii::error([
                'msg' => 'Lock error',
                'operation_id' => $data['operation_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'error' => $e->getMessage(),
            ], 'balance.operations');
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
} 