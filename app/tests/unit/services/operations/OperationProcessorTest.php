<?php
namespace tests\unit\services\operations;

use app\models\User;
use app\models\Transaction;
use app\models\LockedFunds;
use app\services\OperationProcessor;
use PHPUnit\Framework\TestCase;

class OperationProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::deleteAll();
        Transaction::deleteAll();
        LockedFunds::deleteAll();
    }

    public function testDebitSuccess()
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $processor = new OperationProcessor();
        $data = [
            'operation' => 'debit',
            'user_id' => $user->id,
            'amount' => 50,
            'operation_id' => 'test-debit-1',
        ];
        $result = $processor->process($data);
        $this->assertEquals('success', $result['status']);
        $user->refresh();
        $this->assertEquals(50, $user->balance);
    }

    public function testCreditSuccess()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $processor = new OperationProcessor();
        $data = [
            'operation' => 'credit',
            'user_id' => $user->id,
            'amount' => 20,
            'operation_id' => 'test-credit-1',
        ];
        $result = $processor->process($data);
        $this->assertEquals('success', $result['status']);
        $user->refresh();
        $this->assertEquals(30, $user->balance);
    }

    // Аналогично добавить тесты для transfer, lock, unlock, а также негативные кейсы
} 