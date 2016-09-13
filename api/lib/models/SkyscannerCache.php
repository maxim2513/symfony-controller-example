<?php

namespace api\lib\models;

use api\helpers\UserHelp;
use \yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * Country Model
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class SkyscannerCache extends ActiveRecord
{
    const TMP_Path = '/files/tmp/';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ts_fare_cache_skyscanner';
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
            [['start','end'], 'string', 'max' => 5],
            ['data', 'string'],
            ['date', 'date']
        ];
    }
    
}
