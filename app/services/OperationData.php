<?php

namespace app\services;

class OperationData
{
    public string $operation;
    public int $user_id;
    public ?int $related_user_id = null;
    public float $amount;
    public string $operation_id;
    public ?string $lock_id = null;
    public ?bool $confirm = null;

    public function __construct(
        string $operation,
        int $user_id,
        float $amount,
        string $operation_id,
        ?int $related_user_id = null,
        ?string $lock_id = null,
        ?bool $confirm = null
    ) {
        $this->operation = $operation;
        $this->user_id = $user_id;
        $this->amount = $amount;
        $this->operation_id = $operation_id;
        $this->related_user_id = $related_user_id;
        $this->lock_id = $lock_id;
        $this->confirm = $confirm;
    }

    public static function fromArray(array $data): self
    {
        if (empty($data['operation'])) {
            throw new \InvalidArgumentException('operation is required');
        }
        if (empty($data['user_id'])) {
            throw new \InvalidArgumentException('user_id is required');
        }
        if (!isset($data['amount']) || (float)$data['amount'] <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        if (empty($data['operation_id'])) {
            throw new \InvalidArgumentException('operation_id is required');
        }
        if ($data['operation'] === 'transfer' && !isset($data['related_user_id'])) {
            throw new \InvalidArgumentException('related_user_id is required');
        }
        if ($data['operation'] === 'unlock' && !isset($data['lock_id'])) {
            throw new \InvalidArgumentException('lock_id is required');
        }
        return new self(
            $data['operation'],
            (int)$data['user_id'],
            (float)$data['amount'],
            $data['operation_id'],
            isset($data['related_user_id']) ? (int)$data['related_user_id'] : null,
            $data['lock_id'] ?? null,
            isset($data['confirm']) ? (bool)$data['confirm'] : null
        );
    }
} 