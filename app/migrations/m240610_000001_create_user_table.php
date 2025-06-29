<?php
use yii\db\Migration;

class m240610_000001_create_user_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('user', [
            'id' => $this->integer()->notNull(),
            'balance' => $this->decimal(20, 4)->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('user_id', 'user', 'id', true); //Считаем, что пользователями управляет внешняя система
    }

    public function safeDown()
    {
        $this->dropTable('user');
    }
} 