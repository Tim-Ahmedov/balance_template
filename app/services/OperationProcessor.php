<?php
namespace app\services;

use app\models\User;
use app\models\Transaction;
use app\models\LockedFunds;
use Yii;
use yii\db\Exception;
use app\services\OperationType;

class OperationProcessor
{
    public function process(array $data)
    {
        if (empty($data['operation'])) {
            throw new \InvalidArgumentException('Operation type required');
        }
        switch ($data['operation']) {
            case OperationType::DEBIT->value:
                return (new DebitOperation())->process($data);
            case OperationType::CREDIT->value:
                return (new CreditOperation())->process($data);
            case OperationType::TRANSFER->value:
                return (new TransferOperation())->process($data);
            case OperationType::LOCK->value:
                return (new LockOperation())->process($data);
            case OperationType::UNLOCK->value:
                return (new UnlockOperation())->process($data);
            default:
                throw new \InvalidArgumentException('Unknown operation');
        }
    }
} 