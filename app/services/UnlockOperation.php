<?php
namespace app\services;

use app\models\User;
use app\models\Transaction;
use app\models\LockedFunds;
use Yii;

class UnlockOperation
{
    public function process(array $data)
    {
        \Yii::info([
            'msg' => 'Start unlock',
            'data' => $data,
        ], 'balance.operations');
        if (empty($data['user_id']) || empty($data['amount']) || empty($data['operation_id']) || empty($data['lock_id'])) {
            throw new \InvalidArgumentException('user_id, amount, operation_id, lock_id required');
        }
        $userId = (int)$data['user_id'];
        $amount = (float)$data['amount'];
        $operationId = $data['operation_id'];
        $lockId = (int)$data['lock_id'];
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        if (Transaction::find()->where(['operation_id' => $operationId, 'type' => 'unlock'])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate unlock',
                'operation_id' => $operationId,
                'user_id' => $userId,
                'lock_id' => $lockId,
            ], 'balance.operations');
            return ['status' => 'duplicate'];
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $lock = LockedFunds::findOne(['id' => $lockId, 'user_id' => $userId, 'status' => 'locked']);
            if (!$lock) {
                throw new \Exception('Locked funds not found or already processed');
            }
            if (!empty($data['confirm']) && $data['confirm']) {
                $lock->status = 'charged';
            } else {
                $user = User::find()->where(['id' => $userId])->forUpdate()->one();
                if (!$user) {
                    throw new \Exception('User not found');
                }
                $user->balance += $amount;
                if (!$user->save(false)) {
                    throw new \Exception('Failed to update balance');
                }
                $lock->status = 'unlocked';
            }
            if (!$lock->save(false)) {
                throw new \Exception('Failed to update locked funds');
            }
            $tr = new Transaction([
                'user_id' => $userId,
                'type' => 'unlock',
                'amount' => $amount,
                'status' => 'confirmed',
                'operation_id' => $operationId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$tr->save(false)) {
                throw new \Exception('Failed to save transaction');
            }
            $transaction->commit();
            \Yii::info([
                'msg' => 'Unlock success',
                'operation_id' => $operationId,
                'user_id' => $userId,
                'lock_id' => $lockId,
                'amount' => $amount,
            ], 'balance.operations');
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'funds_unlocked',
                'user_id' => $userId,
                'amount' => $amount,
                'operation' => 'unlock',
                'operation_id' => $operationId,
                'lock_id' => $lockId,
                'status' => !empty($data['confirm']) && $data['confirm'] ? 'charged' : 'unlocked',
                'timestamp' => date('c'),
            ]));
            return ['status' => 'success'];
        } catch (\Throwable $e) {
            if (isset($transaction) && $transaction->isActive) {
                $transaction->rollBack();
            }
            \Yii::error([
                'msg' => 'Unlock error',
                'operation_id' => $data['operation_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'lock_id' => $data['lock_id'] ?? null,
                'error' => $e->getMessage(),
            ], 'balance.operations');
            throw $e;
        }
    }
} 