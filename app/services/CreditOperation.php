<?php
namespace app\services;

use app\models\User;
use app\models\Transaction;
use Yii;
use app\services\OperationType;

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
        if (Transaction::find()->where(['operation_id' => $operationId, 'type' => OperationType::CREDIT->value])->exists()) {
            \Yii::info([
                'msg' => 'Duplicate credit',
                'operation_id' => $operationId,
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
            $user->balance += $amount;
            if (!$user->save(false)) {
                $transaction->rollBack();
                return ['status' => 'error', 'message' => 'Failed to update balance'];
            }
            $tr = new Transaction([
                'user_id' => $userId,
                'type' => OperationType::CREDIT->value,
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
                'msg' => 'Credit success',
                'operation_id' => $operationId,
                'user_id' => $userId,
                'amount' => $amount,
            ], 'balance.operations');
            Yii::$app->amqpQueue->sendEvent(json_encode([
                'event' => 'funds_credited',
                'user_id' => $userId,
                'amount' => $amount,
                'operation' => OperationType::CREDIT->value,
                'operation_id' => $operationId,
                'status' => 'credited',
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
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
} 