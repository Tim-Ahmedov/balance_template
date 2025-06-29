<?php
namespace app\services;

use app\models\User;
use app\models\Transaction;
use Yii;

class TransferOperation
{
    public function process(array $data)
    {
        \Yii::info([
            'msg' => 'Start transfer',
            'data' => $data,
        ], 'balance.operations');
        if (empty($data['user_id']) || empty($data['related_user_id']) || empty($data['amount']) || empty($data['operation_id'])) {
            throw new \InvalidArgumentException('user_id, related_user_id, amount, operation_id required');
        }
        $fromId = (int)$data['user_id'];
        $toId = (int)$data['related_user_id'];
        $amount = (float)$data['amount'];
        $operationId = $data['operation_id'];
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        if ($fromId === $toId) {
            throw new \InvalidArgumentException('Cannot transfer to self');
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $from = User::find()->where(['id' => $fromId])->forUpdate()->one();
            $to = User::find()->where(['id' => $toId])->forUpdate()->one();
            if (!$from || !$to) {
                throw new \Exception('User(s) not found');
            }
            if ($from->balance < $amount) {
                throw new \Exception('Insufficient funds');
            }
            $from->balance -= $amount;
            $to->balance += $amount;
            if (!$from->save(false) || !$to->save(false)) {
                throw new \Exception('Failed to update balances');
            }
            $tr = new Transaction([
                'user_id' => $fromId,
                'type' => 'transfer',
                'amount' => $amount,
                'status' => 'confirmed',
                'operation_id' => $operationId,
                'related_user_id' => $toId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$tr->save(false)) {
                throw new \Exception('Failed to save transaction');
            }
            $transaction->commit();
            \Yii::info([
                'msg' => 'Transfer success',
                'operation_id' => $operationId,
                'user_id' => $fromId,
                'related_user_id' => $toId,
                'amount' => $amount,
            ], 'balance.operations');
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'balance_changed',
                'user_id' => $fromId,
                'amount' => -$amount,
                'operation' => 'transfer',
                'operation_id' => $operationId,
                'related_user_id' => $toId,
                'status' => 'confirmed',
                'timestamp' => date('c'),
            ]));
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'balance_changed',
                'user_id' => $toId,
                'amount' => $amount,
                'operation' => 'transfer',
                'operation_id' => $operationId,
                'related_user_id' => $fromId,
                'status' => 'confirmed',
                'timestamp' => date('c'),
            ]));
            return ['status' => 'success'];
        } catch (\Throwable $e) {
            if (isset($transaction) && $transaction->isActive) {
                $transaction->rollBack();
            }
            \Yii::error([
                'msg' => 'Transfer error',
                'operation_id' => $data['operation_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'related_user_id' => $data['related_user_id'] ?? null,
                'error' => $e->getMessage(),
            ], 'balance.operations');
            throw $e;
        }
    }
} 