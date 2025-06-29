<?php

namespace tests\unit\services\operations;

use app\models\Transaction;
use app\models\User;
use app\services\CreditOperation;
use PHPUnit\Framework\TestCase;
use Yii;

class CreditOperationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Мокаем компонент amqpQueue
        Yii::$app->set('amqpQueue', new class {
            public function sendEvent($body)
            {
            }
        });
        User::deleteAll();
        Transaction::deleteAll();
    }

    private function createUserWithBalance($balance): User
    {
        $userId = random_int(1, 100000);
        $user = new User(['balance' => $balance, 'id' => $userId]);
        $user->save(false);
        return $user;
    }

    public function testSuccess()
    {
        $user = $this->createUserWithBalance(10);
        $op = new CreditOperation();
        $result = $op->process([
            'user_id' => $user->id,
            'amount' => 20,
            'operation_id' => 'op1',
        ]);
        $this->assertEquals('success', $result['status']);
        $user->refresh();
        $this->assertEquals(30, $user->balance);
    }

    public function testDuplicate()
    {
        $user = $this->createUserWithBalance(10);
        $tr = new Transaction([
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 10,
            'status' => 'confirmed',
            'operation_id' => 'dup-op',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $tr->save(false);
        $op = new CreditOperation();
        $result = $op->process([
            'user_id' => $user->id,
            'amount' => 10,
            'operation_id' => 'dup-op',
        ]);
        $this->assertEquals('duplicate', $result['status']);
    }

    public function testNegativeAmount()
    {
        $user = $this->createUserWithBalance(10);
        $op = new CreditOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process([
            'user_id' => $user->id,
            'amount' => -10,
            'operation_id' => 'op3',
        ]);
    }

    public function testZeroAmount()
    {
        $user = $this->createUserWithBalance(100);
        $op = new CreditOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id required');
        $op->process([
            'user_id' => $user->id,
            'amount' => 0,
            'operation_id' => 'op5',
        ]);
    }

    public function testNoOperationId()
    {
        $user = $this->createUserWithBalance(10);
        $op = new CreditOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id required');
        $op->process([
            'user_id' => $user->id,
            'amount' => 10,
        ]);
    }

    public function testNoUserId()
    {
        $op = new CreditOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id required');
        $op->process([
            'amount' => 10,
            'operation_id' => 'op5',
        ]);
    }

    public function testNoAmount()
    {
        $user = $this->createUserWithBalance(10);
        $op = new CreditOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id required');
        $op->process([
            'user_id' => $user->id,
            'operation_id' => 'op6',
        ]);
    }
}
