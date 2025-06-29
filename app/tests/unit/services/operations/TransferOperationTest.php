<?php
namespace tests\unit\services\operations;

use app\models\User;
use app\models\Transaction;
use app\services\TransferOperation;
use Yii;
use PHPUnit\Framework\TestCase;

class TransferOperationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        User::deleteAll();
        Transaction::deleteAll();
    }

    public function testSuccess()
    {
        $from = new User(['balance' => 100]);
        $from->save(false);
        $to = new User(['balance' => 10]);
        $to->save(false);
        $op = new TransferOperation();
        $result = $op->process([
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 50,
            'operation_id' => 'op1',
        ]);
        $this->assertEquals('success', $result['status']);
        $from->refresh();
        $to->refresh();
        $this->assertEquals(50, $from->balance);
        $this->assertEquals(60, $to->balance);
    }

    public function testInsufficientFunds()
    {
        $from = new User(['balance' => 10]);
        $from->save(false);
        $to = new User(['balance' => 10]);
        $to->save(false);
        $op = new TransferOperation();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient funds');
        $op->process([
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 100,
            'operation_id' => 'op2',
        ]);
    }

    public function testTransferToSelf()
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transfer to self');
        $op->process([
            'user_id' => $user->id,
            'related_user_id' => $user->id,
            'amount' => 10,
            'operation_id' => 'op3',
        ]);
    }

    public function testUserNotFound()
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $op = new TransferOperation();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User(s) not found');
        $op->process([
            'user_id' => $user->id,
            'related_user_id' => 9999,
            'amount' => 10,
            'operation_id' => 'op4',
        ]);
    }

    public function testDuplicate()
    {
        $from = new User(['balance' => 100]);
        $from->save(false);
        $to = new User(['balance' => 10]);
        $to->save(false);
        $tr = new Transaction([
            'user_id' => $from->id,
            'type' => 'transfer',
            'amount' => 10,
            'status' => 'confirmed',
            'operation_id' => 'dup-op',
            'related_user_id' => $to->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $tr->save(false);
        $op = new TransferOperation();
        $result = $op->process([
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 10,
            'operation_id' => 'dup-op',
        ]);
        $this->assertEquals('duplicate', $result['status']);
    }

    public function testNegativeAmount()
    {
        $from = new User(['balance' => 100]);
        $from->save(false);
        $to = new User(['balance' => 10]);
        $to->save(false);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process([
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => -10,
            'operation_id' => 'op5',
        ]);
    }

    public function testZeroAmount()
    {
        $from = new User(['balance' => 100]);
        $from->save(false);
        $to = new User(['balance' => 10]);
        $to->save(false);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process([
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 0,
            'operation_id' => 'op6',
        ]);
    }

    public function testNoOperationId()
    {
        $from = new User(['balance' => 100]);
        $from->save(false);
        $to = new User(['balance' => 10]);
        $to->save(false);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, related_user_id, amount, operation_id required');
        $op->process([
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 10,
        ]);
    }

    public function testNoUserId()
    {
        $to = new User(['balance' => 10]);
        $to->save(false);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, related_user_id, amount, operation_id required');
        $op->process([
            'related_user_id' => $to->id,
            'amount' => 10,
            'operation_id' => 'op7',
        ]);
    }

    public function testNoRelatedUserId()
    {
        $from = new User(['balance' => 100]);
        $from->save(false);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, related_user_id, amount, operation_id required');
        $op->process([
            'user_id' => $from->id,
            'amount' => 10,
            'operation_id' => 'op8',
        ]);
    }

    public function testNoAmount()
    {
        $from = new User(['balance' => 100]);
        $from->save(false);
        $to = new User(['balance' => 10]);
        $to->save(false);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, related_user_id, amount, operation_id required');
        $op->process([
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'operation_id' => 'op9',
        ]);
    }
} 