<?php

namespace api\modules\v1\models;

use api\helpers\UserHelp;
use \yii\db\ActiveRecord;

/**
 * Country Model
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class UserSns extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ts_user_sns';
    }

    /**
     * @inheritdoc
     */
    public static function primaryKey()
    {
        return ['id'];
    }

    /**
     * Define rules for validation
     */
    public function rules()
    {
        return [
            ['sns_token', 'string', 'max' => 384],
            ['sns_uid', 'string', 'max' => 64],
            [['user_id', 'sns_no', 'created', 'updated'], 'integer'],
        ];
    }

    public function newUser()
    {
        $t = time();
        $this->created = $t;
        $this->updated = $t;
    }

    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    public static function getUsersByUid($ids)
    {
        return self::findAll(['sns_uid' => $ids]);
    }


}
