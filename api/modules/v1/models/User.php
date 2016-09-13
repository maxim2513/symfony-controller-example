<?php

namespace api\modules\v1\models;

use api\helpers\LibHelp;
use api\helpers\UserHelp;
use \yii\db\ActiveRecord;

/**
 * Country Model
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class User extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ts_user';
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
            [['token', 'nickname'], 'required'],
            ['email', 'email'],
            [['token', 'nickname', 'passwd', 'secret', 'salt'], 'string', 'max' => 32],
            [['pic', 'bio', 'link'], 'string', 'max' => 255],
            ['phone', 'string', 'max' => 64],
            [['name', 'passwd_reset'], 'string', 'max' => 128],
            [['pic_id', 'created', 'updated', 'reset_expires', 'is_paid'], 'integer'],
            ['state', 'in', 'range' => ['pending', 'active', 'disabled']]
        ];
    }

    public function newUser()
    {
        $this->token = UserHelp::getToken();
        $t = time();
        $this->created = $t;
        $this->updated = $t;
    }

    public static function loginToken()
    {
        $id = LibHelp::parseDataPost('u');
        $token = LibHelp::parseDataPost('t');
        $user = self::findOne(['id' => $id, 'token' => $token]);
        if (!$user) {
            $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
            \Yii::info(__FILE__ . ":" . __LINE__ . ":failed token login from IP:" . $ip);
            return false;
        }
        $user->updated = time();
        $user->save();
        return $user;
    }

    public function getProfile($id)
    {
        if ($id && $id != $this->id) {

            if (is_numeric($id)) {
                $data = self::findOne($id)->toArray(['nickname', 'name', 'pic', 'bio', 'link']);
            } else {
                $data = self::findOne(['nickname' => $id])->toArray(['nickname', 'name', 'pic', 'bio', 'link']);
            }
        } else {
            $data = $this->toArray(['email', 'nickname', 'name', 'phone', 'pic', 'bio', 'link']);

        }
        return $data;
    }

    public function getAccess()
    {
        return $this->hasMany(TripAccess::className(), ['user_id' => 'id']);
    }

    public function getSns()
    {
        return $this->hasOne(UserSns::className(), ['id' => 'user_id']);
    }

}
