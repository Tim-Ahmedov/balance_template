<?php

namespace app\services;

use app\models\Transaction;
use app\models\User;
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
        if (Transaction::find()->where(['operation_id' => $operationId, 'type' => OperationType::TRANSFER->value])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate transfer',
                'operation_id' => $operationId,
                'user_id' => $fromId,
                'related_user_id' => $toId,
            ], 'balance.operations');
            return ['status' => 'duplicate'];
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $from = User::findForUpdateOrCreate($fromId);
            $to = User::findForUpdateOrCreate($toId);
            if ($from->balance < $amount) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Insufficient funds'];
            }
            $from->balance -= $amount;
            $to->balance += $amount;
            if (!$from->save(false) || !$to->save(false)) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Failed to update balances'];
            }
            $tr = new Transaction([
                'user_id' => $fromId,
                'related_user_id' => $toId,
                'type' => OperationType::TRANSFER->value,
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
                'msg' => 'Transfer success',
                'operation_id' => $operationId,
                'user_id' => $fromId,
                'related_user_id' => $toId,
                'amount' => $amount,
            ], 'balance.operations');
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'funds_transferred',
                'user_id' => $fromId,
                'related_user_id' => $toId,
                'amount' => $amount,
                'operation' => OperationType::TRANSFER->value,
                'operation_id' => $operationId,
                'status' => 'transferred',
                'timestamp' => date('c'),
            ], JSON_THROW_ON_ERROR));
            return ['status' => 'success'];
        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error([
                'msg' => 'Transfer error',
                'operation_id' => $data['operation_id'],
                'user_id' => $data['user_id'],
                'related_user_id' => $data['related_user_id'],
                'error' => $e->getMessage(),
            ], 'balance.operations');
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
