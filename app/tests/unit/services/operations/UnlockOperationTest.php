<?php

namespace tests\unit\services\operations;

use app\models\LockedFunds;
use app\models\Transaction;
use app\models\User;
use app\services\UnlockOperation;
use PHPUnit\Framework\TestCase;
use Yii;

class UnlockOperationTest extends TestCase
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
        LockedFunds::deleteAll();
    }

    private function createUserWithBalance($balance): User
    {
        $userId = random_int(1, 100000);
        $user = new User(['balance' => $balance, 'id' => $userId]);
        $user->save(false);
        return $user;
    }

    private function createLock($userId, $amount, $status = 'locked')
    {
        $lock = new LockedFunds([
            'user_id' => $userId,
            'amount' => $amount,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        return $lock;
    }

    public function testSuccess()
    {
        $user = $this->createUserWithBalance(10);
        // Создаём блокировку с lock_id = 'lock-test-1' (строковый внешний id)
        $lock = new \app\models\LockedFunds([
            'user_id' => $user->id,
            'amount' => 5,
            'status' => 'locked',
            'lock_id' => 'lock-test-1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        $op = new UnlockOperation();
        $result = $op->process([
            'user_id' => $user->id,
            'amount' => 5,
            'operation_id' => 'unlock1',
            'lock_id' => 'lock-test-1',
        ]);
        $this->assertEquals('success', $result['status']);
        $user->refresh();
        $this->assertEquals(15, $user->balance);
        $lock->refresh();
        $this->assertEquals('unlocked', $lock->status);
    }

    public function testDuplicate()
    {
        $user = $this->createUserWithBalance(10);
        $lock = $this->createLock($user->id, 5);
        $tr = new Transaction([
            'user_id' => $user->id,
            'type' => 'unlock',
            'amount' => 5,
            'status' => 'confirmed',
            'operation_id' => 'dup-unlock',
            'related_user_id' => $lock->user_id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $tr->save(false);
        $op = new UnlockOperation();
        $result = $op->process([
            'user_id' => $user->id,
            'amount' => 5,
            'operation_id' => 'dup-unlock',
            'lock_id' => $lock->id,
        ]);
        $this->assertEquals('duplicate', $result['status']);
    }

    public function testLockNotFound()
    {
        $user = $this->createUserWithBalance(10);
        $op = new UnlockOperation();
        $result = $op->process([
            'user_id' => $user->id,
            'amount' => 5,
            'operation_id' => 'unlock3',
            'lock_id' => 9999,
        ]);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Locked funds not found or already processed', $result['message']);
    }

    public function testNegativeAmount()
    {
        $user = $this->createUserWithBalance(10);
        $lock = $this->createLock($user->id, 5);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process([
            'user_id' => $user->id,
            'amount' => -5,
            'operation_id' => 'unlock4',
            'lock_id' => $lock->id,
        ]);
    }

    public function testZeroAmount()
    {
        $user = $this->createUserWithBalance(10);
        $lock = $this->createLock($user->id, 5);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id, lock_id required');
        $op->process([
            'user_id' => $user->id,
            'amount' => 0,
            'operation_id' => 'unlock5',
            'lock_id' => $lock->id,
        ]);
    }

    public function testNoOperationId()
    {
        $user = $this->createUserWithBalance(10);
        $lock = $this->createLock($user->id, 5);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id, lock_id required');
        $op->process([
            'user_id' => $user->id,
            'amount' => 5,
            'lock_id' => $lock->id,
        ]);
    }

    public function testNoUserId()
    {
        $user = $this->createUserWithBalance(10);
        $lock = $this->createLock($user->id, 5);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id, lock_id required');
        $op->process([
            'amount' => 5,
            'operation_id' => 'unlock6',
            'lock_id' => $lock->id,
        ]);
    }

    public function testNoAmount()
    {
        $user = $this->createUserWithBalance(10);
        $lock = $this->createLock($user->id, 5);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id, lock_id required');
        $op->process([
            'user_id' => $user->id,
            'operation_id' => 'unlock7',
            'lock_id' => $lock->id,
        ]);
    }
}
