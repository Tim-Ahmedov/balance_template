<?php

namespace tests\unit\services\operations;

use app\models\LockedFunds;
use app\models\Transaction;
use app\models\User;
use app\services\LockOperation;
use app\services\OperationData;
use PHPUnit\Framework\TestCase;
use Yii;

class LockOperationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->set('amqpQueue', new class {
            public function sendEvent($body)
            {
            }
        });
        User::deleteAll();
        Transaction::deleteAll();
        LockedFunds::deleteAll();
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
        $user = $this->createUserWithBalance(100);
        $op = new LockOperation();
        $result = $op->process(OperationData::fromArray([
            'operation' => 'lock',
            'user_id' => $user->id,
            'amount' => 30,
            'operation_id' => 'op1',
        ]));
        $this->assertEquals('success', $result['status']);
        $user->refresh();
        $this->assertEquals(70, $user->balance);
        $lock = LockedFunds::find()->where(['user_id' => $user->id, 'amount' => 30, 'status' => 'locked'])->one();
        $this->assertNotNull($lock);
    }

    public function testDuplicate(): void
    {
        $user = $this->createUserWithBalance(100);
        $tr = new Transaction([
            'user_id' => $user->id,
            'type' => 'lock',
            'amount' => 10,
            'status' => 'confirmed',
            'operation_id' => 'dup-op',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $tr->save(false);
        $op = new LockOperation();
        $result = $op->process(OperationData::fromArray([
            'operation' => 'lock',
            'user_id' => $user->id,
            'amount' => 10,
            'operation_id' => 'dup-op',
        ]));
        $this->assertEquals('duplicate', $result['status']);
    }
    public function testInsufficientFunds(): void
    {
        $user = $this->createUserWithBalance(10);
        $op = new LockOperation();
        $result = $op->process(OperationData::fromArray([
            'operation' => 'lock',
            'user_id' => $user->id,
            'amount' => 20,
            'operation_id' => 'op3',
        ]));
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Insufficient funds', $result['message']);
    }

    public function testNegativeAmount(): void
    {
        $user = $this->createUserWithBalance(100);
        $op = new LockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process(OperationData::fromArray([
            'operation' => 'lock',
            'user_id' => $user->id,
            'amount' => -10,
            'operation_id' => 'op4',
        ]));
    }

    public function testZeroAmount(): void
    {
        $user = $this->createUserWithBalance(100);
        $op = new LockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process(OperationData::fromArray([
            'operation' => 'lock',
            'user_id' => $user->id,
            'amount' => 0,
            'operation_id' => 'op5',
        ]));
    }

    public function testNoOperationId(): void
    {
        $user = $this->createUserWithBalance(100);
        $op = new LockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('operation_id is required');
        $op->process(OperationData::fromArray([
            'operation' => 'lock',
            'user_id' => $user->id,
            'amount' => 10,
        ]));
    }

    public function testNoUserId(): void
    {
        $op = new LockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id is required');
        $op->process(OperationData::fromArray([
            'operation' => 'lock',
            'amount' => 10,
            'operation_id' => 'op6',
        ]));
    }

    public function testNoAmount(): void
    {
        $user = $this->createUserWithBalance(100);
        $op = new LockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process(OperationData::fromArray([
            'operation' => 'lock',
            'user_id' => $user->id,
            'operation_id' => 'op7',
        ]));
    }
}
