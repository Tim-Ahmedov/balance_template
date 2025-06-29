<?php
namespace app\services;

use app\models\User;
use app\models\Transaction;
use app\models\LockedFunds;
use Yii;
use yii\db\Exception;

class OperationProcessor
{
    public function process(array $data)
    {
        if (empty($data['operation'])) {
            throw new \InvalidArgumentException('Operation type required');
        }
        switch ($data['operation']) {
            case 'debit':
                return (new DebitOperation())->process($data);
            case 'credit':
                return (new CreditOperation())->process($data);
            case 'transfer':
                return (new TransferOperation())->process($data);
            case 'lock':
                return (new LockOperation())->process($data);
            case 'unlock':
                return (new UnlockOperation())->process($data);
            default:
                throw new \InvalidArgumentException('Unknown operation');
        }
    }
} 