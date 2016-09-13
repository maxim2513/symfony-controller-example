<?php

namespace api\modules\v1\models;

use api\helpers\UserHelp;
use \yii\db\ActiveRecord;

/**
 * Country Model
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class TripHash extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ts_trip_hash';
    }

    /**
     * Define rules for validation
     */
    public function rules()
    {
        return [
            [['hash', 'code'], 'string', 'max' => 127],
            ['trip_id', 'integer'],
            [['lng', 'lat'], 'number'],
        ];
    }

    public function getTrip()
    {
        return $this->hasOne(Trip::className(), ['id' => 'trip_id']);
    }

    public static function generateHash($trip_id)
    {

        if ($data = self::findOne(['trip_id' => $trip_id])) {
            return $data;
        } else {
            do {
                $hash = $trip_id . md5(rand());
            } while (self::findOne(['hash' => $hash]));

            $hash_new = new self();
            $hash_new->code = substr($hash, 0, 5) . $trip_id;
            $hash_new->hash = $hash;
            $hash_new->trip_id = $trip_id;
            $hash_new->save();

            return [
                'hash' => $hash,
                'code' => $hash_new->code
            ];
        }

    }


}
