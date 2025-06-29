<?php
namespace app\services;

use app\models\User;
use app\models\Transaction;
use Yii;

class CreditOperation
{
    public function process(array $data)
    {
        \Yii::info([
            'msg' => 'Start credit',
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
        if (Transaction::find()->where(['operation_id' => $operationId, 'type' => 'credit'])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate credit',
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
            $user->balance += $amount;
            if (!$user->save(false)) {
                throw new \Exception('Failed to update balance');
            }
            $tr = new Transaction([
                'user_id' => $userId,
                'type' => 'credit',
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
                'msg' => 'Credit success',
                'operation_id' => $operationId,
                'user_id' => $userId,
                'amount' => $amount,
            ], 'balance.operations');
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'balance_changed',
                'user_id' => $userId,
                'amount' => $amount,
                'operation' => 'credit',
                'operation_id' => $operationId,
                'status' => 'confirmed',
                'timestamp' => date('c'),
            ]));
            return ['status' => 'success'];
        } catch (\Throwable $e) {
            if (isset($transaction) && $transaction->isActive) {
                $transaction->rollBack();
            }
            \Yii::error([
                'msg' => 'Credit error',
                'operation_id' => $data['operation_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'error' => $e->getMessage(),
            ], 'balance.operations');
            throw $e;
        }
    }
} 