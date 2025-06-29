<?php
namespace app\models;

use common\components\helpers\ArrayHelper;
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

    public static function findForUpdateOrCreate(int $userId): User
    {
        $user = self::findForUpdate($userId);

        if (!$user) {
            $user = new User(['id' => $userId]);
            $user->save();
        }

        return self::findForUpdate($userId);
    }

    public static function findForUpdate(int $userId): ?User
    {
        $sql = static::find()
            ->where(['id' => $userId])
            ->createCommand()
            ->getRawSql();

        return static::findBySql($sql . ' FOR UPDATE')->one();
    }
} 