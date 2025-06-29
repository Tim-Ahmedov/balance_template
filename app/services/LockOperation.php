<?php
namespace app\services;

use app\models\User;
use app\models\Transaction;
use app\models\LockedFunds;
use Yii;

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
        if (Transaction::find()->where(['operation_id' => $operationId, 'type' => 'lock'])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate lock',
                'operation_id' => $operationId,
                'user_id' => $userId,
            ], 'balance.operations');
            return ['status' => 'duplicate'];
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = User::find()->where(['id' => $userId])->forUpdate()->one();
            if (!$user) {
                throw new \Exception('User not found');
            }
            if ($user->balance < $amount) {
                throw new \Exception('Insufficient funds');
            }
            $user->balance -= $amount;
            if (!$user->save(false)) {
                throw new \Exception('Failed to update balance');
            }
            $lock = new LockedFunds([
                'user_id' => $userId,
                'amount' => $amount,
                'status' => 'locked',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$lock->save(false)) {
                throw new \Exception('Failed to save locked funds');
            }
            $tr = new Transaction([
                'user_id' => $userId,
                'type' => 'lock',
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
                'msg' => 'Lock success',
                'operation_id' => $operationId,
                'user_id' => $userId,
                'amount' => $amount,
            ], 'balance.operations');
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'funds_locked',
                'user_id' => $userId,
                'amount' => $amount,
                'operation' => 'lock',
                'operation_id' => $operationId,
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
            throw $e;
        }
    }
} 