<?php
namespace tests\unit\services\operations;

use app\models\User;
use app\models\Transaction;
use app\models\LockedFunds;
use app\services\UnlockOperation;
use Yii;
use PHPUnit\Framework\TestCase;

class UnlockOperationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::deleteAll();
        Transaction::deleteAll();
        LockedFunds::deleteAll();
    }

    public function testSuccessRefund()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $lock = new LockedFunds([
            'user_id' => $user->id,
            'amount' => 50,
            'status' => 'locked',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        $op = new UnlockOperation();
        $result = $op->process([
            'user_id' => $user->id,
            'amount' => 50,
            'operation_id' => 'op1',
            'lock_id' => $lock->id,
        ]);
        $this->assertEquals('success', $result['status']);
        $user->refresh();
        $this->assertEquals(60, $user->balance);
        $lock->refresh();
        $this->assertEquals('unlocked', $lock->status);
    }

    public function testSuccessCharge()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $lock = new LockedFunds([
            'user_id' => $user->id,
            'amount' => 50,
            'status' => 'locked',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        $op = new UnlockOperation();
        $result = $op->process([
            'user_id' => $user->id,
            'amount' => 50,
            'operation_id' => 'op2',
            'lock_id' => $lock->id,
            'confirm' => true,
        ]);
        $this->assertEquals('success', $result['status']);
        $lock->refresh();
        $this->assertEquals('charged', $lock->status);
    }

    public function testLockNotFound()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $op = new UnlockOperation();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Locked funds not found or already processed');
        $op->process([
            'user_id' => $user->id,
            'amount' => 50,
            'operation_id' => 'op3',
            'lock_id' => 9999,
        ]);
    }

    public function testUserNotFound()
    {
        $lock = new LockedFunds([
            'user_id' => 1234,
            'amount' => 50,
            'status' => 'locked',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        $op = new UnlockOperation();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not found');
        $op->process([
            'user_id' => 9999,
            'amount' => 50,
            'operation_id' => 'op4',
            'lock_id' => $lock->id,
        ]);
    }

    public function testDuplicate()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $lock = new LockedFunds([
            'user_id' => $user->id,
            'amount' => 50,
            'status' => 'locked',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        $tr = new Transaction([
            'user_id' => $user->id,
            'type' => 'unlock',
            'amount' => 50,
            'status' => 'confirmed',
            'operation_id' => 'dup-op',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $tr->save(false);
        $op = new UnlockOperation();
        $result = $op->process([
            'user_id' => $user->id,
            'amount' => 50,
            'operation_id' => 'dup-op',
            'lock_id' => $lock->id,
        ]);
        $this->assertEquals('duplicate', $result['status']);
    }

    public function testNegativeAmount()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $lock = new LockedFunds([
            'user_id' => $user->id,
            'amount' => 50,
            'status' => 'locked',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process([
            'user_id' => $user->id,
            'amount' => -10,
            'operation_id' => 'op5',
            'lock_id' => $lock->id,
        ]);
    }

    public function testZeroAmount()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $lock = new LockedFunds([
            'user_id' => $user->id,
            'amount' => 50,
            'status' => 'locked',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process([
            'user_id' => $user->id,
            'amount' => 0,
            'operation_id' => 'op6',
            'lock_id' => $lock->id,
        ]);
    }

    public function testNoOperationId()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $lock = new LockedFunds([
            'user_id' => $user->id,
            'amount' => 50,
            'status' => 'locked',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id, lock_id required');
        $op->process([
            'user_id' => $user->id,
            'amount' => 10,
            'lock_id' => $lock->id,
        ]);
    }

    public function testNoUserId()
    {
        $lock = new LockedFunds([
            'user_id' => 1234,
            'amount' => 50,
            'status' => 'locked',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id, lock_id required');
        $op->process([
            'amount' => 10,
            'operation_id' => 'op7',
            'lock_id' => $lock->id,
        ]);
    }

    public function testNoAmount()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $lock = new LockedFunds([
            'user_id' => $user->id,
            'amount' => 50,
            'status' => 'locked',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $lock->save(false);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id, lock_id required');
        $op->process([
            'user_id' => $user->id,
            'operation_id' => 'op8',
            'lock_id' => $lock->id,
        ]);
    }

    public function testNoLockId()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $op = new UnlockOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id, lock_id required');
        $op->process([
            'user_id' => $user->id,
            'amount' => 10,
            'operation_id' => 'op9',
        ]);
    }
} 