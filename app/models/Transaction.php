<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property float $amount
 * @property string $status
 * @property int|null $related_user_id
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $operation_id
 */
class Transaction extends ActiveRecord
{
    public static function tableName()
    {
        return 'transaction';
    }
}
