<?php
namespace tests\functional;

use app\models\User;
use app\models\Transaction;
use app\models\LockedFunds;
use app\services\OperationProcessor;
use Yii;

class OperationProcessorCest
{
    public function _before(\FunctionalTester $I)
    {
        // Отключаем отправку событий в очередь
        Yii::$app->set('amqpQueue', new class {
            public function sendEvent($body) {}
        });
        // Очистка таблиц
        User::deleteAll();
        Transaction::deleteAll();
        LockedFunds::deleteAll();
    }

    public function debitSuccess(\FunctionalTester $I)
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $processor = new OperationProcessor();
        $data = [
            'operation' => 'debit',
            'user_id' => $user->id,
            'amount' => 50,
            'operation_id' => 'func-debit-1',
        ];
        $result = $processor->process($data);
        $I->assertEquals('success', $result['status']);
        $user->refresh();
        $I->assertEquals(50, $user->balance);
    }

    public function debitInsufficientFunds(\FunctionalTester $I)
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $processor = new OperationProcessor();
        $data = [
            'operation' => 'debit',
            'user_id' => $user->id,
            'amount' => 50,
            'operation_id' => 'func-debit-2',
        ];
        $result = $processor->process($data);
        $I->assertEquals('error', $result['status']);
        $I->assertStringContainsString('недостаточно', mb_strtolower($result['message']));
    }

    public function creditSuccess(\FunctionalTester $I)
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $processor = new OperationProcessor();
        $data = [
            'operation' => 'credit',
            'user_id' => $user->id,
            'amount' => 20,
            'operation_id' => 'func-credit-1',
        ];
        $result = $processor->process($data);
        $I->assertEquals('success', $result['status']);
        $user->refresh();
        $I->assertEquals(30, $user->balance);
    }

    public function transferSuccess(\FunctionalTester $I)
    {
        $from = new User(['balance' => 100]);
        $from->save(false);
        $to = new User(['balance' => 5]);
        $to->save(false);
        $processor = new OperationProcessor();
        $data = [
            'operation' => 'transfer',
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 30,
            'operation_id' => 'func-transfer-1',
        ];
        $result = $processor->process($data);
        $I->assertEquals('success', $result['status']);
        $from->refresh();
        $to->refresh();
        $I->assertEquals(70, $from->balance);
        $I->assertEquals(35, $to->balance);
    }

    public function transferInsufficientFunds(\FunctionalTester $I)
    {
        $from = new User(['balance' => 10]);
        $from->save(false);
        $to = new User(['balance' => 5]);
        $to->save(false);
        $processor = new OperationProcessor();
        $data = [
            'operation' => 'transfer',
            'user_id' => $from->id,
            'related_user_id' => $to->id,
            'amount' => 30,
            'operation_id' => 'func-transfer-2',
        ];
        $result = $processor->process($data);
        $I->assertEquals('error', $result['status']);
        $I->assertStringContainsString('недостаточно', mb_strtolower($result['message']));
    }

    public function lockSuccess(\FunctionalTester $I)
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $processor = new OperationProcessor();
        $data = [
            'operation' => 'lock',
            'user_id' => $user->id,
            'amount' => 40,
            'operation_id' => 'func-lock-1',
            'lock_id' => 'lock-1',
        ];
        $result = $processor->process($data);
        $I->assertEquals('success', $result['status']);
        $user->refresh();
        $I->assertEquals(60, $user->balance);
        $I->assertEquals(1, LockedFunds::find()->where(['user_id' => $user->id, 'lock_id' => 'lock-1'])->count());
    }

    public function lockInsufficientFunds(\FunctionalTester $I)
    {
        $user = new User(['balance' => 10]);
        $user->save(false);
        $processor = new OperationProcessor();
        $data = [
            'operation' => 'lock',
            'user_id' => $user->id,
            'amount' => 40,
            'operation_id' => 'func-lock-2',
            'lock_id' => 'lock-2',
        ];
        $result = $processor->process($data);
        $I->assertEquals('error', $result['status']);
        $I->assertStringContainsString('недостаточно', mb_strtolower($result['message']));
    }

    public function unlockSuccess(\FunctionalTester $I)
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $processor = new OperationProcessor();
        // Сначала блокируем
        $lockData = [
            'operation' => 'lock',
            'user_id' => $user->id,
            'amount' => 40,
            'operation_id' => 'func-unlock-lock-1',
            'lock_id' => 'lock-3',
        ];
        $processor->process($lockData);
        // Теперь разблокируем
        $unlockData = [
            'operation' => 'unlock',
            'user_id' => $user->id,
            'amount' => 40,
            'operation_id' => 'func-unlock-1',
            'lock_id' => 'lock-3',
        ];
        $result = $processor->process($unlockData);
        $I->assertEquals('success', $result['status']);
        $user->refresh();
        $I->assertEquals(100, $user->balance);
        $I->assertEquals(0, LockedFunds::find()->where(['user_id' => $user->id, 'lock_id' => 'lock-3'])->count());
    }

    public function unlockNoLock(\FunctionalTester $I)
    {
        $user = new User(['balance' => 100]);
        $user->save(false);
        $processor = new OperationProcessor();
        $data = [
            'operation' => 'unlock',
            'user_id' => $user->id,
            'amount' => 40,
            'operation_id' => 'func-unlock-2',
            'lock_id' => 'lock-404',
        ];
        $result = $processor->process($data);
        $I->assertEquals('error', $result['status']);
        $I->assertStringContainsString('не найден', mb_strtolower($result['message']));
    }

    // Можно добавить дополнительные edge-кейсы: нулевые суммы, дубли operation_id, self-transfer и т.д.
} 