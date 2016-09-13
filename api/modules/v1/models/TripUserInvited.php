<?php

namespace api\modules\v1\models;

use api\helpers\Result;
use api\helpers\UserHelp;
use \yii\db\ActiveRecord;

/**
 * Country Model
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class TripUserInvited extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ts_invited_trip_user';
    }

    /**
     * @inheritdoc
     */
    public static function primaryKey()
    {
        return ['invite_code','trip_id'];
    }

    /**
     * Define rules for validation
     */
    public function rules()
    {
        return [
            ['invite_code', 'string', 'max' => 127],
            ['user_email', 'string', 'max' => 255],
            ['sns_uid', 'string', 'max' => 50],
            [[ 'trip_id', 'sns_no'], 'integer'],
        ];
    }


    public function getTrip()
    {
        return $this->hasOne(Trip::className(), ['id' => 'trip_id']);
    }
    
    public static function generateInviteCode($trip_id, $email = false, $sns_no = false, $sns_uid = false){
        $existing_code = '';
        
        if($email){
            $existing_code = self::findOne(['user_email'=>$email])->invite_code;
        }elseif ($sns_no && $sns_no!=1){
            $existing_code = self::findOne(['sns_uid'=>$sns_uid])->invite_code;
        }
        
        if($existing_code){
            return $existing_code;
        }

        if ($email) {
            $invite_code = $trip_id . md5($email . rand());
        } else if ($sns_uid) {
            $invite_code = $trip_id . md5($sns_uid . rand());
        } else if ($sns_no) { //if no uid but sns, it must be fb
            $invite_code = $trip_id . md5($sns_no . rand());
        } else {
            return false;
        }

        return $invite_code;
    }
    
    public static function processCode($code,$user_id){
        $trip_ids = self::find()
            ->select('trip_id')
            ->andWhere(['invite_code'=>$code])
            ->asArray()->all();
        $trip_ids = array_column($trip_ids, 'trip_id');

        foreach ($trip_ids as $trip_id){
            TripAccess::grantAccess($user_id, $trip_id);
        }
        
        self::deleteAll(['invite_code'=>$code,'sns_no'=>0]);
        
        return $trip_ids;
    }


}
