<?php

namespace api\modules\v1\models;

use api\helpers\UserHelp;
use \yii\db\ActiveRecord;

/**
 * Country Model
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class TripAccess extends ActiveRecord
{
    const ROLE = [
        'CREATOR' => 7,
        'VIEWER' => 4,
        'EDITOR' => 6
    ];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ts_trip_user';
    }

    /**
     * Define rules for validation
     */
    public function rules()
    {
        return [
            [['trip_id', 'user_id', 'access'], 'integer'],
        ];
    }

    public function getTrip()
    {
        return $this->hasOne(Trip::className(), ['id' => 'trip_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    public static function getTripUsers($trip_id)
    {
        $data = TripAccess::find()->alias('a')
            ->select(['user_id', 'a.access', 'u.nickname', 'u.name', 'u.pic'])
            ->joinWith(['user u'])
            ->andWhere(['a.trip_id' => $trip_id])
            ->orderBy(['access' => SORT_DESC])
            ->asArray()->all();

        foreach ($data as $id => $part) {
            unset($data[$id]['user']);
        }

        return $data;

    }
    
    public static function grantAccess($user_id, $trip_id, $access_code = self::ROLE['VIEWER']){
        if (!$access = self::findOne(['trip_id' => $trip_id, 'user_id' => $user_id])) {
            $access = new self();
            $access->trip_id = $trip_id;
            $access->user_id = $user_id;
            $access->access = $access_code;
            $access->save();
        }
    }


}
