<?php
use yii\db\Migration;

class m240610_000004_add_operation_id_to_transaction extends Migration
{
    public function safeUp()
    {
        $this->addColumn('transaction', 'operation_id', $this->string(64)->null()->after('status'));
        $this->createIndex('idx-transaction-operation_id-type', 'transaction', ['operation_id', 'type'], true);
    }

    public function safeDown()
    {
        $this->dropIndex('idx-transaction-operation_id-type', 'transaction');
        $this->dropColumn('transaction', 'operation_id');
    }
} 