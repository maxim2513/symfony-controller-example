<?php

namespace api\modules\v1\models;

use api\helpers\UserHelp;
use \yii\db\ActiveRecord;

/**
 * Country Model
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class TripDestination extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ts_trip_destination';
    }

    /**
     * Define rules for validation
     */
    public function rules()
    {
        return [
            ['destination', 'string', 'max' => 50],
            [['trip_id', 'order_no'], 'integer'],
            [['lng', 'lat'], 'number'],
        ];
    }

    public function getTrip()
    {
        return $this->hasOne(Trip::className(), ['id' => 'trip_id']);
    }

    public static function setDestinations($trip_id, $destinations)
    {
        self::deleteAll(['trip_id' => $trip_id]);
        $order_no = 0;
        foreach ($destinations as $destination) {
            if ($destination) {
                $location = \Yii::$app->googleApi->getGeoCodeObject($destination);
                $destination_new = new self();
                $destination_new->destination = $destination;
                $destination_new->trip_id = $trip_id;
                if (isset($location->geometry->location)) {
                    $destination_new->lat = $location->geometry->location->lat;
                    $destination_new->lng = $location->geometry->location->lng;
                } else {
                    $destination_new->lat = 0;
                    $destination_new->lng = 0;

                }
                $destination_new->order_no = $order_no;
                $destination_new->save();
                $order_no++;
            }
        }
    }

    public static function getDestinations($trip_id)
    {
        $destinations = self::find()->where(['trip_id' => $trip_id])->orderBy(['order_no' => SORT_ASC])->all();

        $results = ['destinations' => [], 'latlngs' => []];

        foreach ($destinations as $destination) {
            $results['destinations'][] = $destination->destination;
            $results['latlngs'][] = $destination->lat == 0 && $destination->lng == 0
                ? null
                : $destination->lat . ';' . $destination->lng;
        }
        return $results;
    }


}
