<?php

namespace tests\unit\services\operations;

use app\models\Transaction;
use app\models\User;
use app\services\OperationData;
use app\services\TransferOperation;
use PHPUnit\Framework\TestCase;
use Yii;

class TransferOperationTest extends TestCase
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
        $from = $this->createUserWithBalance(100);
        $to = $this->createUserWithBalance(10);
        $op = new TransferOperation();
        $result = $op->process(OperationData::fromArray([
            'operation' => 'transfer',
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 40,
            'operation_id' => 'tr1',
        ]));
        $this->assertEquals('success', $result['status']);
        $from->refresh();
        $to->refresh();
        $this->assertEquals(60, $from->balance);
        $this->assertEquals(50, $to->balance);
    }

    public function testDuplicate(): void
    {
        $from = $this->createUserWithBalance(100);
        $to = $this->createUserWithBalance(10);
        $tr = new Transaction([
            'user_id' => $from->id,
            'type' => 'transfer',
            'amount' => 20,
            'status' => 'confirmed',
            'operation_id' => 'dup-tr',
            'related_user_id' => $to->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $tr->save(false);
        $op = new TransferOperation();
        $result = $op->process(OperationData::fromArray([
            'operation' => 'transfer',
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 20,
            'operation_id' => 'dup-tr',
        ]));
        $this->assertEquals('duplicate', $result['status']);
    }
    public function testInsufficientFunds(): void
    {
        $from = $this->createUserWithBalance(10);
        $to = $this->createUserWithBalance(10);
        $op = new TransferOperation();
        $result = $op->process(OperationData::fromArray([
            'operation' => 'transfer',
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 20,
            'operation_id' => 'tr4',
        ]));
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Insufficient funds', $result['message']);
    }

    public function testNegativeAmount(): void
    {
        $from = $this->createUserWithBalance(100);
        $to = $this->createUserWithBalance(10);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process(OperationData::fromArray([
            'operation' => 'transfer',
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => -10,
            'operation_id' => 'tr5',
        ]));
    }

    public function testZeroAmount(): void
    {
        $from = $this->createUserWithBalance(100);
        $to = $this->createUserWithBalance(10);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process(OperationData::fromArray([
            'operation' => 'transfer',
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 0,
            'operation_id' => 'tr6',
        ]));
    }

    public function testNoOperationId(): void
    {
        $from = $this->createUserWithBalance(100);
        $to = $this->createUserWithBalance(10);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('operation_id is required');
        $op->process(OperationData::fromArray([
            'operation' => 'transfer',
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 10,
        ]));
    }

    public function testNoFromUserId(): void
    {
        $to = $this->createUserWithBalance(10);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id is required');
        $op->process(OperationData::fromArray([
            'operation' => 'transfer',
            'related_user_id' => $to->id,
            'amount' => 10,
            'operation_id' => 'tr7',
        ]));
    }

    public function testNoToUserId(): void
    {
        $from = $this->createUserWithBalance(100);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('related_user_id is required');
        $op->process(OperationData::fromArray([
            'operation' => 'transfer',
            'user_id' => $from->id,
            'amount' => 10,
            'operation_id' => 'tr8',
        ]));
    }

    public function testNoAmount(): void
    {
        $from = $this->createUserWithBalance(100);
        $to = $this->createUserWithBalance(10);
        $op = new TransferOperation();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        $op->process(OperationData::fromArray([
            'operation' => 'transfer',
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'operation_id' => 'tr9',
        ]));
    }
}
