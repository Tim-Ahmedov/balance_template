<?php
namespace tests\unit\services\operations;

use app\models\User;
use app\models\Transaction;
use app\models\LockedFunds;
use app\services\OperationProcessor;
use Yii;

class OperationProcessorCest
{
    public function _before(UnitTester $I)
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

    public function debitSuccess(UnitTester $I)
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
        $I->assertEquals('success', $result['status']);
        $user->refresh();
        $I->assertEquals(50, $user->balance);
    }

    public function creditSuccess(UnitTester $I)
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
        $I->assertEquals('success', $result['status']);
        $user->refresh();
        $I->assertEquals(30, $user->balance);
    }

    // TODO: Добавить аналогичные тесты для transfer, lock, unlock
    // и негативные кейсы (например, недостаточно средств, неверный operation_id и т.д.)
} 