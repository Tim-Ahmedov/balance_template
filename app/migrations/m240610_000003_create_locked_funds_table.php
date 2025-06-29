<?php
use yii\db\Migration;

class m240610_000003_create_locked_funds_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('locked_funds', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'amount' => $this->decimal(20, 4)->notNull(),
            'status' => $this->string(32)->notNull(),
            'lock_id' => $this->string(64)->null()->unique(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);
        $this->addForeignKey('fk-locked_funds-user_id', 'locked_funds', 'user_id', 'user', 'id', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-locked_funds-user_id', 'locked_funds');
        $this->dropTable('locked_funds');
    }
} 