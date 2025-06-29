<?php

namespace app\services;

class OperationProcessor
{
    public function process(array $data)
    {
        if (empty($data['operation'])) {
            throw new \InvalidArgumentException('Operation type required');
        }
        return match ($data['operation']) {
            OperationType::DEBIT->value => (new DebitOperation())->process($data),
            OperationType::CREDIT->value => (new CreditOperation())->process($data),
            OperationType::TRANSFER->value => (new TransferOperation())->process($data),
            OperationType::LOCK->value => (new LockOperation())->process($data),
            OperationType::UNLOCK->value => (new UnlockOperation())->process($data),
            default => throw new \InvalidArgumentException('Unknown operation'),
        };
    }
}
