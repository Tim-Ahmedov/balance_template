<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property float $balance
 * @property string $created_at
 * @property string $updated_at
 */
class User extends ActiveRecord
{
    public static function tableName()
    {
        return 'user';
    }
} 