<?php
namespace tests\unit\services\operations;

use app\models\User;
use app\models\Transaction;
use app\services\DebitOperation;
use Yii;
use PHPUnit\Framework\TestCase;

class DebitOperationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::deleteAll();
        Transaction::deleteAll();
    }

    public function testSuccess()
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $op = new DebitOperation();
        $result = $op->process([
            'user_id' => $user->id,
            'amount' => 50,
            'operation_id' => 'op1',
        ]);
        $this->assertEquals('success', $result['status']);
        $user->refresh();
        $this->assertEquals(50, $user->balance);
    }

    public function testInsufficientFunds()
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $op = new DebitOperation();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient funds');
        $op->process([
            'user_id' => $user->id,
            'amount' => 100,
            'operation_id' => 'op2',
        ]);
    }

    public function testUserNotFound()
    {
        $op = new DebitOperation();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not found');
        $op->process([
            'user_id' => 9999,
            'amount' => 10,
            'operation_id' => 'op3',
        ]);
    }

    public function testDuplicate()
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $tr = new Transaction([
            'user_id' => $user->id,
            'type' => 'debit',
            'amount' => 10,
            'status' => 'confirmed',
            'operation_id' => 'dup-op',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $tr->save(false);
        $op = new DebitOperation();
        $result = $op->process([
            'user_id' => $user->id,
            'amount' => 10,
            'operation_id' => 'dup-op',
        ]);
        $this->assertEquals('duplicate', $result['status']);
    }

    public function testNegativeAmount()
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $op = new DebitOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process([
            'user_id' => $user->id,
            'amount' => -10,
            'operation_id' => 'op4',
        ]);
    }

    public function testZeroAmount()
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $op = new DebitOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process([
            'user_id' => $user->id,
            'amount' => 0,
            'operation_id' => 'op5',
        ]);
    }

    public function testNoOperationId()
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $op = new DebitOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id required');
        $op->process([
            'user_id' => $user->id,
            'amount' => 10,
        ]);
    }

    public function testNoUserId()
    {
        $op = new DebitOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id required');
        $op->process([
            'amount' => 10,
            'operation_id' => 'op6',
        ]);
    }

    public function testNoAmount()
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $op = new DebitOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, amount, operation_id required');
        $op->process([
            'user_id' => $user->id,
            'operation_id' => 'op7',
        ]);
    }
} 