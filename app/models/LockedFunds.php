<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $user_id
 * @property float $amount
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 */
class LockedFunds extends ActiveRecord
{
    public static function tableName()
    {
        return 'locked_funds';
    }
} 