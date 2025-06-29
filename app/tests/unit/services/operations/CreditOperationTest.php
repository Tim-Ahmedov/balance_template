<?php

namespace tests\unit\services\operations;

use app\models\Transaction;
use app\models\User;
use app\services\CreditOperation;
use app\services\OperationData;
use PHPUnit\Framework\TestCase;
use Yii;

class CreditOperationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Мокаем компонент amqpQueue
        Yii::$app->set('amqpQueue', new class {
            public function sendEvent($body): void
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

    public function testSuccess(): void
    {
        $user = $this->createUserWithBalance(10);
        $op = new CreditOperation();
        $result = $op->process(OperationData::fromArray([
            'operation' => 'credit',
            'user_id' => $user->id,
            'amount' => 20,
            'operation_id' => 'op1',
        ]));
        $this->assertEquals('success', $result['status']);
        $user->refresh();
        $this->assertEquals(30, $user->balance);
    }

    public function testDuplicate(): void
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
        $result = $op->process(OperationData::fromArray([
            'operation' => 'credit',
            'user_id' => $user->id,
            'amount' => 10,
            'operation_id' => 'dup-op',
        ]));
        $this->assertEquals('duplicate', $result['status']);
    }

    public function testNegativeAmount(): void
    {
        $user = $this->createUserWithBalance(10);
        $op = new CreditOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process(OperationData::fromArray([
            'operation' => 'credit',
            'user_id' => $user->id,
            'amount' => -10,
            'operation_id' => 'op3',
        ]));
    }

    public function testZeroAmount(): void
    {
        $user = $this->createUserWithBalance(100);
        $op = new CreditOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process(OperationData::fromArray([
            'operation' => 'credit',
            'user_id' => $user->id,
            'amount' => 0,
            'operation_id' => 'op5',
        ]));
    }

    public function testNoOperationId(): void
    {
        $user = $this->createUserWithBalance(10);
        $op = new CreditOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('operation_id is required');
        $op->process(OperationData::fromArray([
            'operation' => 'credit',
            'user_id' => $user->id,
            'amount' => 10,
        ]));
    }

    public function testNoUserId(): void
    {
        $op = new CreditOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id is required');
        $op->process(OperationData::fromArray([
            'operation' => 'credit',
            'amount' => 10,
            'operation_id' => 'op5',
        ]));
    }

    public function testNoAmount(): void
    {
        $user = $this->createUserWithBalance(10);
        $op = new CreditOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process(OperationData::fromArray([
            'operation' => 'credit',
            'user_id' => $user->id,
            'operation_id' => 'op6',
        ]));
    }
}
