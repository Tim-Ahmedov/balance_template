<?php
use yii\db\Migration;

class m240610_000002_create_transaction_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('transaction', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'type' => $this->string(32)->notNull(),
            'amount' => $this->decimal(20, 4)->notNull(),
            'status' => $this->string(32)->notNull(),
            'related_user_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        $this->addForeignKey('fk-transaction-user_id', 'transaction', 'user_id', 'user', 'id', 'CASCADE');
        $this->addForeignKey('fk-transaction-related_user_id', 'transaction', 'related_user_id', 'user', 'id', 'SET NULL');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-transaction-user_id', 'transaction');
        $this->dropForeignKey('fk-transaction-related_user_id', 'transaction');
        $this->dropTable('transaction');
    }
} 