<?php

namespace api\modules\v1\models;

use api\helpers\UserHelp;
use \yii\db\ActiveRecord;

/**
 * Country Model
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class Airport extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ts_airports';
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
            ['code', 'string', 'max' => 5],
            ['name', 'string'],
            [['latitude', 'longitude'], 'number'],
            ['type', 'in', 'range' => ['Airport', 'City']]
        ];
    }

    public static function getNear($lat, $lng, $type = 'Airport')
    {
        $airports = self::find()
            ->select('latitude, longitude,code, name, type, SQRT(POW(69.1 * (`latitude` - ' . floatval($lat) . '), 2) + POW(69.1 * (' . floatval($lng) . ' - `longitude`) * COS(`latitude` / 57.3), 2)) AS `distance`')
            ->andWhere(['type' => $type])
            ->orderBy(['distance' => SORT_ASC])
            ->limit(5)->asArray()->all();
        return $airports;

    }

}
