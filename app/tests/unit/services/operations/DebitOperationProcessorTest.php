<?php

namespace tests\unit\services\operations;

use app\models\Transactions;
use app\services\operations\DebitOperationProcessor;
use app\services\operations\OperationType;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DebitOperationProcessorTest extends TestCase
{
    public function testProcessSuccess()
    {
        $transaction = new Transactions();
        $transaction->id = 1;
        $transaction->user_id = 10;
        $transaction->amount = 50;
        $transaction->type = OperationType::DEBIT->value;
        $transaction->status = 'new';
        $processor = new DebitOperationProcessor();
        // ...
        // $result = $processor->process($transaction);
        // $this->assertEquals('success', $transaction->status);
    }

    public function testProcessNotEnoughFunds()
    {
        $transaction = new Transactions();
        $transaction->id = 1;
        $transaction->user_id = 10;
        $transaction->amount = 200;
        $transaction->type = OperationType::DEBIT->value;
        $transaction->status = 'new';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно средств!');
        $processor = new DebitOperationProcessor();
        $processor->process($transaction);
    }

    public function testProcessUserNotFound()
    {
        $transaction = new Transactions();
        $transaction->id = 1;
        $transaction->user_id = 10;
        $transaction->amount = 100;
        $transaction->type = OperationType::DEBIT->value;
        $transaction->status = 'new';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Получатель не найден');
        $processor = new DebitOperationProcessor();
        $processor->process($transaction);
    }

    public function testProcessBalanceSaveError()
    {
        $transaction = new Transactions();
        $transaction->id = 1;
        $transaction->user_id = 10;
        $transaction->amount = 50;
        $transaction->type = OperationType::DEBIT->value;
        $transaction->status = 'new';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ошибка сохранения баланса!');
        $processor = new DebitOperationProcessor();
        $processor->process($transaction);
    }

    public function testProcessAlreadyProcessedTransaction()
    {
        $transaction = new Transactions();
        $transaction->id = 1;
        $transaction->user_id = 10;
        $transaction->amount = 50;
        $transaction->type = OperationType::DEBIT->value;
        $transaction->status = 'success';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Некорректная транзакция');
        $processor = new DebitOperationProcessor();
        $processor->process($transaction);
    }

    public function testProcessWrongTransactionType()
    {
        $transaction = new Transactions();
        $transaction->id = 1;
        $transaction->user_id = 10;
        $transaction->amount = 50;
        $transaction->type = OperationType::CREDIT->value; // not DEBIT
        $transaction->status = 'new';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Некорректная транзакция');
        $processor = new DebitOperationProcessor();
        $processor->process($transaction);
    }
} 